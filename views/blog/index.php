<section style="max-width:1000px;margin:0 auto;padding:3rem 1.5rem;">
    <h1 style="font-size:2rem;margin-bottom:2rem;">Blog</h1>
    <?php if (!$posts): ?><p>Todavía no hay artículos publicados.</p><?php endif; ?>
    <?php foreach ($posts as $post): ?>
        <article style="padding:1.5rem;margin-bottom:1.25rem;border:1px solid #334155;border-radius:1rem;">
            <h2><a href="/blog/<?= rawurlencode($post['slug']) ?>" style="color:#38bdf8;"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></a></h2>
            <p style="margin-top:0.75rem;"><?= htmlspecialchars($post['excerpt'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </article>
    <?php endforeach; ?>
</section>
