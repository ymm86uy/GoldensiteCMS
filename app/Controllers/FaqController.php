<?php

namespace App\Controllers;

use App\Core\FrontendEngine;

class FaqController
{
    public function index(): void
    {
        if (session_status() === PHP_SESSION_NONE) session_start();

        $siteSettings = FrontendEngine::getSettings();
        $activeTheme  = $siteSettings['active_theme'] ?? 'default';
        $siteTitle    = "Preguntas Frecuentes - " . ($siteSettings['site_title'] ?? 'GoldenSiteCMS');

        $faqs = [
            [
                'q' => '¿Cómo activo una licencia de módulo PRO en mi sitio?',
                'a' => 'Puedes ingresar la clave de activación provista en tu compra desde el Panel de Administración en la sección <strong>🔌 Plugins y Ext.</strong>.'
            ],
            [
                'q' => '¿Qué métodos de pago son aceptados en la tienda?',
                'a' => 'Consulta a la administración los métodos habilitados para tu compra. Una solicitud de compra no acredita pagos ni activa licencias por sí sola.'
            ],
            [
                'q' => '¿Cómo accedo a mis contenidos VIP y descargas?',
                'a' => 'Al completar tu suscripción o compra, puedes consultar tu historial y enlaces de descarga directamente desde la sección <strong>Mi Cuenta</strong>.'
            ],
            [
                'q' => '¿El sistema de seguridad WAF bloquea intentos maliciosos?',
                'a' => 'Goldensite analiza las solicitudes que llegan al CMS y aplica sus reglas y límites. La protección del servidor y del tráfico de gran volumen requiere medidas adicionales en el VPS o proveedor.'
            ]
        ];

        ob_start();
        ?>
        <div class="max-w-4xl mx-auto px-6 py-12 space-y-8">
            <div class="border-b border-slate-800/80 pb-6 text-center space-y-2">
                <span class="text-xs font-mono text-sky-400 uppercase tracking-wider">Centro de Ayuda</span>
                <h1 class="text-3xl font-black text-white tracking-tight">Preguntas Frecuentes (FAQ)</h1>
                <p class="text-slate-400 text-sm">Respuestas rápidas sobre licencias, pagos y gestión de cuentas.</p>
            </div>

            <div class="space-y-4">
                <?php foreach ($faqs as $item): ?>
                    <details class="glass-panel p-5 rounded-2xl border border-slate-800 group cursor-pointer transition-all">
                        <summary class="text-white font-bold text-sm flex justify-between items-center list-none select-none group-hover:text-sky-400">
                            <span>❓ <?= htmlspecialchars($item['q']) ?></span>
                            <span class="text-sky-400 font-mono text-lg transition-transform group-open:rotate-45">+</span>
                        </summary>
                        <p class="text-slate-400 text-xs leading-relaxed mt-3 pt-3 border-t border-slate-800/80">
                            <?= $item['a'] ?>
                        </p>
                    </details>
                <?php endforeach; ?>
            </div>
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