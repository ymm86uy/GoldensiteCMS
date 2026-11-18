<?php

namespace App\Controllers\Admin;

use App\Core\Auth;

class QrGeneratorController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        
        $title = "Goldensite Basic QR Generator (FREE)";
        $defaultUrl = ($_SERVER['REQUEST_SCHEME'] ?? 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0;">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; display: flex; align-items: center; gap: 0.75rem;">
                    📱 Goldensite Basic QR Generator
                    <span style="background: #38bdf8; color: #0f172a; font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: bold;">CORE FREE</span>
                </h1>
                <p style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Generación nativa, instantánea y 100% offline sin dependencias en el servidor.</p>
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                <!-- Panel de Configuración -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: #38bdf8; font-size: 1.1rem; font-weight: bold; margin-bottom: 1.25rem;">⚙️ Configurar Código QR</h3>
                    
                    <div style="margin-bottom: 1rem;">
                        <label style="color: #cbd5e1; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">Contenido / URL del QR:</label>
                        <input type="text" id="qr_content" value="<?= htmlspecialchars($defaultUrl) ?>" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.9rem;">
                    </div>

                    <div style="margin-bottom: 1.5rem;">
                        <label style="color: #cbd5e1; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">Tamaño (Píxeles):</label>
                        <select id="qr_size" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.9rem;">
                            <option value="150">150 x 150 px (Pequeño)</option>
                            <option value="250" selected>250 x 250 px (Mediano - Recomendado)</option>
                            <option value="400">400 x 400 px (Grande)</option>
                        </select>
                    </div>
                </div>

                <!-- Panel de Vista Previa -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center;">
                    <h3 style="color: #22c55e; font-size: 1.1rem; font-weight: bold; margin-bottom: 1rem;">🖼️ Vista Previa en Tiempo Real</h3>
                    
                    <!-- Contenedor del QR Generado por JS -->
                    <div id="qr-container" style="background: #ffffff; padding: 15px; border-radius: 12px; display: inline-block; margin-bottom: 1.25rem; min-height: 250px; min-width: 250px; display: flex; align-items: center; justify-content: center;"></div>
                    
                    <a id="download-btn" href="#" download="goldensite_qr.png" style="background: #22c55e; color: #0f172a; text-decoration: none; padding: 0.75rem 1.5rem; font-weight: bold; border-radius: 6px; font-size: 0.9rem; display: inline-block;">
                        💾 Descargar Imagen PNG
                    </a>
                </div>
            </div>
        </div>

        <!-- Librería Local de Código QR y Lógica de Renderizado -->
        <script src="/js/qrcode.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const qrContainer = document.getElementById('qr-container');
                const inputEl = document.getElementById('qr_content');
                const sizeEl = document.getElementById('qr_size');
                const downloadBtn = document.getElementById('download-btn');
                let qrcode = null;

                function generateQR() {
                    qrContainer.innerHTML = ''; // Limpiar lienzo
                    const size = parseInt(sizeEl.value);
                    const text = inputEl.value.trim() || 'https://goldensitecms.com';
                    
                    qrcode = new QRCode(qrContainer, {
                        text: text,
                        width: size,
                        height: size,
                        colorDark: "#0f172a",
                        colorLight: "#ffffff",
                        correctLevel: QRCode.CorrectLevel.H
                    });

                    // Extraer Base64 para el botón de descarga con un ligero retraso
                    setTimeout(() => {
                        const img = qrContainer.querySelector('img');
                        const canvas = qrContainer.querySelector('canvas');
                        
                        if (img && img.src && img.src.startsWith('data:')) {
                            downloadBtn.href = img.src;
                        } else if (canvas) {
                            downloadBtn.href = canvas.toDataURL("image/png");
                        }
                    }, 50);
                }

                // Listeners para generación en tiempo real
                inputEl.addEventListener('input', generateQR);
                sizeEl.addEventListener('change', generateQR);

                // Generación inicial
                generateQR();
            });
        </script>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}