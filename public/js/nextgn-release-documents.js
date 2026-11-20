document.querySelectorAll('[data-release-disclosure]').forEach((button) => {
    const panel = document.getElementById(button.getAttribute('aria-controls'));

    if (!(panel instanceof HTMLDetailsElement)) {
        return;
    }

    button.addEventListener('click', () => {
        panel.open = !panel.open;
    });

    panel.addEventListener('toggle', () => {
        button.setAttribute('aria-expanded', String(panel.open));
    });
});
