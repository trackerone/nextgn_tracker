(() => {
    const createForm = document.getElementById('create-api-key');
    if (!createForm) return;
    const status = document.getElementById('api-key-status');
    const error = document.getElementById('api-key-error');
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const request = async (url, method, body) => {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
            body: body === undefined ? undefined : JSON.stringify(body),
        });
        const data = await response.json();
        if (!response.ok) throw new Error(data.message ?? 'Request failed.');
        return data;
    };
    const run = async (form, action) => {
        const button = form.querySelector('button');
        button.disabled = true;
        error.hidden = true;
        status.hidden = true;
        try { await action(); }
        catch (failure) {
            error.textContent = failure instanceof Error ? failure.message : 'Request failed.';
            error.hidden = false;
        } finally { button.disabled = false; }
    };
    const bindRevoke = (form) => form.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!window.confirm('Revoke this API key? Tools using it will stop working.')) return;
        run(form, async () => {
            await request(form.action, 'DELETE');
            form.closest('article').remove();
            status.textContent = 'API key revoked.';
            status.hidden = false;
        });
    });
    document.querySelectorAll('.revoke-api-key').forEach(bindRevoke);
    createForm.addEventListener('submit', (event) => {
        event.preventDefault();
        run(createForm, async () => {
            const label = createForm.elements.namedItem('label').value;
            const data = await request(createForm.action, 'POST', { label });
            document.getElementById('new-api-key').value = data.key;
            document.getElementById('api-key-result').hidden = false;
            const article = document.createElement('article');
            article.className = 'flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-700 p-4';
            const title = document.createElement('h3');
            title.textContent = label;
            const form = document.createElement('form');
            form.action = `${createForm.action.replace(/\/$/, '')}/${encodeURIComponent(data.id)}`;
            const button = document.createElement('button');
            button.textContent = 'Revoke key';
            button.className = 'rounded-xl border border-rose-500/50 px-3 py-2 text-sm text-rose-200';
            form.append(button);
            bindRevoke(form);
            article.append(title, form);
            document.getElementById('api-key-list').prepend(article);
            status.textContent = 'API key created. Save the key before leaving this page.';
            status.hidden = false;
            createForm.reset();
        });
    });
})();
