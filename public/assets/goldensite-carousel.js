for (const carousel of document.querySelectorAll('.gs-carousel')) {
    const track = carousel.querySelector('.gs-carousel-track');
    const controls = carousel.querySelector('.gs-carousel-controls');
    if (!track || !controls) continue;
    const cards = Array.from(track.children);
    const previous = controls.querySelector('[data-carousel-step="-1"]');
    const next = controls.querySelector('[data-carousel-step="1"]');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
    let current = 0;
    let pendingFrame = 0;
    const offset = index => cards[index].offsetLeft - cards[0].offsetLeft;

    function sync() {
        pendingFrame = 0;
        const maximum = Math.max(0, track.scrollWidth - track.clientWidth);
        const position = track.scrollLeft;
        current = 0;
        while (current + 1 < cards.length && offset(current + 1) <= position + 2) current++;
        cards.forEach((card, number) => card.toggleAttribute('data-current', number === current));
        previous.disabled = position <= 2;
        next.disabled = position >= maximum - 2;
        controls.hidden = maximum <= 2;
    }

    function move(direction) {
        const maximum = track.scrollWidth - track.clientWidth;
        const target = Math.min(cards.length - 1, Math.max(0, current + direction));
        track.scrollTo({left: Math.min(maximum, offset(target)), behavior: reducedMotion.matches ? 'auto' : 'smooth'});
    }

    previous.addEventListener('click', () => move(-1));
    next.addEventListener('click', () => move(1));
    track.addEventListener('scroll', () => {
        if (!pendingFrame) pendingFrame = requestAnimationFrame(sync);
    }, {passive:true});
    new ResizeObserver(sync).observe(track);
    sync();
}
