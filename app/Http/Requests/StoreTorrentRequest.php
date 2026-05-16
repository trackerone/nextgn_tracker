<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesTorrentUpload;
use Illuminate\Foundation\Http\FormRequest;

class StoreTorrentRequest extends FormRequest
{
    use ValidatesTorrentUpload;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }
}
