(() => {
    const input = document.getElementById('browse-query');
    const form = input?.form;
    const status = document.getElementById('browse-search-status');
    if (!form || !status) {
        return;
    }

    let timer;
    let pending;

    const cancel = () => {
        window.clearTimeout(timer);
        pending?.abort();
    };

    const refresh = async () => {
        const current = new AbortController();
        pending = current;
        const url = new URL(form.action, window.location.href);
        url.search = window.location.search;
        url.searchParams.delete('page');
        url.searchParams.delete('category');

        for (const [name, value] of new FormData(form)) {
            if (value === '') {
                url.searchParams.delete(name);
            } else {
                url.searchParams.set(name, value);
            }
        }

        try {
            const response = await fetch(url, {
                headers: { Accept: 'text/html' },
                signal: current.signal,
            });
            if (!response.ok || response.redirected) {
                throw new Error('Browse search failed');
            }

            const page = new DOMParser().parseFromString(await response.text(), 'text/html');
            if (current.signal.aborted) {
                return;
            }

            const results = page.getElementById('browse-results');
            if (!results) {
                throw new Error('Browse results missing');
            }

            for (const selector of ['#browse-results', '[data-browse-saved-filters]']) {
                const replacement = page.querySelector(selector);
                if (replacement) {
                    document.querySelector(selector)?.replaceWith(replacement);
                }
            }

            window.history.replaceState(window.history.state, '', url);
            status.textContent = results.querySelector('[data-browse-result-count]')?.textContent.trim() ?? '';
            status.classList.add('sr-only');
        } catch (error) {
            if (!current.signal.aborted) {
                status.textContent = 'Live search could not update. Use Search to try again.';
                status.classList.remove('sr-only');
            }
        }
    };

    const schedule = (event) => {
        cancel();
        const length = Array.from(input.value.trim()).length;
        if (!event.isComposing && length !== 1) {
            timer = window.setTimeout(refresh, 400);
        }
    };

    input.addEventListener('input', schedule);
    input.addEventListener('compositionend', schedule);
    form.addEventListener('change', (event) => {
        if (event.target !== input) {
            cancel();
        }
    });
    form.addEventListener('submit', cancel);
})();
