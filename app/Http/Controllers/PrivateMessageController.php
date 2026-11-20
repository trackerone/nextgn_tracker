<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ConversationRepositoryInterface;
use App\Contracts\MessageRepositoryInterface;
use App\Http\Requests\PrivateMessages\StartConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Services\MarkdownService;
use App\Services\Notifications\PrivateMessageNotificationDispatcher;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class PrivateMessageController extends Controller
{
    public function __construct(
        private readonly MarkdownService $markdownService,
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $conversations */
        $conversations = $this->conversations->paginateForUser($user, perPage: 20);

        if (! $request->expectsJson()) {
            return response()->view('pm.index', ['conversations' => $conversations])->header('Cache-Control', 'private, no-store');
        }

        return response()->json($conversations->getCollection());
    }

    public function create(Request $request): Response
    {
        $recipient = null;
        if ($request->filled('recipient')) {
            $data = $request->validate(['recipient' => ['string', 'exists:users,public_slug']]);
            $recipient = User::query()->where('public_slug', $data['recipient'])->firstOrFail();
        }

        return response()->view('pm.create', ['recipient' => $recipient])->header('Cache-Control', 'private, no-store');
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        $this->authorize('view', $conversation);

        $userId = (int) $request->user()->getKey();
        if (! $request->expectsJson()) {
            $conversation->load(['userA:id,name,public_slug', 'userB:id,name,public_slug']);
            $messages = $conversation->messages()->with('sender:id,name,public_slug')->orderBy('created_at')->orderBy('id')->paginate(20);
            $conversation->messages()->whereIn('id', $messages->getCollection()->pluck('id'))
                ->whereNull('read_at')->where('sender_id', '!=', $userId)->update(['read_at' => now()]);

            return response()->view('pm.show', [
                'conversation' => $conversation,
                'messages' => $messages,
                'otherUser' => (int) $conversation->user_a_id === $userId ? $conversation->userB : $conversation->userA,
            ])->header('Cache-Control', 'private, no-store');
        }

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->update(['read_at' => now()]);

        $conversation->load([
            'userA:id,name',
            'userB:id,name',
            'messages' => static fn ($query) => $query
                ->select([
                    'messages.id',
                    'messages.conversation_id',
                    'messages.sender_id',
                    'messages.body_md',
                    'messages.body_html',
                    'messages.read_at',
                    'messages.created_at',
                    'messages.updated_at',
                ])
                ->with('sender:id,name')
                ->orderBy('created_at'),
        ]);

        return response()->json($conversation);
    }

    public function store(StartConversationRequest $request): Response
    {
        $user = $request->user();
        $data = $request->validated();

        $senderId = (int) $user->getKey();
        $recipient = $request->expectsJson()
            ? User::query()->findOrFail((int) $data['recipient_id'])
            : User::query()->where('public_slug', $data['recipient_slug'])->firstOrFail();
        $recipientId = (int) $recipient->getKey();

        if ($senderId === $recipientId) {
            if (! $request->expectsJson()) {
                throw ValidationException::withMessages(['recipient_slug' => 'You cannot message yourself.']);
            }

            return response()->json([
                'message' => 'You cannot message yourself.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $html = $this->markdownService->render($data['body_md']);

        $conversation = $this->conversations->startConversation($user, $recipient);

        $message = $this->messages->sendMessage($conversation, [
            'sender_id' => $senderId,
            'body_md' => $data['body_md'],
            'body_html' => $html,
        ]);

        $conversation->load([
            'userA:id,name',
            'userB:id,name',
            'lastMessage.sender:id,name',
        ]);

        $recipientKey = $conversation->otherParticipantId($senderId);
        $recipient = User::query()->find($recipientKey);

        if ($recipient !== null) {
            app(PrivateMessageNotificationDispatcher::class)->send($recipient, $message);
        }

        if (! $request->expectsJson()) {
            $page = max(1, (int) ceil($conversation->messages()->count() / 20));

            return redirect(route('pm.show', ['conversation' => $conversation->id, 'page' => $page]).'#message-'.$message->id)->with('status', 'Message sent.');
        }

        return response()->json([
            'conversation' => $conversation,
            'message' => $message,
        ], Response::HTTP_CREATED);
    }
}
