<?php
declare(strict_types=1);
$dark=$data['preset']==='consulting';
$background=$dark?'#111a1b':'#f4f2ec'; $ink=\App\Core\AdminTheme::contrast($background);
$accentInk=\App\Core\AdminTheme::contrast($data['accent']);
$font=\App\Core\Typography::stack($data['font']);
$type=\App\Core\Typography::style(false,['typography_json'=>json_encode(['font'=>$data['font'],'size'=>16,'heading'=>80])]);
?>
<!doctype html><html lang="<?= $e(\App\Core\Locale::language()) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="<?= $e(mb_substr($data['description'],0,160)) ?>"><title><?= $e($data['name']) ?></title>
<style><?= file_get_contents(dirname(__DIR__,2).'/public/assets/studio/site.css') ?></style>
<?= $type ?>
</head><body class="nx-public gs-site <?= $dark?'gs-consulting':'gs-agency' ?>" style="--gs-bg:<?= $background ?>;--gs-ink:<?= $ink ?>;--gs-accent:<?= $e($data['accent']) ?>;--gs-accent-ink:<?= $accentInk ?>;--gs-font:<?= $e($font) ?>">
<header class="gs-header gs-wrap"><a class="gs-brand" href="/" aria-label="<?= $e($data['name']) ?>, inicio"><?php if ($data['logo']!==''): ?><img class="gs-brand-logo" src="<?= $e($data['logo']) ?>" alt="" width="40" height="40"><?php else: ?><span class="gs-mark" aria-hidden="true">✳</span><?php endif ?><?= $e($data['name']) ?></a>
<nav aria-label="Navegación del sitio"><?php foreach ($data['sections'] as $section): ?><a href="#<?= $section ?>"><?= ['services'=>'Servicios','about'=>'Nosotros','contact'=>'Contacto'][$section] ?></a><?php endforeach ?><a class="gs-nav-cta" href="<?= $e($data['url']) ?>">Hablemos <span aria-hidden="true">↗</span></a></nav></header>
<main>
<section class="gs-hero gs-wrap"><div class="gs-hero-copy"><p class="gs-eyebrow"><span></span><?= $e($data['eyebrow']) ?></p><h1><?= $e($data['headline']) ?></h1><p class="gs-lead"><?= nl2br($e($data['description'])) ?></p><a class="gs-button" href="<?= $e($data['url']) ?>"><?= $e($data['cta']) ?><span aria-hidden="true">↗</span></a></div>
<div class="gs-hero-art"><?php if ($data['image']!==''): ?><img src="<?= $e($data['image']) ?>" alt="<?= $e($data['image_alt']) ?>" width="1000" height="1200" fetchpriority="high"><?php else: ?><div class="gs-sculpture" aria-hidden="true"><div class="gs-orbit"></div><div class="gs-sphere"></div><span class="gs-art-index">01 — POSIBILIDADES</span><span class="gs-art-note">Ideas que<br>toman forma.</span></div><?php endif ?><span class="gs-art-caption"><?= $e($data['name']) ?> <span aria-hidden="true">↗</span></span></div></section>
<?php foreach ($data['sections'] as $section): ?>
<?php if ($section==='services'): ?>
<section class="gs-section gs-wrap" id="services"><div class="gs-section-heading"><p class="gs-eyebrow">01 / SERVICIOS</p><h2><?= $e($data['services_title']) ?></h2></div><div class="gs-services">
<?php for ($i=1;$i<=3;$i++): if ($data['service_'.$i.'_title']==='') continue; ?><article><span class="gs-number">0<?= $i ?></span><span class="gs-service-arrow" aria-hidden="true">↗</span><h3><?= $e($data['service_'.$i.'_title']) ?></h3><p><?= $e($data['service_'.$i.'_text']) ?></p></article><?php endfor ?></div></section>
<?php elseif ($section==='about'): ?>
<section class="gs-about" id="about"><div class="gs-wrap gs-about-grid"><p class="gs-eyebrow">02 / NOSOTROS</p><div><h2><?= $e($data['about_title']) ?></h2><p><?= nl2br($e($data['about_text'])) ?></p></div><div class="gs-about-symbol" aria-hidden="true">✳</div></div></section>
<?php elseif ($section==='contact'): ?>
<section class="gs-section gs-wrap gs-contact" id="contact"><p class="gs-eyebrow">03 / CONVERSEMOS</p><h2><?= $e($data['contact_title']) ?></h2><p class="gs-lead"><?= nl2br($e($data['contact_text'])) ?></p>
<?php if ((int)$data['form_id']>0): ?><div class="gs-form-slot">[goldensite_form id="<?= (int)$data['form_id'] ?>"]</div><?php else: ?><a class="gs-button" href="<?= $e($data['url']) ?>"><?= $e($data['cta']) ?><span aria-hidden="true">↗</span></a><?php endif ?></section>
<?php endif; endforeach ?>
</main><footer class="gs-footer gs-wrap"><a class="gs-brand" href="/"><?= $e($data['name']) ?></a><span>© <?= date('Y') ?> · Todos los derechos reservados.</span><a href="/politica-cookies">Privacidad y cookies ↗</a></footer>
</body></html>
