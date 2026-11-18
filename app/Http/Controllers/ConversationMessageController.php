<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\MessageRepositoryInterface;
use App\Http\Requests\PrivateMessages\StoreMessageRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\NewPrivateMessageNotification;
use App\Services\MarkdownService;
use Symfony\Component\HttpFoundation\Response;

class ConversationMessageController extends Controller
{
    public function __construct(
        private readonly MarkdownService $markdownService,
        private readonly MessageRepositoryInterface $messages,
    ) {}

    public function store(StoreMessageRequest $request, Conversation $conversation): Response
    {
        $this->authorize('create', [Message::class, $conversation]);

        $user = $request->user();
        $data = $request->validated();

        $senderId = (int) $user->getKey();
        $html = $this->markdownService->render($data['body_md']);

        $message = $this->messages->sendMessage($conversation, [
            'sender_id' => $senderId,
            'body_md' => $data['body_md'],
            'body_html' => $html,
        ]);

        $recipientId = $conversation->otherParticipantId($senderId);
        $recipient = User::query()->find($recipientId);

        if ($recipient !== null) {
            $recipient->notify(new NewPrivateMessageNotification($message));
        }

        if (! $request->expectsJson()) {
            $page = max(1, (int) ceil($conversation->messages()->count() / 20));

            return redirect(route('pm.show', ['conversation' => $conversation->id, 'page' => $page]).'#message-'.$message->id)->with('status', 'Reply sent.');
        }

        return response()->json($message, Response::HTTP_CREATED);
    }
}
