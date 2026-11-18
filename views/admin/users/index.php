<?php use App\Core\Csrf; $csrfToken = Csrf::generate(); ?>
<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.8rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">Gestión de Usuarios y Roles</h1>
        <p style="color: #94a3b8; font-size: 0.9rem;">Asigna permisos, cambia perfiles y modera el acceso al sistema.</p>
    </div>
    <a href="/admin/users/create" style="padding: 0.6rem 1.2rem; background: #0284c7; color: #fff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 0.85rem;">+ Crear Usuario PRO</a>
</div>

<div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem; text-align: left;">
        <thead>
            <tr style="border-bottom: 1px solid #334155; background: #0f172a; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">
                <th style="padding: 1rem;">Usuario</th>
                <th style="padding: 1rem;">Correo Electrónico</th>
                <th style="padding: 1rem;">Rol de Sistema</th>
                <th style="padding: 1rem;">Fecha Registro</th>
                <th style="padding: 1rem; text-align: right;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): 
                $userRole = strtolower($user['role'] ?? (($user['role_id'] ?? 2) == 1 ? 'admin' : 'user'));
            ?>
            <tr style="border-bottom: 1px solid #334155; color: #cbd5e1;">
                <td style="padding: 1rem; font-weight: 600; color: #f8fafc;">
                    <?= htmlspecialchars($user['username'] ?? $user['name'] ?? 'Usuario') ?>
                </td>
                <td style="padding: 1rem; color: #38bdf8;"><?= htmlspecialchars($user['email']) ?></td>
                <td style="padding: 1rem;">
                    <form action="/admin/users/role" method="POST" style="display: inline-block;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <select name="role" onchange="this.form.submit()" style="background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.35rem 0.6rem; border-radius: 6px; font-size: 0.8rem; font-weight: bold; cursor: pointer;">
                            <option value="user" <?= $userRole === 'user' ? 'selected' : '' ?>>Suscriptor / Cliente</option>
                            <option value="editor" <?= $userRole === 'editor' ? 'selected' : '' ?>>Editor</option>
                            <option value="admin" <?= $userRole === 'admin' ? 'selected' : '' ?>>Administrador</option>
                        </select>
                    </form>
                </td>
                <td style="padding: 1rem; font-size: 0.8rem; color: #64748b;"><?= htmlspecialchars($user['created_at']) ?></td>
                <td style="padding: 1rem; text-align: right;">
                    <?php if ((int)$user['id'] !== ($_SESSION['user_id'] ?? 0)): ?>
                    <form action="/admin/users/delete" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                        <button type="submit" style="background: rgba(239, 68, 68, 0.15); border: 1px solid #ef4444; color: #fca5a5; padding: 0.35rem 0.7rem; border-radius: 6px; cursor: pointer; font-size: 0.78rem; font-weight: bold;" onclick="return confirm('¿Eliminar usuario?')">Eliminar</button>
                    </form>
                    <?php else: ?>
                        <span style="font-size: 0.75rem; color: #38bdf8; font-weight: bold; background: rgba(56, 189, 248, 0.1); padding: 0.3rem 0.6rem; border-radius: 4px;">(Tu Cuenta)</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>