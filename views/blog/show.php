<article data-nx-content class="nx-editorial" style="max-width:1000px;margin:0 auto;padding:3rem 1.5rem;">
    <a href="/blog" style="color:#38bdf8;">Volver al blog</a>
    <h1 style="font-size:2rem;margin:1.5rem 0;"><?= htmlspecialchars($post['title'], ENT_QUOTES, 'UTF-8') ?></h1>
    <div><?= \App\Core\FrontendEngine::renderShortcodes(\App\Core\PaywallEngine::parse($post['content'] ?? '')) ?></div>
</article>
