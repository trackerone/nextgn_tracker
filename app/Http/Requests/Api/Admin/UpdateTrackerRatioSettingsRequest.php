<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTrackerRatioSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'enforcement_enabled' => ['required', 'boolean'],
            'minimum_download_ratio' => ['required', 'numeric', 'min:0'],
            'freeleech_bypass_enabled' => ['required', 'boolean'],
            'no_history_grace_enabled' => ['required', 'boolean'],
        ];
    }
}
