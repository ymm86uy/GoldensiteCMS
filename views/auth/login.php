<?php
if (session_status() === PHP_SESSION_NONE) session_start();
use App\Core\Csrf;
use App\Core\Database;
use App\Core\LoginAppearance;

$csrfToken = Csrf::generate();
$isPreview = isset($loginPreview);
$registrationPage = $registrationPage ?? false;

$style = LoginAppearance::defaultStyle();
$bgImage = 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?auto=format&fit=crop&w=1920&q=80';
$logoUrl = '';
$companyName = 'GoldenSiteCMS';
$companyTagline = 'Acceso al Sistema';
$accentColor = '';
$customBackground = false;

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->query("SELECT * FROM goldensite_login_studio WHERE id = 1 LIMIT 1");
    if ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        $style = $row['active_style'] ?? LoginAppearance::defaultStyle();
        if (!empty($row['bg_image_url'])) { $bgImage = $row['bg_image_url']; $customBackground = true; }
        if (!empty($row['company_logo_url'])) $logoUrl = $row['company_logo_url'];
        if (!empty($row['company_name'])) $companyName = $row['company_name'];
        if (!empty($row['company_tagline'])) $companyTagline = $row['company_tagline'];
        if (!empty($row['accent_color'])) $accentColor = $row['accent_color'];
    }
} catch (\Throwable $e) {}
if (!LoginAppearance::available($style)) $style = LoginAppearance::defaultStyle();
if (!\App\Core\LoginAppearance::media($bgImage)) $bgImage = '';
if (!\App\Core\LoginAppearance::media($logoUrl)) $logoUrl = '';
if (!$isPreview && !\App\Core\FrontendEngine::isModuleActive('login-security')) {
    if (!in_array($style, LoginAppearance::FREE_STYLES, true)) $style = LoginAppearance::defaultStyle();
    $companyName = 'GoldenSiteCMS'; $companyTagline = 'Acceso al Sistema'; $logoUrl = ''; $accentColor = '';
    $customBackground = false;
}
if ($isPreview) $style = $loginPreview;
$motion = LoginAppearance::MOTION[$style] ?? null;
if (!preg_match('/^#[a-f0-9]{6}$/iD', $accentColor)) $accentColor = $motion[2] ?? '#38bdf8';
if ($motion !== null && !$customBackground) $bgImage = '';
$samlAvailable = !$registrationPage && !$isPreview && \App\Core\EnterpriseSaml::ready();
?>
<!DOCTYPE html>
<html lang="<?= $registrationPage ? \App\Core\Locale::language() : 'es' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($companyName) ?><?= $registrationPage ? ' · '.$registrationTitle : ' - Iniciar Sesión' ?></title>
    <?php if ($motion !== null): ?><link rel="stylesheet" href="/assets/goldensite-login-motion.css"><?php endif; ?>
    <?php if ($registrationPage): ?><link rel="stylesheet" href="/assets/goldensite-registration.css"><?php endif; ?>
    <style>
        :root {
            --accent: <?= $accentColor ?>;
            --bg-img: url(<?= json_encode($bgImage, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES) ?>);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh; display: flex; align-items: center; justify-content: center;
            background: #090d16; color: #fff; padding: 1.5rem;
        }

        <?php if ($style === 'glass_neon'): ?>
        body { background: radial-gradient(circle at center, #1e1b4b 0%, #090d16 100%); }
        .login-card {
            width: 100%; max-width: 420px; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(16px);
            border: 1px solid var(--accent); box-shadow: 0 0 30px rgba(56, 189, 248, 0.25); border-radius: 1.5rem; padding: 2.5rem; text-align: center;
        }
        <?php endif; ?>

        <?php if ($style === 'split_national'): ?>
        body { background: #070913; }
        .login-card {
            width: 100%; max-width: 950px; background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 1.5rem;
            display: grid; grid-template-columns: 1.1fr 1fr; overflow: hidden;
        }
        .card-banner { background: var(--bg-img) center/cover no-repeat; min-height: 450px; }
        .card-form { padding: 3rem; display: flex; flex-direction: column; justify-content: center; }
        <?php endif; ?>

        <?php if ($style === 'nature_mist'): ?>
        body { background: var(--bg-img) center/cover fixed; }
        .login-card { width: 100%; max-width: 440px; background: rgba(255, 255, 255, 0.95); color: #0f172a; border-radius: 1.5rem; overflow: hidden; }
        .nature-header { background: #047857; padding: 2.5rem 1.5rem; text-align: center; color: #fff; }
        .nature-body { padding: 2.5rem; }
        .nature-body label { color: #334155 !important; }
        .nature-body input { background: #f1f5f9 !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; }
        <?php endif; ?>

        <?php if ($style === 'cyberpunk_glow'): ?>
        body { background: #05050d; }
        .login-card { width: 100%; max-width: 420px; background: #0d0d1a; border: 2px solid var(--accent); box-shadow: 0 0 25px var(--accent); border-radius: 1rem; padding: 2.5rem; text-align: center; }
        <?php endif; ?>

        <?php if ($style === 'minimal_clean'): ?>
        body { background: #f8fafc; color: #0f172a; }
        .login-card { width: 100%; max-width: 400px; background: #ffffff; border: 1px solid #e2e8f0; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); border-radius: 1rem; padding: 2.5rem; }
        .login-card label { color: #475569 !important; }
        .login-card input { background: #f8fafc !important; color: #0f172a !important; border: 1px solid #cbd5e1 !important; }
        <?php endif; ?>

        <?php if ($style === 'dark_wallet'): ?>
        body { background: var(--bg-img) center/cover fixed; }
        .login-card { width: 100%; max-width: 420px; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(20px); border: 1px solid rgba(255, 255, 255, 0.15); border-radius: 2rem; padding: 2.5rem; text-align: center; }
        <?php endif; ?>

        <?php if ($style === 'gradient_sunset'): ?>
        body { background: linear-gradient(135deg, #4c1d95 0%, #c026d3 50%, #f97316 100%); }
        .login-card { width: 100%; max-width: 420px; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(16px); border-radius: 1.5rem; padding: 2.5rem; text-align: center; }
        <?php endif; ?>

        <?php if ($style === 'auth_centered_badge'): ?>
        body { background: #0f172a; }
        .login-card { width: 100%; max-width: 440px; background: #1e293b; border-radius: 1.5rem; padding: 3rem 2.5rem 2.5rem; position: relative; text-align: center; margin-top: 40px; }
        .top-badge { width: 80px; height: 80px; background: var(--accent); border-radius: 50%; position: absolute; top: -40px; left: 50%; transform: translateX(-50%); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: #000; }
        <?php endif; ?>

        .input-group { margin-bottom: 1.25rem; text-align: left; }
        .input-group label { display: block; font-size: 0.8rem; font-weight: 700; margin-bottom: 0.4rem; text-transform: uppercase; letter-spacing: 0.05em; }
        .input-group input { width: 100%; background: #0f172a; border: 1px solid #334155; padding: 0.85rem 1rem; border-radius: 0.75rem; color: #fff; font-size: 0.95rem; outline: none; transition: all 0.2s; }
        .input-group input:focus { border-color: var(--accent); }
        .btn-submit { width: 100%; background: var(--accent); color: #000; font-weight: 900; padding: 0.9rem; border: none; border-radius: 0.75rem; font-size: 0.95rem; cursor: pointer; transition: all 0.2s; margin-top: 0.5rem; }
        .btn-submit:hover { transform: translateY(-2px); filter: brightness(1.1); }
        .login-preview { position: fixed; inset: .6rem auto auto .6rem; z-index: 5; background: #121827; color: #fff; padding: .6rem .8rem; border-radius: .5rem; }
        fieldset.login-fields { border: 0; min-width: 0; }
        .gs-register-link { margin-top: 1.15rem; font-size: .9rem; }
        .gs-register-link a { color: inherit; text-decoration: underline; text-underline-offset: .18em; font-weight: 700; }
        @media (max-width: 660px) { .card-banner { display:none; } .login-card { grid-template-columns:1fr; } .card-form { padding:1.5rem; } }
        @media (prefers-reduced-motion: reduce) { .btn-submit, .input-group input { transition:none; } .btn-submit:hover { transform:none; } }
    </style>
</head>
<body<?= $motion !== null ? ' class="gs-motion gs-motion--'.htmlspecialchars($style, ENT_QUOTES, 'UTF-8').'"' : '' ?>>
    <?php if ($isPreview): ?><p class="login-preview" role="status">Vista previa · Inicio de sesión desactivado</p><?php endif; ?>

    <?php if ($motion !== null): ?>
        <?php require __DIR__.'/partials/login_motion.php'; ?>
    <?php elseif ($style === 'split_national'): ?>
    <div class="login-card">
        <div class="card-banner"></div>
        <div class="card-form">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="max-height: 50px; margin-bottom: 1rem; align-self: flex-start;">
            <?php endif; ?>
            <h2 style="font-size: 1.75rem; font-weight: 900; margin-bottom: 0.25rem;"><?= htmlspecialchars($registrationPage ? $registrationTitle : $companyName) ?></h2>
            <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 2rem;"><?= htmlspecialchars($registrationPage ? $registrationIntro : $companyTagline) ?></p>

            <?php if ($registrationPage): require __DIR__.'/partials/register_form.php'; else: ?>
            <form action="/login" method="POST"><fieldset class="login-fields"<?= $isPreview ? ' disabled' : '' ?>><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <?php require __DIR__ . '/partials/login_error.php'; ?>
                
                <div class="input-group">
                    <label>Correo o Usuario</label>
                    <input type="text" name="email" required placeholder="admin@goldensite.local" autofocus>
                </div>
                <div class="input-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-submit">Acceder al Sistema</button>
            </fieldset></form>
            <?php require __DIR__.'/partials/register_link.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($style === 'nature_mist'): ?>
    <div class="login-card">
        <div class="nature-header">
            <?php if (!empty($logoUrl)): ?><img src="<?= htmlspecialchars($logoUrl) ?>" style="max-height: 45px; margin-bottom: 0.5rem;"><?php endif; ?>
            <h2 style="font-size: 1.5rem; font-weight: 900;"><?= htmlspecialchars($registrationPage ? $registrationTitle : $companyName) ?></h2>
            <p style="font-size: 0.8rem; opacity: 0.9;"><?= htmlspecialchars($registrationPage ? $registrationIntro : $companyTagline) ?></p>
        </div>
        <div class="nature-body">
            <?php if ($registrationPage): require __DIR__.'/partials/register_form.php'; else: ?>
            <form action="/login" method="POST"><fieldset class="login-fields"<?= $isPreview ? ' disabled' : '' ?>><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <?php require __DIR__ . '/partials/login_error.php'; ?>
                
                <div class="input-group">
                    <label>Usuario</label>
                    <input type="text" name="email" required placeholder="Usuario o Email">
                </div>
                <div class="input-group">
                    <label>Contraseña</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-submit" style="background: #047857; color: #fff;">Ingresar</button>
            </fieldset></form>
            <?php require __DIR__.'/partials/register_link.php'; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php else: ?>
    <div class="login-card">
        <?php if ($style === 'auth_centered_badge'): ?>
            <div class="top-badge">👤</div>
        <?php endif; ?>

        <?php if (!empty($logoUrl)): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="max-height: 50px; margin-bottom: 1rem;">
        <?php endif; ?>

        <h2 style="font-size: 1.5rem; font-weight: 900; margin-bottom: 0.25rem;"><?= htmlspecialchars($registrationPage ? $registrationTitle : $companyName) ?></h2>
        <p style="font-size: 0.85rem; opacity: 0.8; margin-bottom: 1.75rem;"><?= htmlspecialchars($registrationPage ? $registrationIntro : $companyTagline) ?></p>

        <?php if ($registrationPage): require __DIR__.'/partials/register_form.php'; else: ?>
            <form action="/login" method="POST"><fieldset class="login-fields"<?= $isPreview ? ' disabled' : '' ?>><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <?php require __DIR__ . '/partials/login_error.php'; ?>
            
            <div class="input-group">
                <label>Email / Usuario</label>
                <input type="text" name="email" required placeholder="admin@goldensite.local">
            </div>
            <div class="input-group">
                <label>Contraseña</label>
                <input type="password" name="password" required placeholder="••••••••">
            </div>
            <button type="submit" class="btn-submit">LOGIN</button>
        </fieldset></form>
        <?php require __DIR__.'/partials/register_link.php'; ?>
            <?php endif; ?>
    </div>
    <?php endif; ?>

<?php if ($motion === null && $samlAvailable): ?>
<p style="text-align:center;padding:16px"><a href="/login/saml" style="color:inherit">Entrar con SAML corporativo</a></p>
<?php endif; ?>
</body>
</html>
