<?php use App\Core\Csrf; $csrfToken = Csrf::generate(); ?>
<div style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <h1 style="font-size: 1.8rem; font-weight: 700; color: #f8fafc; margin-bottom: 0.25rem;">Crear Nuevo Usuario</h1>
        <p style="color: #94a3b8; font-size: 0.9rem;">Asigna credenciales y define el nivel de privilegios en el sistema.</p>
    </div>
    <a href="/admin/users" style="padding: 0.6rem 1.2rem; background: #334155; color: #f8fafc; text-decoration: none; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">← Volver al Listado</a>
</div>

<form action="/admin/users/store" method="POST" style="max-width: 600px; background: #1e293b; border: 1px solid #334155; padding: 2rem; border-radius: 12px; display: flex; flex-direction: column; gap: 1.25rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
    

    <div>
        <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 600;">Nombre de Usuario</label>
        <input type="text" name="username" required placeholder="ej. carlos_admin" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.7rem; border-radius: 6px; font-size: 0.9rem;">
    </div>

    <div>
        <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 600;">Correo Electrónico</label>
        <input type="email" name="email" required placeholder="carlos@empresa.com" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.7rem; border-radius: 6px; font-size: 0.9rem;">
    </div>

    <div>
        <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 600;">Contraseña de Acceso</label>
        <input type="password" name="password" required placeholder="••••••••" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.7rem; border-radius: 6px; font-size: 0.9rem;">
    </div>

    <div>
        <label style="display: block; font-size: 0.85rem; color: #cbd5e1; margin-bottom: 0.4rem; font-weight: 600;">Rol y Privilegios</label>
        <select name="role" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.7rem; border-radius: 6px; font-size: 0.9rem;">
            <option value="user">Suscriptor / Cliente (Acceso a /mi-cuenta)</option>
            <option value="editor">Editor de Contenidos (Gestión de entradas y medios)</option>
            <option value="admin">Administrador Global (Acceso total al Panel Máster)</option>
        </select>
    </div>

    <button type="submit" style="background: #0284c7; color: #ffffff; font-weight: bold; border: none; padding: 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.95rem; margin-top: 0.5rem;">
        Guardar Usuario 🚀
    </button>
</form>