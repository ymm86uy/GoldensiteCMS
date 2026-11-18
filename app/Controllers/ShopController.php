<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\FrontendEngine;

class ShopController
{
    /**
     * Valida la licencia PRO del módulo Goldensite Commerce & Sales Engine
     */
    public static function isSalesLicenseValid(): bool
    {
        return \App\Core\FrontendEngine::isModuleActive('commerce-sales');
    }

    /**
     * Muestra el catálogo público de productos si la licencia PRO está activa
     */
    public function index(): void
    {
        // Verificar si el sitio tiene licencia activa para Goldensite Commerce & Sales Engine
        if (!self::isSalesLicenseValid()) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Módulo E-Commerce PRO Requerido</title></head>';
            echo '<body style="background:#0f172a; color:#f59e0b; font-family:sans-serif; text-align:center; padding:5rem 1rem;">';
            echo '<div style="background:#1e293b; border:2px solid #f59e0b; border-radius:12px; max-width:550px; margin:0 auto; padding:2.5rem;">';
            echo '<h1 style="font-size:2rem; margin-bottom:1rem; color:#fff;">🔒 Tienda E-Commerce PRO Bloqueada</h1>';
            echo '<p style="color:#cbd5e1; font-size:1rem; line-height:1.6; margin-bottom:1.5rem;">El módulo <strong>Goldensite Commerce & Sales Engine</strong> requiere una suscripción activa con el precio vigente del catálogo para habilitar el catálogo público y el procesamiento de ventas.</p>';
            echo '<a href="/admin/plugins" style="background:#f59e0b; color:#0f172a; padding:0.6rem 1.2rem; border-radius:6px; font-weight:bold; text-decoration:none; display:inline-block;">Ingresar Licencia Sales Engine</a>';
            echo '</div></body></html>';
            exit;
        }

        $db = Database::getInstance()->getConnection();
        $siteSettings = FrontendEngine::getSettings();
        $activeTheme  = $siteSettings['active_theme'] ?? 'default';

        $products = [];
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(150) NOT NULL,
                price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                description TEXT,
                image_url VARCHAR(255),
                status VARCHAR(20) DEFAULT 'active',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmt = $db->query("SELECT * FROM goldensite_products WHERE status = 'active' ORDER BY id DESC");
            if ($stmt) {
                $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        $siteTitle = "Tienda & Catálogo - " . ($siteSettings['site_title'] ?? 'GoldenSiteCMS');

        ob_start();
        ?>
        <div class="max-w-7xl mx-auto px-6 py-12 space-y-8">
            <div class="border-b border-slate-800/80 pb-6 flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-black text-white tracking-tight flex items-center gap-3">
                        <span class="text-amber-400">🛍️</span> Catálogo de Productos
                    </h1>
                    <p class="text-slate-400 text-sm mt-1">Explora nuestros productos procesados por Goldensite Commerce & Sales Engine.</p>
                </div>
            </div>

            <?php if (empty($products)): ?>
                <div class="glass-panel p-12 rounded-2xl text-center text-slate-500">
                    <p class="text-lg">No hay productos disponibles en la tienda por el momento.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <?php foreach ($products as $prod): ?>
                        <div class="glass-panel rounded-2xl p-5 border border-slate-800 flex flex-col justify-between hover:border-amber-500/40 transition-all">
                            <div class="space-y-4">
                                <?php if (!empty($prod['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($prod['image_url']) ?>" class="w-full h-48 object-cover rounded-xl border border-slate-800">
                                <?php else: ?>
                                    <div class="w-full h-48 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center text-slate-600 font-bold">Sin Imagen</div>
                                <?php endif; ?>
                                <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($prod['name']) ?></h3>
                                <p class="text-slate-400 text-xs line-clamp-2"><?= htmlspecialchars($prod['description'] ?? '') ?></p>
                            </div>

                            <div class="pt-6 mt-6 border-t border-slate-800/80 flex items-center justify-between">
                                <span class="text-xl font-black text-amber-400">$<?= number_format((float)$prod['price'], 2) ?></span>
                                <button onclick="addToCart(<?= (int)$prod['id'] ?>, '<?= htmlspecialchars($prod['name'], ENT_QUOTES) ?>', <?= (float)$prod['price'] ?>)" class="bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl uppercase tracking-wider transition-all">
                                    Añadir 🛒
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script>
            function addToCart(id, name, price) {
                let cart = JSON.parse(localStorage.getItem('goldensite_cart') || '[]');
                cart.push({ id, name, price });
                localStorage.setItem('goldensite_cart', JSON.stringify(cart));
                alert(`🛒 ${name} ha sido añadido al carrito.`);
            }
        </script>
        <?php
        $content = ob_get_clean();

        $themeLayoutPath = __DIR__ . "/../../views/themes/{$activeTheme}/layout.php";
        if (file_exists($themeLayoutPath)) {
            require_once $themeLayoutPath;
        } else {
            require_once __DIR__ . '/../../views/layouts/main.php';
        }
    }
}