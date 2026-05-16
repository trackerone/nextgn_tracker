<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\SecurityAuditLog;
use App\Support\Uploads\TorrentUploadRules;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Validation\Validator as LaravelValidator;

trait ValidatesTorrentUpload
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return TorrentUploadRules::rules();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return TorrentUploadRules::messages();
    }

    public function withValidator(LaravelValidator $validator): void
    {
        $validator->after(function (LaravelValidator $validator): void {
            if ($this->file('nfo_file') !== null && $this->filled('nfo_text')) {
                $validator->errors()->add('nfo_text', 'Provide either an NFO file or inline text, not both.');
            }
        });
    }

    protected function failedValidation(ValidatorContract $validator): void
    {
        SecurityAuditLog::log($this->user(), 'torrent.upload.validation_failed', [
            'errors' => $validator->errors()->keys(),
            'has_torrent_file' => $this->hasFile('torrent_file'),
        ]);

        parent::failedValidation($validator);
    }
}
