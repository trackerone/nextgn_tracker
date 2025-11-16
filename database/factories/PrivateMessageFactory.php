<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\MarkdownService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class PrivateMessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        $bodyMd = $this->faker->sentence();

        return [
            'conversation_id' => Conversation::factory(),
            'sender_id' => User::factory(),
            'body_md' => $bodyMd,
            'body_html' => fn () => app(MarkdownService::class)->render($bodyMd),
            'read_at' => null,
        ];
    }
}
