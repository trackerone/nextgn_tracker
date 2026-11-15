(() => {
    const form = document.getElementById('torrent-upload-form');
    if (!form) return;

    const button = document.getElementById('analyse-upload');
    const status = document.getElementById('upload-analysis-status');
    const reviewed = document.getElementById('metadata-reviewed');
    const dirty = new Set();
    const detected = new Map();
    let generation = 0;
    let controller;

    form.addEventListener('input', (event) => {
        if (event.target.name) dirty.add(event.target.name);
    });

    for (const name of ['torrent_file', 'nfo_file', 'nfo_text']) {
        form.elements.namedItem(name)?.addEventListener('change', () => {
            generation++;
            controller?.abort();
            reviewed.value = '0';
            for (const [field, value] of detected) {
                const input = form.elements.namedItem(field);
                if (input && !dirty.has(field) && input.value === value) input.value = '';
            }
            detected.clear();
            status.textContent = 'Files changed. Analyse them again before reviewing the metadata.';
            button.disabled = false;
        });
    }

    button.addEventListener('click', async () => {
        if (!form.elements.namedItem('torrent_file').files.length) {
            status.textContent = 'Choose a .torrent file first.';
            return;
        }

        const current = ++generation;
        controller?.abort();
        controller = new AbortController();
        button.disabled = true;
        reviewed.value = '0';
        status.textContent = 'Analysing torrent and NFO…';
        const data = new FormData();
        for (const name of ['_token', 'torrent_file', 'nfo_file', 'nfo_text']) {
            const input = form.elements.namedItem(name);
            if (input.type === 'file') {
                if (input.files.length) data.append(name, input.files[0]);
            } else {
                data.append(name, input.value);
            }
        }

        try {
            const response = await fetch(form.dataset.analysisUrl, {
                method: 'POST',
                headers: { Accept: 'application/json' },
                body: data,
                signal: controller.signal,
            });
            const result = await response.json();
            if (!response.ok) {
                throw new Error(Object.values(result.errors || {}).flat().join(' ') || 'Analysis failed. Please try again.');
            }
            if (current !== generation) return;

            let filled = 0;
            for (const [name, value] of Object.entries(result.fields)) {
                const input = form.elements.namedItem(name);
                // Keep old validation input and every field the user has edited.
                if (!input || dirty.has(name) || input.value.trim() !== '') continue;
                input.value = String(value);
                detected.set(name, input.value);
                filled++;
            }
            reviewed.value = '1';
            document.getElementById('upload-extra-metadata').open = true;
            status.textContent = `${result.files} content files detected. ${filled} fields filled. Review the details; unknown values remain blank. ${(result.warnings || []).join(' ')}`;
        } catch (error) {
            if (current === generation && error.name !== 'AbortError') {
                status.textContent = error.message || 'Analysis failed. Your entries have been preserved.';
            }
        } finally {
            if (current === generation) button.disabled = false;
        }
    });
})();
