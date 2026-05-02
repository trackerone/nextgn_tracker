<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Admin\ClearMetadataCredentialRequest;
use App\Http\Requests\Api\Admin\SetMetadataCredentialRequest;
use App\Services\Logging\AuditLogger;
use App\Services\Metadata\MetadataCredentialsRepository;
use Illuminate\Http\JsonResponse;

final class MetadataCredentialSettingsController extends Controller
{
    public function __construct(
        private readonly MetadataCredentialsRepository $credentials,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function status(): JsonResponse
    {
        return response()->json($this->statusPayload());
    }

    public function set(SetMetadataCredentialRequest $request, string $provider): JsonResponse
    {
        $keyByField = $this->credentialKeyMap()[$provider];
        $validated = $request->validated();

        foreach ($keyByField as $field => $key) {
            if (array_key_exists($field, $validated)) {
                $this->credentials->setSecret($key, (string) $validated[$field]);
            }
        }

        $this->auditLogger->log('settings.metadata.credentials.updated', null, [
            'provider' => $provider,
            'fields' => array_keys(array_intersect_key($keyByField, $validated)),
        ]);

        return response()->json($this->providerStatusPayload($provider));
    }

    public function clear(ClearMetadataCredentialRequest $request, string $provider, string $field): JsonResponse
    {
        $key = $this->credentialKeyMap()[$provider][$field];

        $this->credentials->clearSecret($key);

        $this->auditLogger->log('settings.metadata.credentials.cleared', null, [
            'provider' => $provider,
            'field' => $field,
        ]);

        return response()->json($this->providerStatusPayload($provider));
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function credentialKeyMap(): array
    {
        return [
            'tmdb' => [
                'api_key' => 'metadata.providers.tmdb.api_key',
            ],
            'trakt' => [
                'client_id' => 'metadata.providers.trakt.client_id',
                'client_secret' => 'metadata.providers.trakt.client_secret',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function statusPayload(): array
    {
        return [
            'tmdb' => $this->providerStatusPayload('tmdb'),
            'trakt' => $this->providerStatusPayload('trakt'),
        ];
    }

    /**
     * @return array<string, bool>
     */
    private function providerStatusPayload(string $provider): array
    {
        $status = [];

        foreach ($this->credentialKeyMap()[$provider] as $field => $key) {
            $status['has_'. $field] = $this->credentials->hasSecret($key);
        }

        return $status;
    }
}
