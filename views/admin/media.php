<?php use App\Core\Csrf; ?>
<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.8rem; color: #f8fafc; margin-bottom: 0.5rem;">Biblioteca de Medios</h1>
    <p style="color: #94a3b8;">Sube y gestiona las imágenes y archivos multimedia del sitio.</p>
</div>

<?php if (isset($status)): ?>
    <div style="padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; background: <?= $status['type'] === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $status['type'] === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $status['type'] === 'success' ? '#6ee7b7' : '#fca5a5' ?>;">
        <?= htmlspecialchars($status['msg']) ?>
    </div>
<?php endif; ?>

<div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
    <form action="/admin/media" method="POST" enctype="multipart/form-data" style="display: flex; gap: 1rem; align-items: center;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
        
        <input type="hidden" name="action" value="upload">
        <input type="file" name="file" required style="color: #94a3b8; background: #0f172a; padding: 0.5rem; border-radius: 6px; border: 1px solid #334155; flex: 1;">
        <button type="submit" style="padding: 0.65rem 1.25rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Subir Archivo</button>
    </form>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 1.5rem;">
    <?php foreach ($files as $file): ?>
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 0.75rem; text-align: center;">
            <img src="/uploads/<?= htmlspecialchars($file) ?>" style="width: 100%; height: 120px; object-fit: cover; border-radius: 4px; margin-bottom: 0.5rem;">
            <div style="font-size: 0.75rem; color: #94a3b8; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; margin-bottom: 0.5rem;"><?= htmlspecialchars($file) ?></div>
            <form action="/admin/media" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="filename" value="<?= htmlspecialchars($file) ?>">
                <button type="submit" style="width: 100%; background: #ef4444; color: #fff; border: none; padding: 0.35rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Eliminar</button>
            </form>
        </div>
    <?php endforeach; ?>
</div>