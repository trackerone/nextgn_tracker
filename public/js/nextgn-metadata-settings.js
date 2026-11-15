(() => {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const statusEl = document.getElementById('status');
    const errorEl = document.getElementById('error');

    const showStatus = (message) => {
        statusEl.textContent = message;
        statusEl.hidden = false;
        errorEl.hidden = true;
    };

    const showError = async (error) => {
        const msg = error instanceof Error ? error.message : 'Request failed';
        errorEl.textContent = msg;
        errorEl.hidden = false;
        statusEl.hidden = true;
    };

    const request = async (url, options = {}) => {
        const response = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                ...(options.headers ?? {}),
            },
            ...options,
        });

        if (!response.ok) {
            let message = 'Request failed';
            const contentType = response.headers.get('content-type') ?? '';

            if (contentType.includes('application/json')) {
                const data = await response.json().catch(() => ({}));
                const firstError = data?.errors && typeof data.errors === 'object'
                    ? Object.values(data.errors).flat().find((value) => typeof value === 'string')
                    : null;
                if (typeof data?.message === 'string' && data.message.trim() !== '') {
                    message = data.message;
                } else if (typeof firstError === 'string' && firstError.trim() !== '') {
                    message = firstError;
                }
            } else if (response.status >= 500) {
                message = 'Server error. Please try again.';
            }

            throw new Error(message);
        }

        return response.status === 204 ? {} : await response.json();
    };

    const setBadge = (id, hasValue) => {
        const el = document.getElementById(id);
        el.textContent = hasValue ? 'Configured' : 'Missing';
        el.className = `badge ${hasValue ? 'badge-ok' : 'badge-missing'}`;
    };

    const loadState = async () => {
        const [providerSettings, credentialStatus] = await Promise.all([
            request('/api/admin/settings/metadata/providers'),
            request('/api/admin/settings/metadata/credentials/status'),
        ]);

        document.getElementById('enrichment_enabled').checked = !!providerSettings.enrichment_enabled;
        document.getElementById('auto_on_publish').checked = !!providerSettings.auto_on_publish;
        document.getElementById('refresh_after_days').value = String(providerSettings.refresh_after_days ?? 1);
        document.getElementById('provider_tmdb').checked = !!providerSettings.providers?.tmdb?.enabled;
        document.getElementById('provider_trakt').checked = !!providerSettings.providers?.trakt?.enabled;
        document.getElementById('provider_imdb').checked = !!providerSettings.providers?.imdb?.enabled;
        document.getElementById('priority').value = Array.isArray(providerSettings.priority) ? providerSettings.priority.join(',') : 'tmdb,trakt,imdb';

        setBadge('status_tmdb_api_key', !!credentialStatus.tmdb?.has_api_key);
        setBadge('status_trakt_client_id', !!credentialStatus.trakt?.has_client_id);
        setBadge('status_trakt_client_secret', !!credentialStatus.trakt?.has_client_secret);
    };

    document.getElementById('provider-settings-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const priority = document.getElementById('priority').value
                .split(',')
                .map((value) => value.trim())
                .filter((value) => value.length > 0);

            await request('/api/admin/settings/metadata/providers', {
                method: 'POST',
                body: JSON.stringify({
                    enrichment_enabled: document.getElementById('enrichment_enabled').checked,
                    auto_on_publish: document.getElementById('auto_on_publish').checked,
                    refresh_after_days: Number.parseInt(document.getElementById('refresh_after_days').value, 10),
                    providers: {
                        tmdb: { enabled: document.getElementById('provider_tmdb').checked },
                        trakt: { enabled: document.getElementById('provider_trakt').checked },
                        imdb: { enabled: document.getElementById('provider_imdb').checked },
                    },
                    priority,
                }),
            });

            showStatus('Provider settings saved.');
        } catch (error) {
            await showError(error);
        }
    });

    document.getElementById('tmdb-credentials-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const apiKey = document.getElementById('tmdb_api_key').value;
            if (!apiKey) {
                throw new Error('TMDB API key is required.');
            }

            await request('/api/admin/settings/metadata/credentials/tmdb', {
                method: 'PUT',
                body: JSON.stringify({ api_key: apiKey }),
            });

            document.getElementById('tmdb_api_key').value = '';
            await loadState();
            showStatus('TMDB credential updated.');
        } catch (error) {
            await showError(error);
        }
    });

    document.getElementById('trakt-credentials-form').addEventListener('submit', async (event) => {
        event.preventDefault();
        try {
            const clientId = document.getElementById('trakt_client_id').value;
            const clientSecret = document.getElementById('trakt_client_secret').value;
            if (!clientId && !clientSecret) {
                throw new Error('At least one Trakt field is required.');
            }

            const payload = {};
            if (clientId) payload.client_id = clientId;
            if (clientSecret) payload.client_secret = clientSecret;

            await request('/api/admin/settings/metadata/credentials/trakt', {
                method: 'PUT',
                body: JSON.stringify(payload),
            });

            document.getElementById('trakt_client_id').value = '';
            document.getElementById('trakt_client_secret').value = '';
            await loadState();
            showStatus('Trakt credential(s) updated.');
        } catch (error) {
            await showError(error);
        }
    });

    const bindClear = (buttonId, provider, field) => {
        document.getElementById(buttonId).addEventListener('click', async () => {
            if (!window.confirm(`Clear ${provider} ${field}?`)) {
                return;
            }

            try {
                await request(`/api/admin/settings/metadata/credentials/${provider}/${field}`, { method: 'DELETE' });
                await loadState();
                showStatus(`${provider} ${field} cleared.`);
            } catch (error) {
                await showError(error);
            }
        });
    };

    bindClear('clear_tmdb_api_key', 'tmdb', 'api_key');
    bindClear('clear_trakt_client_id', 'trakt', 'client_id');
    bindClear('clear_trakt_client_secret', 'trakt', 'client_secret');

    loadState().catch(showError);
})();
