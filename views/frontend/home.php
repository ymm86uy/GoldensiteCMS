<?php
$title = \App\Core\Settings::get('site_title','GoldenSiteCMS'); $siteTitle=$title;
$home=\App\Core\HomePage::values();
$e=\App\Core\ConsoleView::escape(...);
$split=$home['layout']==='split' && $home['image']!=='';
$officialDemo=strtolower((string)parse_url(\App\Core\Environment::get('APP_URL'),PHP_URL_HOST))==='goldensitecms.com';

ob_start();
?>
<style>
    .hero-container {
        position: relative;
        text-align: center;
        padding: 5.5rem 1.5rem 4.5rem;
        background: radial-gradient(circle at 50% 20%, rgba(56, 189, 248, 0.12) 0%, rgba(15, 23, 42, 0) 70%);
        border-radius: 2rem;
        border: 1px solid rgba(255, 255, 255, 0.08);
        margin-bottom: 3.5rem;
    }
    .badge-pill {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid var(--primary);
        color: var(--primary);
        padding: 0.4rem 1.25rem;
        border-radius: 50px;
        font-size: 0.8rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        margin-bottom: 1.5rem;
    }
    .hero-title {
        font-size: clamp(2rem, 6vw, 3.5rem);
        font-weight: 900;
        line-height: 1.15;
        margin-bottom: 1.25rem;
        color: var(--text-color);
    }
    .hero-title span {
        background: linear-gradient(135deg, var(--primary) 0%, #a855f7 100%);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    .hero-description {
        max-width: 680px;
        margin: 0 auto 2.5rem;
        font-size: 1.1rem;
        color: #94a3b8;
        line-height: 1.6;
    }
    .hero-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 1.25rem;
        justify-content: center;
    }
    .btn-hero-main {
        background: var(--primary);
        color: #000;
        font-weight: 900;
        padding: 0.95rem 2.2rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        transition: transform 0.2s;
    }
    .btn-hero-main:hover { transform: translateY(-2px); }
    .btn-hero-secondary {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.15);
        color: var(--text-color);
        font-weight: 700;
        padding: 0.95rem 2.2rem;
        border-radius: var(--border-radius);
        text-decoration: none;
        transition: all 0.2s;
    }
    .btn-hero-secondary:hover { border-color: var(--primary); color: var(--primary); }
    .hero-cover {display:block;width:100%;max-height:32rem;object-fit:cover;margin:2rem auto 0;border-radius:var(--border-radius)}
    .hero-container.hero-split {display:grid;grid-template-columns:repeat(2,minmax(0,1fr));align-items:center;gap:clamp(1.5rem,4vw,3.5rem);text-align:left}
    .hero-split .hero-description {margin-inline:0}
    .hero-split .hero-actions {justify-content:flex-start}
    .hero-split .hero-cover {margin:0}
    @media(max-width:760px){.hero-container.hero-split {grid-template-columns:1fr}}
    
    .modules-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(min(100%, 300px), 1fr));
        gap: 1.75rem;
    }
    .module-card {
        background: var(--card-bg);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: var(--border-radius);
        padding: 2rem;
        transition: all 0.3s ease;
    }
    .module-card:hover {
        transform: translateY(-5px);
        border-color: var(--primary);
    }
</style>

<div class="hero-container<?= $split?' hero-split':'' ?>">
    <div>
        <?php if ($home['eyebrow']!==''): ?><div class="badge-pill"><?= $e($home['eyebrow']) ?></div><?php endif ?>
        <h1 class="hero-title"><?= $e($home['headline']) ?><?php if ($home['highlight']!==''): ?> <br><span><?= $e($home['highlight']) ?></span><?php endif ?></h1>
        <?php if ($home['description']!==''): ?><p class="hero-description"><?= $e($home['description']) ?></p><?php endif ?>
        <?php if ($home['primary_link']!=='' || $home['secondary_link']!==''): ?>
            <div class="hero-actions">
                <?php if ($home['primary_link']!==''): ?><a href="<?= $e($home['primary_link']) ?>" class="btn-hero-main"><?= $e($home['primary_label']) ?></a><?php endif ?>
                <?php if ($home['secondary_link']!==''): ?><a href="<?= $e($home['secondary_link']) ?>" class="btn-hero-secondary"><?= $e($home['secondary_label']) ?></a><?php endif ?>
            </div>
        <?php endif ?>
    </div>
    <?php if ($home['image']!==''): ?><img class="hero-cover" src="<?= $e($home['image']) ?>" alt="<?= $e($home['image_alt']) ?>" fetchpriority="high" decoding="async"><?php endif ?>
</div>

<?php if ($officialDemo): ?>
<section aria-labelledby="gs-product-workflow" style="margin-bottom:4rem">
    <h2 id="gs-product-workflow">De tu primer borrador al sitio publicado</h2>
    <ol class="modules-grid" style="list-style:none;padding:0">
        <li class="module-card"><h3>Escribe y previsualiza</h3><p>Crea una página con secciones reutilizables y revisa cómo se verá en escritorio, tableta y móvil.</p></li>
        <li class="module-card"><h3>Envía a revisión</h3><p>Un revisor puede aprobar el borrador guardado antes de programar su publicación.</p></li>
        <li class="module-card"><h3>Publica a la hora prevista</h3><p>Programa la fecha y consulta las versiones anteriores desde el historial editorial.</p></li>
    </ol>
</section>
<?php endif ?>

<section style="margin-bottom: 4rem;">
    <div style="text-align: center; margin-bottom: 2.5rem;">
        <?php if ($home['services_title']!==''): ?><h2 style="font-size: 1.75rem; font-weight: 800; color: var(--text-color);"><?= $e($home['services_title']) ?></h2><?php endif ?>
        <?php if ($home['services_description']!==''): ?><p style="color: #94a3b8; font-size: 0.95rem;"><?= $e($home['services_description']) ?></p><?php endif ?>
    </div>

    <div class="modules-grid">
        <div class="module-card">
            <div style="font-size: 2rem; margin-bottom: 1rem;">⚡</div>
            <h3 style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Rendimiento Optimizado</h3>
            <p style="font-size: 0.875rem; color: #94a3b8; line-height: 1.5;">Compresión dinámica de recursos y entrega veloz de contenidos para el usuario.</p>
        </div>

        <div class="module-card">
            <div style="font-size: 2rem; margin-bottom: 1rem;">🛡️</div>
            <h3 style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Seguridad Activa</h3>
            <p style="font-size: 0.875rem; color: #94a3b8; line-height: 1.5;">Protección perimetral proactiva frente a amenazas cibernéticas comunes.</p>
        </div>

        <div class="module-card">
            <div style="font-size: 2rem; margin-bottom: 1rem;">💼</div>
            <h3 style="font-size: 1.2rem; font-weight: bold; margin-bottom: 0.5rem;">Arquitectura Modular</h3>
            <p style="font-size: 0.875rem; color: #94a3b8; line-height: 1.5;">Activación inmediata de características según la demanda de tu negocio.</p>
        </div>
    </div>
</section>
<?php
$content = ob_get_clean();

$layoutPath = __DIR__ . '/layouts/frontend.php';
if (!file_exists($layoutPath)) {
    $layoutPath = __DIR__ . '/../layouts/frontend.php';
}

require_once $layoutPath;
