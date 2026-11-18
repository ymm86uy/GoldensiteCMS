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

$activeFrontendModules = [];
try {
    $dbFrontend = \App\Core\Database::getInstance()->getConnection();
    $stmtFE = $dbFrontend->query("SELECT LOWER(module_id) as mod_id FROM goldensite_active_modules WHERE is_active = 1");
    $rowsFE = $stmtFE->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    foreach ($rowsFE as $r) {
        $activeFrontendModules[$r['mod_id']] = true;
    }
} catch (\Throwable $e) {}

$isFeActive = function($modKey) use ($activeFrontendModules) {
    $clean = strtolower($modKey);
    $alt = str_starts_with($clean, 'goldensite-') ? substr($clean, 7) : 'goldensite-' . $clean;
    return isset($activeFrontendModules[$clean]) || isset($activeFrontendModules[$alt]);
};
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars(\App\Core\Settings::get('site_language','es'), ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle ?? 'GoldenSiteCMS - Ecosistema Neón') ?></title>
    <style>
        :root {
            --primary: <?= $primaryColor ?>;
            --bg-color: <?= $bgColor ?>;
            --card-bg: <?= $cardBg ?>;
            --text-color: <?= $textColor ?>;
            --font-family: <?= $fontFamily ?>;
            --glow-color: <?= $glowColor ?>;
            --border-radius: 12px;
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
            background-image: 
                radial-gradient(circle at 20% 15%, var(--glow-color) 0%, transparent 45%),
                radial-gradient(circle at 80% 80%, rgba(0,0,0,0.8) 0%, transparent 50%);
            transition: background 0.4s ease, color 0.4s ease;
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
        .navbar .brand { 
            font-size: 1.3rem; 
            font-weight: 900; 
            color: #fff; 
            text-decoration: none; 
            letter-spacing: 0.05em;
        }
        .navbar .brand span {
            color: var(--primary);
            text-shadow: 0 0 10px var(--primary);
        }
        .navbar .nav-links { 
            display: flex; 
            gap: 1.5rem; 
            align-items: center; 
        }
        .nav-item { position: relative; }
        .nav-item a { 
            color: var(--text-color); 
            text-decoration: none; 
            font-size: 0.85rem; 
            font-weight: 700; 
            opacity: 0.85; 
            transition: all 0.2s; 
        }
        .nav-item a:hover { 
            opacity: 1; 
            color: var(--primary); 
            text-shadow: 0 0 8px var(--primary);
        }

        /* Submenús Multinivel */
        .dropdown-menu {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid var(--primary);
            box-shadow: 0 0 15px var(--glow-color);
            border-radius: 8px;
            padding: 0.5rem 0;
            min-width: 170px;
            z-index: 200;
        }
        .nav-item:hover .dropdown-menu { display: flex; flex-direction: column; }
        .dropdown-menu a {
            padding: 0.5rem 1rem;
            white-space: nowrap;
            font-size: 0.8rem !important;
        }

        /* Botones Estilizados Reutilizables */
        .btn-cta { 
            background: var(--primary); 
            color: #000; 
            padding: 0.55rem 1.25rem; 
            border-radius: var(--border-radius); 
            font-weight: 900; 
            text-decoration: none; 
            transition: all 0.3s; 
            box-shadow: 0 0 10px var(--glow-color);
        }
        .btn-cta:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 0 20px var(--primary);
        }
        .btn-outline { 
            border: 1px solid var(--primary); 
            color: var(--primary); 
            padding: 0.55rem 1.25rem; 
            border-radius: var(--border-radius); 
            font-weight: bold; 
            text-decoration: none; 
            transition: all 0.3s;
        }
        .btn-outline:hover { background: rgba(56, 189, 248, 0.1); }
        .btn-neon-glow { 
            background: transparent; 
            border: 2px solid var(--primary); 
            color: var(--primary); 
            box-shadow: 0 0 15px var(--primary); 
            padding: 0.6rem 1.5rem; 
            border-radius: 30px; 
            font-weight: 900; 
            text-decoration: none; 
        }

        .main-container { 
            flex: 1; 
            width: 100%; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 2.5rem 1.5rem; 
        }
        .footer { 
            background: var(--card-bg); 
            border-top: 1px solid var(--primary); 
            padding: 2rem; 
            text-align: center; 
            font-size: 0.85rem; 
            margin-top: auto; 
            box-shadow: 0 0 15px var(--glow-color);
        }
        
        .module-card, .hero-container {
            background: var(--card-bg) !important;
            border: 1px solid var(--primary) !important;
            box-shadow: 0 0 15px var(--glow-color);
        }
        .btn-hero-main {
            background: var(--primary) !important;
            color: #000 !important;
            box-shadow: 0 0 12px var(--glow-color);
        }
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

<?= \App\Core\FrontendRuntime::footer() ?>
</body>
</html>
