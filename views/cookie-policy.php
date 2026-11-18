<?php
use Modules\GoldensiteGdprBanner\GdprModule;

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Política de cookies · <?= $escape($siteTitle) ?></title>
    <style>
        :root{color-scheme:light;background:#f5f7f1;color:#142018;font:1rem/1.65 system-ui,sans-serif}
        body{margin:0}header{background:#142018;color:#fff;padding:1rem max(1.25rem,calc((100% - 54rem)/2))}
        header a{color:inherit;font-weight:700}main{max-width:54rem;margin:auto;padding:2rem 1.25rem 3rem}
        h1{font-size:clamp(2rem,6vw,3.4rem);line-height:1.15;margin:.25rem 0 1rem}
        h2{line-height:1.25;margin:1.75rem 0 .5rem}a{color:#285438;text-underline-offset:.15em}
        :focus-visible{outline:3px solid #35624c;outline-offset:3px}
        .preferences{padding:1.25rem;background:#fff;border:1px solid #aabaaa;border-radius:.75rem}
        .preferences h2{margin-top:0}.goldensite-cookie-choices{display:flex;flex-wrap:wrap;gap:.6rem}
        button{min-height:44px;padding:.6rem 1rem;color:#142018;background:#ebf2df;border:1px solid #52674f;border-radius:.4rem;font:600 .95rem system-ui,sans-serif;cursor:pointer}
        .inventory{overflow:auto}table{width:100%;border-collapse:collapse;background:#fff}th,td{padding:.65rem;text-align:left;vertical-align:top;border-bottom:1px solid #cfd9cf}th{background:#e8eee2}code{overflow-wrap:anywhere}
    </style>
</head>
<body>
<header><a href="/"><?= $escape($siteTitle) ?></a></header>
<main>
    <h1>Política de cookies</h1>
    <p>Esta página explica qué datos guarda este sitio en tu navegador, para qué se usan y cómo puedes cambiar tu elección.</p>
    <section class="preferences" aria-labelledby="cookie-preferences">
        <h2 id="cookie-preferences">Tus preferencias</h2>
        <p id="cookie-choice-status" role="status"><?php
            if ($choice === 'essential') echo 'Preferencia actual: solo cookies necesarias.';
            elseif (!GdprModule::enabled()) echo 'Los servicios opcionales de este sitio están desactivados.';
            elseif ($choice === 'all') echo 'Preferencia actual: cookies opcionales aceptadas.';
            else echo 'Todavía no has elegido. Los servicios opcionales están bloqueados.';
        ?></p>
        <?php GdprModule::renderChoices(GdprModule::POLICY); ?>
    </section>
    <h2>Cookies necesarias y preferencias</h2>
    <div class="inventory" tabindex="0" role="region" aria-label="Inventario de cookies">
        <table>
            <thead><tr><th scope="col">Nombre</th><th scope="col">Finalidad</th><th scope="col">Duración</th></tr></thead>
            <tbody>
                <tr><td><code><?= $escape(session_name()) ?></code></td><td>Mantiene la sesión y protege los formularios.</td><td>Sesión del navegador</td></tr>
                <tr><td><code>goldensite_language</code></td><td>Recuerda el idioma cuando lo eliges.</td><td>365 días</td></tr>
                <tr><td><code>goldensite_gdpr_consent</code></td><td>Recuerda tu elección sobre cookies opcionales.</td><td>180 días</td></tr>
                <tr><td><code>goldensite_saml_flow</code></td><td>Vincula el inicio de sesión empresarial con el navegador si usas el acceso SAML.</td><td>Hasta 5 minutos</td></tr>
            </tbody>
        </table>
    </div>
    <p>Tu navegador también puede guardar los ajustes de accesibilidad y, si usas una tienda con carrito local, los productos del carrito. Esos datos se conservan hasta que los cambies o borres los datos del sitio.</p>
    <h2>Servicios opcionales</h2>
    <?php if ($providers !== ''): ?>
    <p><?= nl2br($escape($providers)) ?></p>
    <?php else: ?>
    <p>Las integraciones opcionales de medición y otros scripts configurados en el CMS se cargan después de aceptar. Sus proveedores pueden establecer sus propias cookies.</p>
    <?php endif; ?>
    <h2>Cambiar o retirar el consentimiento</h2>
    <p>Vuelve a esta página desde el pie del sitio y elige <strong>Usar solo necesarias</strong>. En las siguientes cargas se bloquean los servicios opcionales del CMS. Las cookies que un tercero ya guardó se pueden borrar desde los ajustes del navegador.</p>
    <h2>Responsable y contacto</h2>
    <p><strong><?= $escape($responsible) ?></strong><?php if ($address !== ''): ?><br><?= nl2br($escape($address)) ?><?php endif; ?></p>
    <p>Para consultas sobre tus preferencias, usa el <a href="/contacto">formulario de contacto</a><?php if (filter_var($email, FILTER_VALIDATE_EMAIL)): ?> o escribe a <a href="mailto:<?= $escape($email) ?>"><?= $escape($email) ?></a><?php endif; ?>.</p>
</main>
</body>
</html>
