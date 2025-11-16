<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\ConversationRepositoryInterface;
use App\Contracts\MessageRepositoryInterface;
use App\Http\Requests\PrivateMessages\StartConversationRequest;
use App\Models\Conversation;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Services\MarkdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivateMessageController extends Controller
{
    public function __construct(
        private readonly MarkdownService $markdownService,
        private readonly ConversationRepositoryInterface $conversations,
        private readonly MessageRepositoryInterface $messages,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        /** @var \Illuminate\Pagination\LengthAwarePaginator $conversations */
        $conversations = $this->conversations->paginateForUser($user, perPage: 20);

        return response()->json($conversations->getCollection());
    }

    public function show(Request $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $userId = (int) $request->user()->getKey();

        $conversation->messages()
            ->whereNull('read_at')
            ->where('sender_id', '!=', $userId)
            ->update(['read_at' => now()]);

        $conversation->load([
            'userA:id,name',
            'userB:id,name',
            'messages' => static fn ($query) => $query
                ->with('sender:id,name')
                ->orderBy('created_at'),
        ]);

        return response()->json($conversation);
    }

    public function store(StartConversationRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $senderId = (int) $user->getKey();
        $recipientId = (int) $data['recipient_id'];

        if ($senderId === $recipientId) {
            return response()->json([
                'message' => 'You cannot message yourself.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $html = $this->markdownService->render($data['body_md']);

        $recipient = User::query()->findOrFail($recipientId);
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
            $recipient->notify(new NewPrivateMessageNotification($message));
        }

        return response()->json([
            'conversation' => $conversation,
            'message' => $message,
        ], Response::HTTP_CREATED);
    }
}
