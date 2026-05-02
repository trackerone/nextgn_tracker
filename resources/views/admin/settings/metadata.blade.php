<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Metadata Settings</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont; background-color: #0b1120; color: #e2e8f0; padding: 2.5rem; }
        h1 { font-size: 2rem; font-weight: 600; margin-bottom: 1rem; }
        h2 { font-size: 1.2rem; font-weight: 600; margin-top: 1.5rem; }
        form { margin-top: 0.75rem; display: grid; gap: 1rem; max-width: 46rem; }
        label { display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.95rem; color: #cbd5f5; }
        input[type="number"], input[type="text"], input[type="password"] { padding: 0.65rem; border-radius: 0.375rem; border: 1px solid #1e293b; background: #0b1120; color: #f8fafc; }
        .row { display: flex; align-items: center; gap: 0.5rem; }
        .controls { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        button { padding: 0.6rem 0.95rem; border-radius: 0.375rem; border: none; background-color: #2563eb; color: white; font-weight: 600; cursor: pointer; }
        .secondary { background-color: #334155; }
        .danger { background-color: #b91c1c; }
        .status { margin: 0.5rem 0; padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: #14532d; color: #dcfce7; max-width: 46rem; }
        .error { margin: 0.5rem 0; padding: 0.75rem 1rem; border-radius: 0.5rem; background: #450a0a; color: #fecdd3; max-width: 46rem; }
        .badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 999px; font-size: 0.8rem; font-weight: 600; }
        .badge-ok { background: #14532d; color: #dcfce7; }
        .badge-missing { background: #7f1d1d; color: #fecdd3; }
        ul { margin: 0.25rem 0 0; padding-left: 1.2rem; }
    </style>
</head>
<body>
<h1>Metadata Settings</h1>
<p>Only credential status is displayed. Stored secrets are never shown.</p>
<div id="status" class="status" hidden></div>
<div id="error" class="error" hidden></div>
<form id="provider-settings-form">
    <h2>Enrichment</h2>
    <label class="row"><input type="checkbox" id="enrichment_enabled"> Enable enrichment</label>
    <label class="row"><input type="checkbox" id="auto_on_publish"> Auto enrich on publish</label>
    <label>Refresh after days <input type="number" id="refresh_after_days" min="1" required></label>

    <h2>Providers</h2>
    <label class="row"><input type="checkbox" id="provider_tmdb"> TMDB enabled</label>
    <label class="row"><input type="checkbox" id="provider_trakt"> Trakt enabled</label>
    <label class="row"><input type="checkbox" id="provider_imdb"> IMDb enabled</label>

    <label>Priority order (comma separated: tmdb,trakt,imdb)
        <input type="text" id="priority" required>
    </label>

    <div class="controls">
        <button type="submit">Save provider settings</button>
    </div>
</form>

<h2>Credential status</h2>
<ul>
    <li>TMDB api_key: <span id="status_tmdb_api_key" class="badge badge-missing">Missing</span></li>
    <li>Trakt client_id: <span id="status_trakt_client_id" class="badge badge-missing">Missing</span></li>
    <li>Trakt client_secret: <span id="status_trakt_client_secret" class="badge badge-missing">Missing</span></li>
</ul>

<form id="tmdb-credentials-form">
    <h2>Set TMDB credential</h2>
    <label>TMDB API key <input type="password" id="tmdb_api_key" autocomplete="new-password"></label>
    <div class="controls">
        <button type="submit" class="secondary">Set TMDB API key</button>
        <button type="button" class="danger" id="clear_tmdb_api_key">Clear TMDB API key</button>
    </div>
</form>

<form id="trakt-credentials-form">
    <h2>Set Trakt credentials</h2>
    <label>Trakt client_id <input type="password" id="trakt_client_id" autocomplete="new-password"></label>
    <label>Trakt client_secret <input type="password" id="trakt_client_secret" autocomplete="new-password"></label>
    <div class="controls">
        <button type="submit" class="secondary">Set Trakt credentials</button>
        <button type="button" class="danger" id="clear_trakt_client_id">Clear Trakt client_id</button>
        <button type="button" class="danger" id="clear_trakt_client_secret">Clear Trakt client_secret</button>
    </div>
</form>

<script>
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
            throw new Error(await response.text() || 'Request failed');
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
</script>
</body>
</html>
