document.querySelectorAll('[data-trailer-id]').forEach((link) => {
    link.addEventListener('click', (event) => {
        const videoId = link.dataset.trailerId;
        if (!/^[A-Za-z0-9_-]{11}$/.test(videoId)) {
            return;
        }

        event.preventDefault();

        const player = document.createElement('iframe');
        player.src = 'https://www.youtube-nocookie.com/embed/' + videoId + '?autoplay=1';
        player.title = 'YouTube trailer';
        player.className = 'h-full w-full border-0';
        player.allow = 'autoplay; encrypted-media; picture-in-picture; fullscreen';
        player.allowFullscreen = true;
        link.replaceWith(player);
        player.focus();
    });
});
