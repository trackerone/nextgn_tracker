<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Torrent;
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
                Rule::in([
                    Torrent::STATUS_PENDING,
                    Torrent::STATUS_PUBLISHED,
                    Torrent::STATUS_REJECTED,
                    Torrent::STATUS_SOFT_DELETED,
                ]),
            ],
        ];
    }
}
