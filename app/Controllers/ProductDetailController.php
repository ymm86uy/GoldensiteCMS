<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\FrontendEngine;
use App\Controllers\ShopController;

class ProductDetailController
{
    /**
     * Muestra la vista detallada de un producto por ID
     */
    public function show(string $id): void
    {
        if (!ShopController::isSalesLicenseValid()) {
            http_response_code(403);
            echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Módulo E-Commerce PRO Requerido</title></head>';
            echo '<body style="background:#0f172a; color:#f59e0b; font-family:sans-serif; text-align:center; padding:5rem 1rem;">';
            echo '<div style="background:#1e293b; border:2px solid #f59e0b; border-radius:12px; max-width:550px; margin:0 auto; padding:2.5rem;">';
            echo '<h1 style="font-size:2rem; margin-bottom:1rem; color:#fff;">🔒 Ficha de Producto PRO Bloqueada</h1>';
            echo '<p style="color:#cbd5e1; font-size:1rem; line-height:1.6; margin-bottom:1.5rem;">El catálogo detallado de productos requiere una suscripción activa al módulo <strong>Goldensite Commerce & Sales Engine</strong>.</p>';
            echo '<a href="/admin/plugins" style="background:#f59e0b; color:#0f172a; padding:0.6rem 1.2rem; border-radius:6px; font-weight:bold; text-decoration:none; display:inline-block;">Activar Licencia Sales Engine</a>';
            echo '</div></body></html>';
            exit;
        }

        $productId = (int)$id;
        $db = Database::getInstance()->getConnection();
        $siteSettings = FrontendEngine::getSettings();
        $activeTheme  = $siteSettings['active_theme'] ?? 'default';

        $product = null;
        $relatedProducts = [];

        try {
            $stmt = $db->prepare("SELECT * FROM goldensite_products WHERE id = :id AND status = 'active' LIMIT 1");
            $stmt->execute(['id' => $productId]);
            $product = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($product) {
                $relStmt = $db->prepare("SELECT * FROM goldensite_products WHERE status = 'active' AND id != :id ORDER BY RAND() LIMIT 3");
                $relStmt->execute(['id' => $productId]);
                $relatedProducts = $relStmt->fetchAll(\PDO::FETCH_ASSOC);
            }
        } catch (\Throwable $e) {}

        if (!$product) {
            http_response_code(404);
            echo "Producto no encontrado.";
            exit;
        }

        $siteTitle = htmlspecialchars($product['name']) . " - " . ($siteSettings['site_title'] ?? 'GoldenSiteCMS');

        ob_start();
        ?>
        <div class="max-w-6xl mx-auto px-6 py-12 space-y-12">
            <!-- Migas de pan / Navegación -->
            <div class="text-xs text-slate-400 flex items-center gap-2">
                <a href="/" class="hover:text-sky-400">Inicio</a>
                <span>&rsaquo;</span>
                <a href="/tienda" class="hover:text-sky-400">Tienda</a>
                <span>&rsaquo;</span>
                <span class="text-white font-semibold"><?= htmlspecialchars($product['name']) ?></span>
            </div>

            <!-- Ficha del Producto -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                <!-- Imagen -->
                <div class="glass-panel p-4 rounded-2xl border border-slate-800">
                    <?php if (!empty($product['image_url'])): ?>
                        <img src="<?= htmlspecialchars($product['image_url']) ?>" class="w-full h-96 object-cover rounded-xl border border-slate-800/80 shadow-2xl">
                    <?php else: ?>
                        <div class="w-full h-96 bg-slate-900 rounded-xl border border-slate-800 flex items-center justify-center text-slate-600 font-bold text-lg">
                            Sin Imagen Disponible
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Detalle y Compra -->
                <div class="space-y-6 flex flex-col justify-between">
                    <div class="space-y-4">
                        <span class="px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-bold uppercase tracking-wider">
                            Garantía Oficial Goldensite Commerce
                        </span>
                        <h1 class="text-3xl font-black text-white tracking-tight"><?= htmlspecialchars($product['name']) ?></h1>
                        <div class="text-3xl font-black text-amber-400">$<?= number_format((float)$product['price'], 2) ?></div>
                        
                        <p class="text-slate-300 text-sm leading-relaxed border-t border-b border-slate-800/80 py-4">
                            <?= nl2br(htmlspecialchars($product['description'] ?? 'Sin descripción adicional para este producto.')) ?>
                        </p>
                    </div>

                    <!-- Controles de Carrito -->
                    <div class="space-y-4 pt-4">
                        <div class="flex items-center gap-4">
                            <label class="text-xs font-bold text-slate-400 uppercase">Cantidad:</label>
                            <input type="number" id="prodQty" value="1" min="1" max="99" class="w-20 bg-slate-900 border border-slate-700 text-white text-center text-sm rounded-xl py-2 focus:outline-none focus:border-amber-400 font-bold">
                        </div>

                        <form action="/cart/add" method="POST" class="flex gap-4">
                            <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                            <input type="hidden" name="quantity" id="formQty" value="1">
                            <button type="submit" onclick="document.getElementById('formQty').value = document.getElementById('prodQty').value;" class="w-full bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs py-4 rounded-xl uppercase tracking-wider shadow-lg transition-all">
                                Añadir al Carrito 🛒
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Productos Relacionados -->
            <?php if (!empty($relatedProducts)): ?>
                <div class="pt-12 border-t border-slate-800/80 space-y-6">
                    <h3 class="text-xl font-bold text-white">También te podría interesar</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <?php foreach ($relatedProducts as $rel): ?>
                            <a href="/producto/<?= (int)$rel['id'] ?>" class="glass-panel p-4 rounded-xl border border-slate-800 hover:border-amber-500/40 transition-all group block">
                                <h4 class="text-white font-bold text-sm group-hover:text-amber-400 transition-colors"><?= htmlspecialchars($rel['name']) ?></h4>
                                <span class="text-amber-400 text-xs font-bold">$<?= number_format((float)$rel['price'], 2) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
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