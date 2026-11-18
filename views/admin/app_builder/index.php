<?php 
use App\Core\Csrf; 
$csrfToken = Csrf::generate(); 
$cleanName = preg_replace('/[^a-zA-Z0-9]/', '', $manifest['business_name'] ?? 'BellaHair');
?>

<div style="max-width: 1000px; margin: 0 auto; color: #f8fafc; font-family: ui-sans-serif, system-ui;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 style="font-size: 1.75rem; font-weight: 800; color: #38bdf8;">📱 Android Goldensite App Builder</h1>
            <p style="color: #94a3b8; font-size: 0.85rem;">Gestión del Business Manifest, temas móviles y compilación de la aplicación ejecutable.</p>
        </div>
        <a href="/admin/ai-builder" style="background: #334155; color: #fff; text-decoration: none; padding: 0.5rem 1rem; border-radius: 6px; font-size: 0.8rem; font-weight: bold;">← Volver al AI Builder</a>
    </div>

    <?php if (!empty($status)): ?>
        <div style="background: <?= $status['type'] === 'success' ? 'rgba(34, 197, 94, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $status['type'] === 'success' ? '#22c55e' : '#ef4444' ?>; color: <?= $status['type'] === 'success' ? '#4ade80' : '#fca5a5' ?>; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.85rem; font-weight: bold;">
            <?= htmlspecialchars($status['msg']) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
            <h3 style="color: #fff; font-size: 1.1rem; font-weight: bold; margin-bottom: 1rem;">⚙️ Parámetros del Business Manifest</h3>
            <form action="/admin/app-builder" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                
                
                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: bold;">Nombre de la Aplicación:</label>
                    <input type="text" name="app_name" value="<?= htmlspecialchars($manifest['business_name'] ?? 'BellaHair') ?>" required style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.85rem;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; font-size: 0.8rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: bold;">Package ID (Identificador Android):</label>
                    <input type="text" name="package_id" value="<?= htmlspecialchars($manifest['package_id'] ?? 'com.GoldensiteCMS.bellahair') ?>" required style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.85rem; font-family: monospace;">
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; font-size: 0.8rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: bold;">Color Principal del Tema Mobile:</label>
                    <input type="color" name="primary_color" value="<?= htmlspecialchars($manifest['primary_color'] ?? '#38bdf8') ?>" style="width: 100%; height: 40px; background: #0f172a; border: 1px solid #475569; border-radius: 6px; cursor: pointer;">
                </div>

                <button type="submit" style="width: 100%; background: #22c55e; color: #0f172a; border: none; padding: 0.75rem; border-radius: 6px; font-weight: 900; font-size: 0.88rem; cursor: pointer;">
                    🔨 Generar Proyecto & Paquetes
                </button>
            </form>
        </div>

        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
            <h3 style="color: #fff; font-size: 1.1rem; font-weight: bold; margin-bottom: 1rem;">📦 Artefactos Disponibles</h3>
            
            <div style="background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 1rem; margin-bottom: 1rem;">
                <div style="font-weight: bold; color: #22c55e; font-size: 0.85rem; margin-bottom: 0.25rem;">📱 APK Ejecutable</div>
                <p style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.75rem;">Descarga el instalador ejecutable de la aplicación.</p>
                <span>APK y AAB requieren compilación y firma externas.</span>
            </div>

            <div style="background: #0f172a; border: 1px solid #334155; border-radius: 8px; padding: 1rem;">
                <div style="font-weight: bold; color: #38bdf8; font-size: 0.85rem; margin-bottom: 0.25rem;">📄 Business Manifest</div>
                <p style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.75rem;">Configuración Single Source of Truth para Android Runtime.</p>
                <a href="/downloads/android/manifest.json" target="_blank" style="display: inline-block; background: #38bdf8; color: #000; font-weight: bold; text-decoration: none; padding: 0.4rem 0.8rem; border-radius: 4px; font-size: 0.75rem;">Ver Manifest JSON</a>
            </div>
        </div>
    </div>
</div>