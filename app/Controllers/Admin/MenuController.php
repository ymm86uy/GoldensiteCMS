<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

class MenuController
{
    private static function initTable($db): void
    {
        try {
            $db->query("SELECT 1 FROM goldensite_menus LIMIT 1");
        } catch (\Throwable $e) {
            try {
                
                
                
                $db->exec("CREATE TABLE IF NOT EXISTS goldensite_menus (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(150) NOT NULL,
                    url VARCHAR(255) NOT NULL,
                    icon VARCHAR(255) DEFAULT '',
                    badge_text VARCHAR(50) DEFAULT '',
                    badge_color VARCHAR(20) DEFAULT '#38bdf8',
                    is_target_blank TINYINT(1) DEFAULT 0,
                    visibility VARCHAR(20) DEFAULT 'all',
                    parent_id INT DEFAULT 0,
                    sort_order INT DEFAULT 0,
                    is_active TINYINT(1) DEFAULT 1
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            } catch (\Throwable $ex) {}
        }

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_pages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(200) NOT NULL,
                slug VARCHAR(200) NOT NULL UNIQUE,
                content LONGTEXT NULL,
                meta_title VARCHAR(255) NULL,
                meta_description TEXT NULL,
                og_image VARCHAR(255) NULL,
                status VARCHAR(20) DEFAULT 'published',
                is_published TINYINT(1) DEFAULT 1,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $ex) {}

        try { $db->exec("ALTER TABLE goldensite_pages ADD COLUMN meta_title VARCHAR(255) NULL AFTER content;"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE goldensite_pages ADD COLUMN meta_description TEXT NULL AFTER meta_title;"); } catch (\Throwable $e) {}
        try { $db->exec("ALTER TABLE goldensite_pages ADD COLUMN og_image VARCHAR(255) NULL AFTER meta_description;"); } catch (\Throwable $e) {}
    }

    private static function getTemplateContent(string $template, string $title): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        if ($template === 'landing') {
            return "<div style='text-align: center; padding: 4rem 1rem;'>
                <h1 style='font-size: 2.8rem; font-weight: 900; color: #fff; margin-bottom: 1rem;'>Bienvenido a {$safeTitle}</h1>
                <p style='color: #94a3b8; font-size: 1.1rem; max-width: 600px; margin: 0 auto 2rem;'>Soluciones modernas y desarrollo de alto rendimiento impulsado por GoldenSiteCMS.</p>
                <a href='#contacto' class='btn-cta' style='display: inline-block; background: #38bdf8; color: #0f172a; padding: 0.8rem 2rem; border-radius: 8px; font-weight: bold; text-decoration: none;'>Empezar Ahora 🚀</a>
            </div>";
        } elseif ($template === 'services') {
            return "<div style='padding: 2rem 0;'>
                <h1 style='font-size: 2rem; font-weight: 900; color: #fff; margin-bottom: 1.5rem;'>Nuestros Servicios - {$safeTitle}</h1>
                <div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1.5rem;'>
                    <div style='background: #1e293b; border: 1px solid #334155; padding: 1.5rem; border-radius: 12px;'>
                        <h3 style='color: #38bdf8; margin-bottom: 0.5rem;'>⚡ Desarrollo Web</h3>
                        <p style='color: #94a3b8; font-size: 0.85rem;'>Arquitectura rápida, segura y escalable.</p>
                    </div>
                    <div style='background: #1e293b; border: 1px solid #334155; padding: 1.5rem; border-radius: 12px;'>
                        <h3 style='color: #a855f7; margin-bottom: 0.5rem;'>🛡️ Ciberseguridad</h3>
                        <p style='color: #94a3b8; font-size: 0.85rem;'>Protección contra vulnerabilidades e inyecciones.</p>
                    </div>
                </div>
            </div>";
        }

        return "<p>Bienvenido a la página de <strong>{$safeTitle}</strong> creada automáticamente en GoldenSiteCMS.</p>";
    }

    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();
        self::initTable($db);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['menu_msg'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/menus');
                exit;
            }

            $action = $_POST['action'] ?? 'add';

            if ($action === 'add') {
                $title       = htmlspecialchars(trim($_POST['title'] ?? ''), ENT_QUOTES, 'UTF-8');
                $rawUrl      = trim($_POST['url'] ?? '/');
                $icon        = htmlspecialchars(trim($_POST['icon'] ?? ''), ENT_QUOTES, 'UTF-8');
                $badgeText   = htmlspecialchars(trim($_POST['badge_text'] ?? ''), ENT_QUOTES, 'UTF-8');
                $badgeColor  = preg_match('/^#[a-fA-F0-9]{3,6}$/', $_POST['badge_color'] ?? '') ? $_POST['badge_color'] : '#38bdf8';
                $targetBlank = isset($_POST['is_target_blank']) ? 1 : 0;
                $visibility  = in_array($_POST['visibility'] ?? '', ['all', 'logged_in', 'admin']) ? $_POST['visibility'] : 'all';
                $parent      = (int)($_POST['parent_id'] ?? 0);
                $order       = (int)($_POST['sort_order'] ?? 0);
                $autoPage    = isset($_POST['create_auto_page']);
                $template    = trim($_POST['page_template'] ?? 'default');

                $parsedPath = parse_url($rawUrl, PHP_URL_PATH);
                $url = !empty($parsedPath) ? $parsedPath : $rawUrl;
                if (!str_starts_with($url, '/') && !str_starts_with($url, 'http://') && !str_starts_with($url, 'https://')) {
                    $url = '/' . $url;
                }

                if (isset($_FILES['icon_file']) && $_FILES['icon_file']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../../../public/uploads/menu_icons/';
                    if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

                    $ext = strtolower(pathinfo($_FILES['icon_file']['name'], PATHINFO_EXTENSION));
                    $allowed = ['jpg', 'jpeg', 'png', 'webp', 'svg', 'gif'];
                    if (in_array($ext, $allowed)) {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $_FILES['icon_file']['tmp_name']);
                        finfo_close($finfo);

                        if (str_starts_with($mime, 'image/')) {
                            $fileName = 'icon_' . bin2hex(random_bytes(8)) . '.' . $ext;
                            if (\App\Core\UploadGuard::move($_FILES['icon_file']['tmp_name'], $uploadDir . $fileName)) {
                                $icon = '/uploads/menu_icons/' . $fileName;
                            }
                        }
                    }
                }

                if (!empty($title)) {
                    if ($autoPage && str_starts_with($url, '/')) {
                        $cleanSlug = ltrim($url, '/');
                        $cleanSlug = preg_replace('/^page\//', '', $cleanSlug);
                        $slug = strtolower(preg_replace('/[^a-zA-Z0-9\-_]/', '', $cleanSlug));

                        if (!empty($slug)) {
                            try {
                                $content = self::getTemplateContent($template, $title);
                                $stmtP = $db->prepare("INSERT INTO goldensite_pages (title, slug, content, meta_title) VALUES (:t, :s, :c, :mt) ON DUPLICATE KEY UPDATE title = :t2, content = :c2");
                                $stmtP->execute([
                                    't'   => $title,
                                    's'   => $slug,
                                    'c'   => $content,
                                    'mt'  => $title . " - GoldenSiteCMS",
                                    't2'  => $title,
                                    'c2'  => $content
                                ]);
                            } catch (\Throwable $e) {}
                        }
                    }

                    $stmt = $db->prepare("INSERT INTO goldensite_menus (title, url, icon, badge_text, badge_color, is_target_blank, visibility, parent_id, sort_order) VALUES (:title, :url, :icon, :bt, :bc, :tb, :vis, :parent, :order)");
                    $stmt->execute([
                        'title'  => $title,
                        'url'    => $url,
                        'icon'   => $icon,
                        'bt'     => $badgeText,
                        'bc'     => $badgeColor,
                        'tb'     => $targetBlank,
                        'vis'    => $visibility,
                        'parent' => $parent,
                        'order'  => $order
                    ]);
                    $_SESSION['menu_msg'] = ['type' => 'success', 'msg' => "Página y menú '{$title}' creados con éxito."];
                }
            } elseif ($action === 'delete') {
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $stmt = $db->prepare("DELETE FROM goldensite_menus WHERE id = :id OR parent_id = :parent_id");
                    $stmt->execute(['id' => $id, 'parent_id' => $id]);
                    $_SESSION['menu_msg'] = ['type' => 'success', 'msg' => "Elemento de menú eliminado."];
                }
            }

            header('Location: /admin/menus');
            exit;
        }

        $items = [];
        try {
            $items = $db->query("SELECT * FROM goldensite_menus ORDER BY parent_id ASC, sort_order ASC, id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}

        $msg = $_SESSION['menu_msg'] ?? null;
        unset($_SESSION['menu_msg']);

        $csrfToken = Csrf::generate();
        $title     = "Gestión de Menús Avanzado - GoldenSiteCMS";

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0; color: #fff;">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.8rem; font-weight: bold; margin-bottom: 0.25rem;">🗺️ Gestor de Menús, Plantillas & SEO</h1>
                <p style="color: #94a3b8; font-size: 0.9rem;">Crea menús y genera páginas con estructuras pre-diseñadas.</p>
            </div>

            <?php if ($msg): ?>
                <div style="background: <?= $msg['type'] === 'success' ? '#15803d' : '#b91c1c' ?>; color: #fff; padding: 0.85rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold; font-size: 0.85rem;">
                    <?= $msg['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($msg['msg']) ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: #38bdf8; font-size: 1.1rem; font-weight: bold; margin-bottom: 1.25rem; border-bottom: 1px solid #334155; padding-bottom: 0.5rem;">
                        ➕ Agregar Elemento / Submenú
                    </h3>

                    <form action="/admin/menus" method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <input type="hidden" name="action" value="add">

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; margin-bottom: 0.4rem;">Título del Menú</label>
                            <input type="text" id="input_title" name="title" placeholder="Ej. Servicios" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 6px; font-size: 0.85rem;">
                        </div>

                        <div style="background: #0f172a; padding: 0.85rem; border-radius: 8px; border: 1px solid #334155; margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.8rem; color: #38bdf8; margin-bottom: 0.4rem;">🎨 Icono / Imagen del Menú</label>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem;">
                                <input type="text" id="input_icon" name="icon" placeholder="Ej. 🚀" style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #fff; padding: 0.45rem; border-radius: 6px; font-size: 0.85rem; text-align: center;">
                                <input type="file" name="icon_file" accept="image/*" style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #94a3b8; padding: 0.35rem; border-radius: 6px; font-size: 0.72rem;">
                            </div>
                        </div>

                        <div style="margin-bottom: 1rem;">
                            <label style="display: block; font-weight: bold; font-size: 0.85rem; margin-bottom: 0.4rem;">URL / Ruta de Destino</label>
                            <input type="text" id="input_url" name="url" placeholder="Ej. /servicios" required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.6rem; border-radius: 6px; font-size: 0.85rem;">
                        </div>

                        <div style="margin-bottom: 1rem; background: #0f172a; padding: 0.75rem; border-radius: 6px; border: 1px dashed #38bdf8;">
                            <label style="display: flex; align-items: center; gap: 0.5rem; font-size: 0.82rem; font-weight: bold; color: #38bdf8; cursor: pointer; margin-bottom: 0.5rem;">
                                <input type="checkbox" name="create_auto_page" value="1" checked>
                                ⚡ Crear automáticamente la página en GoldenSiteCMS
                            </label>
                            <label style="display: block; font-size: 0.75rem; color: #cbd5e1; margin-bottom: 0.25rem;">Plantilla de Diseño:</label>
                            <select name="page_template" style="width: 100%; background: #1e293b; border: 1px solid #334155; color: #fff; padding: 0.45rem; border-radius: 6px; font-size: 0.8rem;">
                                <option value="default">📄 Texto Estándar</option>
                                <option value="landing">🚀 Landing Page Hero & CTA</option>
                                <option value="services">💼 Grid de Servicios & Tarjetas</option>
                            </select>
                        </div>

                        <button type="submit" style="width: 100%; background: #22c55e; color: #0f172a; font-weight: bold; border: none; padding: 0.7rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">
                            💾 Guardar Elemento de Menú
                        </button>
                    </form>
                </div>

                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: #a855f7; font-size: 1.1rem; font-weight: bold; margin-bottom: 1.25rem; border-bottom: 1px solid #334155; padding-bottom: 0.5rem;">
                        👁️ Vista Previa de la Barra Pública
                    </h3>
                    <div style="background: #0f172a; border: 1px solid #38bdf8; border-radius: 8px; padding: 0.85rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-weight: 900; color: #fff; font-size: 0.85rem;">🚀 GOLDENSITE<span style="color: #38bdf8;">.CMS</span></span>
                        <div style="display: flex; gap: 0.85rem; align-items: center;">
                            <span style="color: #38bdf8; font-weight: bold; font-size: 0.75rem;">Inicio</span>
                            <?php foreach ($items as $item): if ($item['parent_id'] == 0): ?>
                                <span style="color: #94a3b8; font-size: 0.75rem; font-weight: 600;"><?= htmlspecialchars($item['title']) ?></span>
                            <?php endif; endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}