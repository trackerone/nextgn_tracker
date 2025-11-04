<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\PrivateMessages\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Services\MarkdownService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class ConversationMessageController extends Controller
{
    public function __construct(private readonly MarkdownService $markdownService)
    {
    }

    public function store(StoreMessageRequest $request, Conversation $conversation): JsonResponse
    {
        $this->authorize('create', [Message::class, $conversation]);

        $user = $request->user();
        $data = $request->validated();

        $senderId = (int) $user->getKey();
        $html = $this->markdownService->render($data['body_md']);

        $message = null;

        DB::transaction(function () use (&$message, $conversation, $senderId, $data, $html): void {
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

        $recipientId = $conversation->otherParticipantId($senderId);
        $recipient = User::query()->find($recipientId);

        if ($recipient !== null) {
            $recipient->notify(new NewPrivateMessageNotification($message));
        }

        return response()->json($message, Response::HTTP_CREATED);
    }
}
