<?php if ($assets): ?>
    <link rel="stylesheet" href="/assets/goldensite-carousel.css">
    <script src="/assets/goldensite-carousel.js" defer></script>
<?php endif; ?>
<section class="gs-carousel" aria-label="<?= $escape($title) ?>" data-effect="<?= $escape($effect) ?>">
    <div class="gs-carousel-header">
        <h2><?= $escape($title) ?></h2>
        <?php if (count($cards) > 1): ?>
            <div class="gs-carousel-controls" hidden>
                <button type="button" data-carousel-step="-1" aria-label="Tarjeta anterior">←</button>
                <button type="button" data-carousel-step="1" aria-label="Tarjeta siguiente">→</button>
            </div>
        <?php endif; ?>
    </div>
    <ul class="gs-carousel-track" tabindex="0" aria-label="Tarjetas de <?= $escape($title) ?>">
        <?php foreach ($cards as $index => [$image,$label,$link]): ?>
            <li class="gs-carousel-item">
                <?php if ($link !== ''): ?><a href="<?= $escape($link) ?>" aria-label="<?= $escape($label !== '' ? $label : 'Abrir tarjeta '.($index+1)) ?>"><?php endif; ?>
                <img src="<?= $escape($image) ?>" alt="<?= $link === '' ? $escape($label) : '' ?>" loading="lazy" decoding="async">
                <?php if ($label !== ''): ?><span><?= $escape($label) ?></span><?php endif; ?>
                <?php if ($link !== ''): ?></a><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</section>
