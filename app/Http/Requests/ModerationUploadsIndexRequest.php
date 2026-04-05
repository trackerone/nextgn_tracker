<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TorrentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ModerationUploadsIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => [
                'nullable',
                'string',
                Rule::in(TorrentStatus::values()),
            ],
        ];
    }
}
