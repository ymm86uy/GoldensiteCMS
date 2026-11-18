<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.8rem; color: #f8fafc; margin-bottom: 0.5rem; font-weight: 700;">Configuración de Servidor FTP / SFTP</h1>
    <p style="color: #94a3b8; font-size: 0.95rem;">Administra las credenciales y parámetros de conexión remota de GoldenSiteCMS para respaldos y despliegues.</p>
</div>

<?php if (isset($status)): ?>
    <div style="padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; background: <?= $status['type'] === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $status['type'] === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $status['type'] === 'success' ? '#6ee7b7' : '#fca5a5' ?>;">
        <?= htmlspecialchars($status['msg']) ?>
    </div>
<?php endif; ?>

<div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem; max-width: 800px;">
    <h2 style="font-size: 1.2rem; color: #38bdf8; margin-top: 0; margin-bottom: 1.2rem; font-weight: 600;">Parámetros de Conexión</h2>

    <form action="/admin/ftp" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
        

        <div style="display: grid; grid-template-columns: 3fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <label style="display: block; font-size: 0.875rem; color: #cbd5e1; margin-bottom: 0.5rem;">Servidor / Host FTP</label>
                <input type="text" name="ftp_host" value="<?= htmlspecialchars($ftpHost) ?>" placeholder="ftp.midominio.com" style="width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc;">
            </div>
            <div>
                <label style="display: block; font-size: 0.875rem; color: #cbd5e1; margin-bottom: 0.5rem;">Puerto</label>
                <input type="number" name="ftp_port" value="<?= htmlspecialchars($ftpPort) ?>" placeholder="21" style="width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc;">
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
            <div>
                <label style="display: block; font-size: 0.875rem; color: #cbd5e1; margin-bottom: 0.5rem;">Usuario FTP</label>
                <input type="text" name="ftp_user" value="<?= htmlspecialchars($ftpUser) ?>" placeholder="admin_ftp" style="width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc;">
            </div>
            <div>
                <label style="display: block; font-size: 0.875rem; color: #cbd5e1; margin-bottom: 0.5rem;">Contraseña FTP</label>
                <input type="password" name="ftp_pass" placeholder="••••••••" style="width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc;">
            </div>
        </div>

        <div style="margin-bottom: 1.25rem;">
            <label style="display: block; font-size: 0.875rem; color: #cbd5e1; margin-bottom: 0.5rem;">Directorio Remoto Raíz</label>
            <input type="text" name="ftp_root" value="<?= htmlspecialchars($ftpRoot) ?>" placeholder="/public_html" style="width: 100%; padding: 0.6rem; background: #0f172a; border: 1px solid #475569; border-radius: 6px; color: #f8fafc;">
        </div>

        <div style="display: flex; gap: 2rem; margin-bottom: 1.5rem;">
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; font-size: 0.9rem; cursor: pointer;">
                <input type="checkbox" name="ftp_passive" value="1" <?= $ftpPassive === '1' ? 'checked' : '' ?> style="accent-color: #0284c7;">
                Modo Pasivo (PASV)
            </label>
            <label style="display: flex; align-items: center; gap: 0.5rem; color: #cbd5e1; font-size: 0.9rem; cursor: pointer;">
                <input type="checkbox" name="ftp_ssl" value="1" <?= $ftpSsl === '1' ? 'checked' : '' ?> style="accent-color: #0284c7;">
                Usar SSL/TLS Implícito (FTPS)
            </label>
        </div>

        <div style="display: flex; gap: 1rem;">
            <button type="submit" name="action" value="save_ftp" style="flex: 1; padding: 0.75rem; background: #0284c7; color: #ffffff; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">
                Guardar Configuración
            </button>
            <button type="submit" name="action" value="test_connection" style="padding: 0.75rem 1.25rem; background: #334155; color: #f8fafc; border: 1px solid #475569; border-radius: 6px; font-weight: 600; cursor: pointer;">
                Probar Conexión
            </button>
        </div>
    </form>
</div>