<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

class ChatModerationController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();

        // 1. Asegurar esquema completo de la BD para Moderación, Roles y Salas
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_chat_rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,
                access_type ENUM('public', 'private', 'vip') DEFAULT 'public',
                moderator_username VARCHAR(100) DEFAULT 'admin',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Insertar salas base si está vacía
            $stmtCount = $db->query("SELECT COUNT(*) FROM goldensite_chat_rooms");
            if ($stmtCount->fetchColumn() == 0) {
                $db->exec("INSERT INTO goldensite_chat_rooms (slug, name, description, access_type, moderator_username) VALUES 
                    ('General-Chat', '🌐 # General-Chat', 'Canal principal abierto para toda la comunidad', 'public', 'admin'),
                    ('Novedades-Goldensite', '🚀 # Novedades-Goldensite', 'Anuncios oficiales y actualizaciones del sistema', 'public', 'admin'),
                    ('Desarrolladores', '💻 # Desarrolladores', 'Canal técnico para código, scripts y soporte VIP', 'vip', 'admin');");
            }

            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_chat_sanctions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                type VARCHAR(20) NOT NULL DEFAULT 'mute',
                reason VARCHAR(255) NULL,
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_chat_user_roles (
                username VARCHAR(100) PRIMARY KEY,
                chat_role VARCHAR(20) NOT NULL DEFAULT 'member',
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("CREATE TABLE IF NOT EXISTS settings (
                setting_key VARCHAR(100) PRIMARY KEY,
                setting_value TEXT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {}

        // 2. Validar que el módulo goldensite-chat esté activo
        try {
            $isChatActive = \App\Core\FrontendEngine::isModuleActive('live-chat');

            if (!$isChatActive) {
                $_SESSION['plugin_status'] = ['type' => 'danger', 'msg' => '⚠️ Activa el módulo "Goldensite Live Chat" para acceder a la moderación.'];
                header('Location: /admin/plugins');
                exit;
            }
        } catch (\Throwable $e) {}

        // 3. Procesar solicitudes POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach ($_POST as $value) if (!is_string($value)) { http_response_code(422); exit('Formato de campo inválido.'); }
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['chat_mod_status'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/chat-moderation');
                exit;
            }

            $action = $_POST['action'] ?? '';

            // Crear o actualizar Sala
            if ($action === 'create_room') {
                $name     = trim($_POST['room_name'] ?? '');
                $slug = is_string($_POST['room_slug']??null)&&$_POST['room_slug']!==''?trim($_POST['room_slug']):preg_replace('/[^a-zA-Z0-9_-]/', '', str_replace(' ', '-', $name));
                $desc     = trim($_POST['room_description'] ?? '');
                $access   = trim($_POST['access_type'] ?? 'public');
                $modUser  = trim($_POST['moderator_username'] ?? 'admin');

                if (strlen($name)>100||!preg_match('/^[A-Za-z0-9_-]{1,50}$/D',$slug)||strlen($desc)>255||!in_array($access,['public','private','vip'],true)||!\App\Core\Database::getInstance()->fetch('SELECT id FROM users WHERE username=?',[$modUser])) { http_response_code(422); exit('Revisa nombre, identificador, acceso y moderador existente.'); }
                if (!empty($name) && !empty($slug)) {
                    $stmt = $db->prepare("INSERT INTO goldensite_chat_rooms (slug, name, description, access_type, moderator_username) 
                                          VALUES (:s, :n, :d, :a, :m) 
                                          ON DUPLICATE KEY UPDATE name=:n2, description=:d2, access_type=:a2, moderator_username=:m2");
                    $stmt->execute(['s' => $slug, 'n' => $name, 'd' => $desc, 'a' => $access, 'm' => $modUser, 'n2' => $name, 'd2' => $desc, 'a2' => $access, 'm2' => $modUser]);
                    $_SESSION['chat_mod_status'] = ['type' => 'success', 'msg' => "🏠 Sala '{$name}' creada/actualizada correctamente."];
                }
            }

            // Eliminar Sala
            if ($action === 'delete_room') {
                $roomId = (int)($_POST['room_id'] ?? 0);
                $roomToDelete=\App\Core\Database::getInstance()->fetch('SELECT slug FROM goldensite_chat_rooms WHERE id=?',[$roomId]); if ($roomToDelete&&in_array($roomToDelete['slug'],['general','soporte','vip'],true)) { http_response_code(422); exit('Las salas base se pueden configurar, pero no eliminar porque forman parte de la navegación del chat.'); }
                if ($roomId > 0) {
                    $stmt = $db->prepare("DELETE FROM goldensite_chat_rooms WHERE id = :id");
                    $stmt->execute(['id' => $roomId]);
                    $_SESSION['chat_mod_status'] = ['type' => 'success', 'msg' => "🗑️ Sala #{$roomId} eliminada correctamente."];
                }
            }

            // Asignar rol de chat a usuario
            if ($action === 'assign_role') {
                $username = trim($_POST['username'] ?? '');
                $role = \App\Core\ConsoleView::field($_POST,'chat_role',20)?:'member'; if (!in_array($role,['member','moderator','superadmin','admin'],true)) { http_response_code(422); exit('Rol de chat inválido.'); }

                if (strlen($username)>100||!\App\Core\Database::getInstance()->fetch('SELECT id FROM users WHERE username=?',[$username])) { http_response_code(422); exit('Cuenta de chat inexistente.'); }
                if (!empty($username)) {
                    $stmt = $db->prepare("INSERT INTO goldensite_chat_user_roles (username, chat_role) VALUES (:u, :r) ON DUPLICATE KEY UPDATE chat_role = :r2");
                    $stmt->execute(['u' => $username, 'r' => $role, 'r2' => $role]);
                    $_SESSION['chat_mod_status'] = ['type' => 'success', 'msg' => "👑 Rol '{$role}' asignado a '{$username}'."];
                }
            }

            // Guardar palabras prohibidas
            if ($action === 'save_banned_words') {
                $words = trim($_POST['banned_words'] ?? '');
                $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('chat_banned_words', :val) ON DUPLICATE KEY UPDATE setting_value = :val2");
                $stmt->execute(['val' => $words, 'val2' => $words]);
                $_SESSION['chat_mod_status'] = ['type' => 'success', 'msg' => '🤖 Reglas del AutoBot actualizadas.'];
            }

            // Aplicar sanción manual
            if ($action === 'manual_sanction') {
                $username = trim($_POST['username'] ?? '');
                $type = \App\Core\ConsoleView::field($_POST,'type',20)?:\App\Core\ConsoleView::field($_POST,'sanction_type',20)?:'mute'; if (!in_array($type,['mute','ban'],true)) { http_response_code(422); exit('Sanción inválida.'); }
                $reason   = trim($_POST['reason'] ?? 'Sanción administrativa manual');
                $hours    = (int)($_POST['duration_hours'] ?? 24);

                if (strlen($username)>100||strlen($reason)>255||!\App\Core\Database::getInstance()->fetch('SELECT id FROM users WHERE username=?',[$username])) { http_response_code(422); exit('Revisa la cuenta y el motivo de la sanción.'); }
                if (!empty($username)) {
                    $hours=max(0,min(8760,$hours)); $expires=$hours>0?\App\Core\Database::getInstance()->fetch('SELECT DATE_ADD(NOW(),INTERVAL '.$hours.' HOUR) deadline')['deadline']:null;
                    $stmt = $db->prepare("INSERT INTO goldensite_chat_sanctions (username, type, reason, expires_at) VALUES (:u, :t, :r, :e)");
                    $stmt->execute(['u' => $username, 't' => $type, 'r' => $reason, 'e' => $expires]);
                    $_SESSION['chat_mod_status'] = ['type' => 'success', 'msg' => "Sanción ('{$type}') aplicada a '{$username}'."];
                }
            }

            // Revocar sanción
            if ($action === 'revoke_sanction') {
                $sanctionId = (int)($_POST['sanction_id'] ?? 0);
                if ($sanctionId > 0) {
                    $stmt = $db->prepare("DELETE FROM goldensite_chat_sanctions WHERE id = :id");
                    $stmt->execute(['id' => $sanctionId]);
                    $_SESSION['chat_mod_status'] = ['type' => 'success', 'msg' => "Sanción #{$sanctionId} revocada."];
                }
            }

            header('Location: /admin/chat-moderation');
            exit;
        }

        // Leer salas existentes
        $chatRooms = [];
        try {
            $stmt = $db->query("SELECT * FROM goldensite_chat_rooms ORDER BY id ASC");
            $chatRooms = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}

        // Leer usuarios del sistema
        $chatUsers = [];
        try {
            $stmt = $db->query("SELECT 
                                    COALESCE(NULLIF(u.username, ''), NULLIF(u.email, ''), 'admin') as resolved_username,
                                    COALESCE(u.role, 'admin') as system_role, 
                                    COALESCE(r.chat_role, 'member') as chat_role 
                                FROM users u
                                LEFT JOIN goldensite_chat_user_roles r ON COALESCE(NULLIF(u.username, ''), NULLIF(u.email, ''), 'admin') = r.username 
                                ORDER BY u.id ASC");
            $chatUsers = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}

        // Leer palabras prohibidas
        $bannedWords = "badword1, badword2, groseria, insulto, spam, hack, exploit";
        try {
            $stmt = $db->prepare("SELECT setting_value FROM settings WHERE setting_key = 'chat_banned_words' LIMIT 1");
            $stmt->execute();
            $val = $stmt->fetchColumn();
            if (!empty($val)) $bannedWords = $val;
        } catch (\Throwable $e) {}

        // Obtener historial de sanciones
        $sanctions = [];
        try {
            $stmt = $db->query("SELECT * FROM goldensite_chat_sanctions ORDER BY id DESC LIMIT 50");
            $sanctions = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {}

        $status = $_SESSION['chat_mod_status'] ?? null;
        unset($_SESSION['chat_mod_status']);

        $csrfToken = Csrf::generate();
        $title     = "Moderación & Gestión de Salas - GoldenSiteCMS";

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0; color: #fff;">
            <div style="margin-bottom: 2rem; display: flex; flex-wrap: wrap; gap: 1rem; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.8rem; font-weight: bold; color: #fff; margin-bottom: 0.25rem;">
                        🤖 Moderación, Salas & Roles de Chat
                    </h1>
                    <p style="color: #94a3b8; font-size: 0.95rem;">Gestión de canales/salas, asignación de moderadores y reglas del AutoBot.</p>
                </div>
                <div class="nx-actions">
                    <?php if (\App\Core\FrontendEngine::isModuleActive('live-chat')): ?>
                        <a class="nx-btn primary" href="/chat">Abrir chat para usuarios</a>
                    <?php endif; ?>
                    <a class="nx-btn" href="<?= htmlspecialchars(\App\Core\AdminPath::url('/admin/plugins'), ENT_QUOTES, 'UTF-8') ?>">Volver a módulos</a>
                </div>
            </div>

            <?php if (!empty($status)): ?>
                <div style="background: <?= $status['type'] === 'success' ? '#15803d' : '#b91c1c' ?>; color: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold;">
                    <?= htmlspecialchars($status['msg']) ?>
                </div>
            <?php endif; ?>

            <!-- 1. SECCIÓN DE GESTIÓN DE SALAS DE CHAT -->
            <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="font-size: 1.15rem; font-weight: bold; color: #38bdf8; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem;">
                    🏠 Gestión de Salas & Canales Activos
                </h3>

                <!-- Formulario de Creación de Sala -->
                <form action="/admin/chat-moderation" method="POST" style="background: #0f172a; padding: 1.25rem; border-radius: 8px; border: 1px solid #334155; margin-bottom: 1.5rem; display: grid; grid-template-columns: 2fr 2fr 1fr 1fr auto; gap: 1rem; align-items: end;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                    
                    <input type="hidden" name="action" value="create_room"><label>Identificador de sala · opcional para actualizar<input name="room_slug" maxlength="50" placeholder="general o una sala existente"></label>

                    <div>
                        <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;">Nombre de la Sala</label>
                        <input type="text" name="room_name" required placeholder="Ej: Soporte Técnico" style="width: 100%; background: #1e293b; border: 1px solid #475569; color: #fff; padding: 0.55rem; border-radius: 6px; outline: none; font-size: 0.88rem;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;">Descripción / Características</label>
                        <input type="text" name="room_description" placeholder="Ej: Canal de ayuda para usuarios" style="width: 100%; background: #1e293b; border: 1px solid #475569; color: #fff; padding: 0.55rem; border-radius: 6px; outline: none; font-size: 0.88rem;">
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;">Acceso</label>
                        <select name="access_type" style="width: 100%; background: #1e293b; border: 1px solid #475569; color: #fff; padding: 0.55rem; border-radius: 6px; font-size: 0.88rem;">
                            <option value="public">🌐 Pública</option>
                            <option value="private">🔒 Protegida</option>
                            <option value="vip">⭐ VIP</option>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.78rem; color: #94a3b8; display: block; margin-bottom: 0.25rem;">Moderador Asignado</label>
                        <select name="moderator_username" style="width: 100%; background: #1e293b; border: 1px solid #475569; color: #fff; padding: 0.55rem; border-radius: 6px; font-size: 0.88rem;">
                            <?php foreach ($chatUsers as $usr): ?>
                                <option value="<?= htmlspecialchars($usr['resolved_username']) ?>"><?= htmlspecialchars($usr['resolved_username']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button type="submit" style="background: #22c55e; color: #0f172a; border: none; padding: 0.6rem 1.2rem; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.88rem;">
                        ➕ Crear Sala
                    </button>
                </form>

                <!-- Tabla de Salas -->
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #334155; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">
                                <th style="padding: 0.75rem;">Canal / Slug</th>
                                <th style="padding: 0.75rem;">Nombre</th>
                                <th style="padding: 0.75rem;">Características / Descripción</th>
                                <th style="padding: 0.75rem;">Acceso</th>
                                <th style="padding: 0.75rem;">Moderador Responsable</th>
                                <th style="padding: 0.75rem; text-align: right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($chatRooms)): ?>
                                <tr><td colspan="6" style="padding: 1.5rem; text-align: center; color: #94a3b8;">No hay salas creadas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($chatRooms as $room): ?>
                                    <tr style="border-bottom: 1px solid #334155;">
                                        <td style="padding: 0.75rem; color: #38bdf8; font-weight: bold;">#<?= htmlspecialchars($room['slug']) ?></td>
                                        <td style="padding: 0.75rem; color: #fff; font-weight: bold;"><?= htmlspecialchars($room['name']) ?><small style="display:block"><?= htmlspecialchars($room['slug']) ?></small></td>
                                        <td style="padding: 0.75rem; color: #cbd5e1;"><?= htmlspecialchars($room['description'] ?? 'Sin descripción') ?></td>
                                        <td style="padding: 0.75rem;">
                                            <span style="background: <?= $room['access_type'] === 'vip' ? '#f59e0b' : ($room['access_type'] === 'private' ? '#ef4444' : '#15803d') ?>; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">
                                                <?= strtoupper(htmlspecialchars($room['access_type'])) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 0.75rem; color: #a855f7; font-weight: bold;">🛡️ @<?= htmlspecialchars($room['moderator_username']) ?></td>
                                        <td style="padding: 0.75rem; text-align: right;">
                                            <form action="/admin/chat-moderation" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                                
                                                <input type="hidden" name="action" value="delete_room">
                                                <input type="hidden" name="room_id" value="<?= htmlspecialchars($room['id']) ?>">
                                                <button type="submit" onclick="return confirm('¿Eliminar esta sala?');" style="background: #ef4444; color: #fff; border: none; padding: 0.35rem 0.75rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: bold;">Eliminar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 2. SECCIÓN DE ROLES DE USUARIO Y JERARQUÍA -->
            <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem; margin-bottom: 2rem;">
                <h3 style="font-size: 1.15rem; font-weight: bold; color: #f59e0b; margin-bottom: 1rem;">👑 Listado de Usuarios y Jerarquía de Chat</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #334155; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">
                                <th style="padding: 0.75rem;">Usuario</th>
                                <th style="padding: 0.75rem;">Rol del Sistema</th>
                                <th style="padding: 0.75rem;">Rol en Goldensite Chat</th>
                                <th style="padding: 0.75rem; text-align: right;">Cambiar Rol</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($chatUsers as $u): ?>
                                <tr style="border-bottom: 1px solid #334155;">
                                    <td style="padding: 0.75rem; font-weight: bold; color: #fff;"><?= htmlspecialchars($u['resolved_username']) ?></td>
                                    <td style="padding: 0.75rem; color: #94a3b8;"><?= strtoupper(htmlspecialchars($u['system_role'])) ?></td>
                                    <td style="padding: 0.75rem;">
                                        <span style="background: <?= $u['chat_role'] === 'superadmin' ? '#ef4444' : ($u['chat_role'] === 'moderator' ? '#f59e0b' : '#334155') ?>; color: #fff; padding: 0.2rem 0.6rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">
                                            <?= strtoupper(htmlspecialchars($u['chat_role'])) ?>
                                        </span>
                                    </td>
                                    <td style="padding: 0.75rem; text-align: right;">
                                        <form action="/admin/chat-moderation" method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                            
                                            <input type="hidden" name="action" value="assign_role">
                                            <input type="hidden" name="username" value="<?= htmlspecialchars($u['resolved_username']) ?>">
                                            <select name="chat_role" style="background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.35rem 0.6rem; border-radius: 4px; font-size: 0.8rem;">
                                                <option value="member" <?= $u['chat_role'] === 'member' ? 'selected' : '' ?>>Miembro Normal</option>
                                                <option value="moderator" <?= $u['chat_role'] === 'moderator' ? 'selected' : '' ?>>🛡️ Moderador</option>
                                                <option value="superadmin" <?= $u['chat_role'] === 'superadmin' ? 'selected' : '' ?>>👑 SuperAdmin Chat</option>
                                            </select>
                                            <button type="submit" style="background: #0284c7; color: #fff; border: none; padding: 0.35rem 0.75rem; border-radius: 4px; font-size: 0.78rem; cursor: pointer; font-weight: bold;">Actualizar</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- 3. CONFIGURACIÓN DEL ROBOT Y SANCIÓN MANUAL -->
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; margin-bottom: 2rem;">
                <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: bold; color: #38bdf8; margin-bottom: 0.5rem;">🤖 Reglas de Filtrado del Robot</h3>
                    <form action="/admin/chat-moderation" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <input type="hidden" name="action" value="save_banned_words">
                        <textarea name="banned_words" rows="5" style="width: 100%; background: #0f172a; border: 1px solid #475569; border-radius: 8px; color: #f8fafc; padding: 0.8rem; font-family: monospace; font-size: 0.88rem; outline: none;"><?= htmlspecialchars($bannedWords) ?></textarea>
                        <button type="submit" style="margin-top: 1rem; width: 100%; background: #0284c7; color: #fff; font-weight: bold; border: none; padding: 0.75rem; border-radius: 6px; cursor: pointer;">💾 Guardar Palabras del Robot</button>
                    </form>
                </div>

                <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem;">
                    <h3 style="font-size: 1.1rem; font-weight: bold; color: #ef4444; margin-bottom: 0.5rem;">⛔ Aplicar Sanción Manual</h3>
                    <form action="/admin/chat-moderation" method="POST" style="display: flex; flex-direction: column; gap: 0.8rem;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <input type="hidden" name="action" value="manual_sanction">
                        <div>
                            <label style="font-size: 0.8rem; color: #cbd5e1; display: block;">Nombre de Usuario</label>
                            <input type="text" name="username" required placeholder="Ej: usuario123" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.6rem; border-radius: 6px; outline: none;">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.8rem;">
                            <div>
                                <label style="font-size: 0.8rem; color: #cbd5e1; display: block;">Tipo</label>
                                <select name="type" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.6rem; border-radius: 6px;">
                                    <option value="mute">Mute</option>
                                    <option value="ban">Ban</option>
                                </select>
                            </div>
                            <div>
                                <label style="font-size: 0.8rem; color: #cbd5e1; display: block;">Duración</label>
                                <select name="duration_hours" style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.6rem; border-radius: 6px;">
                                    <option value="1">1 Hora</option>
                                    <option value="24" selected>24 Horas</option>
                                    <option value="0">Permanente</option>
                                </select>
                            </div>
                        </div>
                        <button type="submit" style="margin-top: 0.5rem; background: #ef4444; color: #fff; font-weight: bold; border: none; padding: 0.75rem; border-radius: 6px; cursor: pointer;">🚨 Aplicar Sanción</button>
                    </form>
                </div>
            </div>

            <!-- 4. HISTORIAL DE SANCIONES -->
            <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem;">
                <h3 style="font-size: 1.15rem; font-weight: bold; color: #f59e0b; margin-bottom: 1rem;">📜 Registro de Sanciones Activas</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #334155; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">
                                <th style="padding: 0.75rem;">ID</th>
                                <th style="padding: 0.75rem;">Usuario</th>
                                <th style="padding: 0.75rem;">Tipo</th>
                                <th style="padding: 0.75rem;">Motivo / Causa</th>
                                <th style="padding: 0.75rem;">Expiración</th>
                                <th style="padding: 0.75rem; text-align: right;">Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($sanctions)): ?>
                                <tr><td colspan="6" style="padding: 2rem; text-align: center; color: #94a3b8;">No hay sanciones activas registradas.</td></tr>
                            <?php else: ?>
                                <?php foreach ($sanctions as $s): ?>
                                    <tr style="border-bottom: 1px solid #334155;">
                                        <td style="padding: 0.75rem; color: #94a3b8;">#<?= htmlspecialchars($s['id']) ?></td>
                                        <td style="padding: 0.75rem; font-weight: bold; color: #38bdf8;"><?= htmlspecialchars($s['username']) ?></td>
                                        <td style="padding: 0.75rem;"><span style="background: #ef4444; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;"><?= strtoupper(htmlspecialchars($s['type'])) ?></span></td>
                                        <td style="padding: 0.75rem; color: #cbd5e1;"><?= htmlspecialchars($s['reason']) ?></td>
                                        <td style="padding: 0.75rem; color: #f59e0b;"><?= htmlspecialchars($s['expires_at'] ?? 'Permanente') ?></td>
                                        <td style="padding: 0.75rem; text-align: right;">
                                            <form action="/admin/chat-moderation" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                                
                                                <input type="hidden" name="action" value="revoke_sanction">
                                                <input type="hidden" name="sanction_id" value="<?= htmlspecialchars($s['id']) ?>">
                                                <button type="submit" style="background: #22c55e; color: #0f172a; border: none; padding: 0.35rem 0.75rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: bold;">Revocar</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}
