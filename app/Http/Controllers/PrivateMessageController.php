<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PrivateMessages\StartConversationRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Services\MarkdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class PrivateMessageController extends Controller
{
    public function __construct(private readonly MarkdownService $markdownService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $conversations = Conversation::query()
            ->forUser((int) $user->getKey())
            ->with([
                'userA:id,name',
                'userB:id,name',
                'lastMessage.sender:id,name',
            ])
            ->orderByDesc('last_message_at')
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($conversations);
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

        [$firstId, $secondId] = $senderId < $recipientId
            ? [$senderId, $recipientId]
            : [$recipientId, $senderId];

        $html = $this->markdownService->render($data['body_md']);

        $conversation = null;
        $message = null;

        DB::transaction(function () use (&$conversation, &$message, $firstId, $secondId, $senderId, $data, $html): void {
            $conversation = Conversation::query()
                ->where('user_a_id', $firstId)
                ->where('user_b_id', $secondId)
                ->lockForUpdate()
                ->first();

            if ($conversation === null) {
                $conversation = Conversation::query()->create([
                    'user_a_id' => $firstId,
                    'user_b_id' => $secondId,
                ]);
            }

            $message = $conversation->messages()->create([
                'sender_id' => $senderId,
                'body_md' => $data['body_md'],
                'body_html' => $html,
            ]);

            $conversation->forceFill([
                'last_message_at' => $message->created_at,
            ])->save();
        });

        $message->load('sender:id,name');

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
