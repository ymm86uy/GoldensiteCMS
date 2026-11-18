<?php
if (session_status() === PHP_SESSION_NONE) session_start();

extract(\App\Core\ThemeService::context(), EXTR_OVERWRITE);
$siteTitle ??= \App\Core\Settings::get('site_title','GoldenSiteCMS');

$customNavItems = [];
try {
    $dbNav = \App\Core\Database::getInstance()->getConnection();
    $stmtNav = $dbNav->query("SELECT * FROM goldensite_menus WHERE is_active = 1 ORDER BY parent_id ASC, sort_order ASC, id ASC");
    $customNavItems = $stmtNav ? $stmtNav->fetchAll(\PDO::FETCH_ASSOC) : [];
} catch (\Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Core\Settings::get('site_language','es'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle ?? 'GoldenSiteCMS Engine') ?></title>
    <?php if (isset($forumAssets)) require $forumAssets; ?>
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --bg-color: <?= $bgColor ?>;
            --card-bg: <?= $cardBg ?>;
            --text-color: <?= $textColor ?>;
            --font-family: <?= $fontFamily ?>;
            --glow-color: <?= $glowColor ?>;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: var(--font-family); 
            background-color: var(--bg-color); 
            color: var(--text-color); 
            min-height: 100vh; 
            display: flex; 
            flex-direction: column; 
            line-height: 1.6;
        }
        .navbar { 
            background: rgba(15, 23, 42, 0.85); 
            backdrop-filter: blur(16px); 
            border-bottom: 1px solid var(--primary); 
            box-shadow: 0 0 15px var(--glow-color);
            position: sticky; 
            top: 0; 
            z-index: 100; 
            padding: 1rem 2rem; 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
        }
        .navbar .brand { font-size: 1.3rem; font-weight: 900; color: #fff; text-decoration: none; }
        .navbar .brand span { color: var(--primary); }
        .navbar .nav-links { display: flex; gap: 1.2rem; align-items: center; }
        .nav-item a { color: var(--text-color); text-decoration: none; font-size: 0.85rem; font-weight: 700; }
        .nav-item a:hover { color: var(--primary); }
        .btn-cta { background: var(--primary); color: #000; padding: 0.55rem 1.25rem; border-radius: 8px; font-weight: 900; text-decoration: none; }
        
        /* Buscador AJAX Predictivo */
        .search-box { position: relative; width: 220px; }
        .search-box input { width: 100%; background: #0f172a; border: 1px solid var(--primary); color: #fff; padding: 0.45rem 0.75rem; border-radius: 6px; font-size: 0.8rem; outline: none; }
        .search-results { position: absolute; top: 110%; left: 0; right: 0; background: #0f172a; border: 1px solid var(--primary); border-radius: 6px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); display: none; flex-direction: column; z-index: 200; max-height: 250px; overflow-y: auto; }
        .search-results a { padding: 0.6rem 0.8rem; color: #cbd5e1; text-decoration: none; font-size: 0.8rem; border-bottom: 1px solid #1e293b; display: flex; justify-content: space-between; align-items: center; }
        .search-results a:hover { background: #1e293b; color: var(--primary); }

        .main-container { flex: 1; width: 100%; max-width: 1200px; margin: 0 auto; padding: 2.5rem 1.5rem; }
        .footer { background: var(--card-bg); border-top: 1px solid var(--primary); padding: 2rem; text-align: center; font-size: 0.85rem; margin-top: auto; }
    </style>
<?= \App\Core\ThemeService::head() ?>
</head>
<body>

    <header class="navbar">
        <a href="/" class="brand"><?= htmlspecialchars(\App\Core\Settings::get('site_title','GoldenSiteCMS'), ENT_QUOTES, 'UTF-8') ?></a>
        <?= \App\Core\Navigation::render() ?>
    </header>

    <main class="main-container">
        <?= $content ?? '' ?>
    </main>

    <footer class="footer">
        <p>&copy; <?= date('Y') ?> GoldenSiteCMS — Todos los derechos reservados.</p>
    </footer>

    <script src="/assets/search.js?v=20261105" defer></script>

<?= \App\Core\FrontendRuntime::footer() ?>
</body>
</html>
