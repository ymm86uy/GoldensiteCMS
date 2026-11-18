<?php

namespace Plugins\GoldensiteChat;

use App\Core\Auth;
use App\Core\Database;

class ChatEngine
{
    public static function inspectAndFilter(string $sender, string $message, string $channel = 'general'): array
    {
        return self::processMessage($sender, $message, $channel);
    }
    private static array $defaultBanned = ['badword1', 'groseria', 'insulto', 'exploit', 'spam', 'phishing'];

    public static function initSchema(): void
    {
        try {
            $db = Database::getInstance()->getConnection();

            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_chat_rooms (
                id INT AUTO_INCREMENT PRIMARY KEY,
                slug VARCHAR(50) UNIQUE NOT NULL,
                name VARCHAR(100) NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_chat_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sender_name VARCHAR(100) NOT NULL,
                user_role VARCHAR(20) DEFAULT 'user',
                message TEXT NOT NULL,
                image_url VARCHAR(255) NULL,
                channel VARCHAR(50) DEFAULT 'general',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_chat_sanctions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(100) NOT NULL,
                type VARCHAR(20) DEFAULT 'mute',
                reason VARCHAR(255) NULL,
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            // Cargar salas por defecto
            $db->exec("INSERT IGNORE INTO goldensite_chat_rooms (slug, name, access_type) VALUES 
                ('general', '🌐 Sala General', 'public'),
                ('soporte', '🛠️ Soporte Técnico', 'public'),
                ('vip', '⭐ Canal VIP', 'vip');");

        } catch (\Throwable $e) {}
    }

    public static function getRooms(): array
    {
        self::initSchema();
        try {
            $db = Database::getInstance()->getConnection();
            return $db->query("SELECT * FROM goldensite_chat_rooms ORDER BY id ASC")->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    public static function getMessages(string $channel = 'general'): array
    {
        self::initSchema();
        if (!self::canAccess($channel)) return [];
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("SELECT * FROM (SELECT * FROM goldensite_chat_messages WHERE channel = :c ORDER BY id DESC LIMIT 100) latest ORDER BY id ASC");
            $stmt->execute(['c' => $channel]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) { return []; }
    }

    public static function processMessage(string $sender, string $message, string $channel = 'general', string $imgUrl = ''): array
    {
        self::initSchema();
        if (!self::canAccess($channel)) return ['success' => false, 'msg' => 'Sala no disponible para esta cuenta.'];
        $sender = (string)(\App\Core\Auth::user()['username'] ?? '');
        if ($sender === '' || trim($message) === '' || mb_strlen($message) > 2000 || ($imgUrl !== '' && !str_starts_with($imgUrl, '/uploads/'))) return ['success' => false, 'msg' => 'Mensaje inválido.'];
        $db = Database::getInstance()->getConnection();

        // 1. Validar Mute/Ban
        try {
            $stmt = $db->prepare("SELECT type, reason FROM goldensite_chat_sanctions WHERE username = :u AND (expires_at IS NULL OR expires_at > NOW()) ORDER BY id DESC LIMIT 1");
            $stmt->execute(['u' => $sender]);
            $sanc = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($sanc) {
                return ['success' => false, 'msg' => '⛔ Estás ' . ($sanc['type'] === 'ban' ? 'baneado' : 'silenciado') . ' del chat.'];
            }
        } catch (\Throwable $e) {}

        // 2. Filtro de Palabras Prohibidas
        $bannedWords = self::$defaultBanned;
        try {
            $r = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'chat_banned_words' LIMIT 1");
            if ($r && $val = $r->fetchColumn()) {
                $bannedWords = array_map('trim', explode(',', $val));
            }
        } catch (\Throwable $e) {}

        foreach ($bannedWords as $word) {
            if (!empty($word) && preg_match("/\b" . preg_quote($word, '/') . "\b/i", $message)) {
                // Auto-Mute de 10 min
                $stmtMute = $db->prepare("INSERT INTO goldensite_chat_sanctions (username, type, reason, expires_at) VALUES (?, 'mute', 'Uso de palabras prohibidas', DATE_ADD(NOW(), INTERVAL 10 MINUTE))");
                $stmtMute->execute([$sender]);
                return ['success' => false, 'msg' => '🤖 [Auto-Mod]: Mensaje bloqueado por contener vocabulario no permitido. Has sido silenciado 10 min.'];
            }
        }

        // 3. Insertar Mensaje
        try {
            $stmt = $db->prepare("INSERT INTO goldensite_chat_messages (sender_name, message, image_url, channel) VALUES (:s, :m, :img, :c)");
            $stmt->execute(['s' => $sender, 'm' => $message, 'img' => $imgUrl, 'c' => $channel]);
            return ['success' => true, 'msg' => 'Mensaje enviado.'];
        } catch (\Throwable $e) {
            return ['success' => false, 'msg' => 'Error de base de datos.'];
        }
    }

    public static function canAccess(string $channel): bool
    {
        $user = \App\Core\Auth::user();
        if (!$user || strlen($channel) > 50) return false;
        $room = Database::getInstance()->fetch('SELECT access_type, moderator_username FROM goldensite_chat_rooms WHERE slug = ?', [$channel]);
        if (!$room) return false;
        if ((int)($user['role_id'] ?? 0) === 1 || ($user['role'] ?? '') === 'admin') return true;
        $staffRole=Database::getInstance()->fetch('SELECT chat_role FROM goldensite_chat_user_roles WHERE username=?',[$user['username']]); if (($staffRole['chat_role']??'')==='superadmin') return true;
        if (($room['moderator_username'] ?? '') === $user['username']) {
            $role = Database::getInstance()->fetch('SELECT chat_role FROM goldensite_chat_user_roles WHERE username = ?', [$user['username']]);
            if (in_array($role['chat_role'] ?? '', ['moderator', 'admin'], true)) return true;
        }
        return $room['access_type'] === 'public' || ($room['access_type'] === 'vip' && \App\Core\PaywallEngine::hasAccess());
    }

    public static function renderAdminView(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        self::initSchema();
        $db = Database::getInstance()->getConnection();

        $msgInfo = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['action_send_msg'])) {
                $sender  = trim($_POST['sender_name'] ?? 'Admin');
                $txt     = trim($_POST['chat_message'] ?? '');
                $channel = trim($_POST['chat_channel'] ?? 'general');
                $imgUrl  = trim($_POST['image_url'] ?? '');

                if (!empty($txt) || !empty($imgUrl)) {
                    $res = self::processMessage($sender, $txt, $channel, $imgUrl);
                    $msgInfo = $res['msg'];
                }
            } elseif (isset($_POST['action_add_room'])) {
                $roomName = trim($_POST['room_name'] ?? '');
                $roomSlug = strtolower(preg_replace('/[^a-z0-9]/', '', $roomName));
                if (!empty($roomSlug)) {
                    $stmt = $db->prepare("INSERT IGNORE INTO goldensite_chat_rooms (slug, name) VALUES (?, ?)");
                    $stmt->execute([$roomSlug, $roomName]);
                    $msgInfo = "Sala '$roomName' creada.";
                }
            } elseif (isset($_POST['action_save_banned_words'])) {
                $words = trim($_POST['banned_words'] ?? '');
                $stmt = $db->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES ('chat_banned_words', ?)");
                $stmt->execute([$words]);
                $msgInfo = "Lista de palabras prohibidas actualizada.";
            } elseif (isset($_POST['action_sanction_user'])) {
                $user = trim($_POST['target_user'] ?? '');
                $type = $_POST['sanction_type'] ?? 'mute';
                if (!empty($user)) {
                    $stmt = $db->prepare("INSERT INTO goldensite_chat_sanctions (username, type, reason, expires_at) VALUES (?, ?, 'Sanción administrativa', DATE_ADD(NOW(), INTERVAL 1 HOUR))");
                    $stmt->execute([$user, $type]);
                    $msgInfo = "Sanción ($type) aplicada a '$user'.";
                }
            }
        }

        $activeRoom = $_GET['room'] ?? 'general';
        $rooms      = self::getRooms();
        $messages   = self::getMessages($activeRoom);

        $bannedWordsVal = '';
        try {
            $r = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'chat_banned_words' LIMIT 1");
            if ($r) $bannedWordsVal = $r->fetchColumn() ?: implode(',', self::$defaultBanned);
        } catch (\Throwable $e) {}

        $sanctions = $db->query("SELECT * FROM goldensite_chat_sanctions ORDER BY id DESC LIMIT 20")->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $title = "Goldensite Live Chat & Engagement Console";
        ob_start();
        ?>
        <div style="padding: 1rem 0; color: #fff; font-family: system-ui, sans-serif;">
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: 900; margin-bottom: 0.2rem; color: #38bdf8;">💬 Consola Profesional de Live Chat</h1>
                    <p style="color: #94a3b8; font-size: 0.85rem;">Salas en tiempo real, moderación automática, emojis y control de usuarios.</p>
                </div>
            </div>

            <?php if (!empty($msgInfo)): ?>
                <div style="background: rgba(56, 189, 248, 0.15); border: 1px solid #38bdf8; color: #38bdf8; padding: 0.75rem; border-radius: 8px; font-weight: bold; margin-bottom: 1rem; font-size: 0.85rem;">
                    <?= htmlspecialchars($msgInfo) ?>
                </div>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: 240px 1fr 280px; gap: 1.25rem;">
                
                <!-- SALAS Y NAVEGACIÓN -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1rem;">
                    <h3 style="font-size: 0.85rem; text-transform: uppercase; color: #38bdf8; margin-bottom: 0.75rem;">🏠 Salas de Chat</h3>
                    <div style="display: flex; flex-direction: column; gap: 0.4rem; margin-bottom: 1.5rem;">
                        <?php foreach ($rooms as $r): ?>
                            <a href="/admin/live-chat?room=<?= $r['slug'] ?>" style="display: block; padding: 0.6rem; border-radius: 6px; text-decoration: none; font-size: 0.85rem; font-weight: bold; background: <?= $activeRoom === $r['slug'] ? '#38bdf8' : '#0f172a' ?>; color: <?= $activeRoom === $r['slug'] ? '#000' : '#cbd5e1' ?>;">
                                <?= htmlspecialchars($r['name']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <h3 style="font-size: 0.85rem; text-transform: uppercase; color: #38bdf8; margin-bottom: 0.5rem;">➕ Nueva Sala</h3>
                    <form method="POST">
                        <input type="hidden" name="action_add_room" value="1">
                        <input type="text" name="room_name" placeholder="Nombre de sala..." required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.5rem; border-radius: 6px; font-size: 0.8rem; margin-bottom: 0.5rem;">
                        <button type="submit" style="width: 100%; background: #38bdf8; color: #000; font-weight: bold; border: none; padding: 0.5rem; border-radius: 6px; cursor: pointer; font-size: 0.8rem;">Crear Sala</button>
                    </form>
                </div>

                <!-- SALA DE MENSAJES PRINCIPAL -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                    <div>
                        <div style="border-bottom: 1px solid #334155; padding-bottom: 0.75rem; margin-bottom: 1rem; display: flex; justify-content: space-between; align-items: center;">
                            <strong style="color: #fff; font-size: 1rem;">Sala Activa: <span style="color: #38bdf8;"><?= htmlspecialchars(strtoupper($activeRoom)) ?></span></strong>
                            <span style="background: #22c55e; color: #000; font-weight: 900; font-size: 0.65rem; padding: 0.15rem 0.4rem; border-radius: 4px;">EN VIVO</span>
                        </div>

                        <!-- HISTORIAL DE MENSAJES -->
                        <div style="height: 380px; overflow-y: auto; background: #0f172a; border-radius: 8px; padding: 1rem; display: flex; flex-direction: column; gap: 0.75rem; margin-bottom: 1rem;">
                            <?php if (!empty($messages)): ?>
                                <?php foreach ($messages as $m): ?>
                                    <div style="background: #1e293b; padding: 0.6rem 0.8rem; border-radius: 8px; border-left: 3px solid #38bdf8;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.2rem;">
                                            <strong style="color: #a7f3d0; font-size: 0.8rem;"><?= htmlspecialchars($m['sender_name']) ?></strong>
                                            <small style="color: #64748b; font-size: 0.7rem;"><?= $m['created_at'] ?></small>
                                        </div>
                                        <div style="color: #f8fafc; font-size: 0.85rem; word-break: break-word;"><?= htmlspecialchars($m['message']) ?></div>
                                        <?php if (!empty($m['image_url'])): ?>
                                            <img src="<?= htmlspecialchars($m['image_url']) ?>" alt="adjunto" style="max-height: 120px; border-radius: 6px; margin-top: 0.5rem; display: block;">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: #64748b; text-align: center; margin-top: 160px; font-size: 0.85rem;">No hay mensajes registrados en esta sala.</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- FORMULARIO DE ENVÍO Y EMOJIS -->
                    <form method="POST" style="display: flex; flex-direction: column; gap: 0.5rem;">
                        <input type="hidden" name="action_send_msg" value="1">
                        <input type="hidden" name="chat_channel" value="<?= htmlspecialchars($activeRoom) ?>">

                        <!-- BARRA DE EMOJIS RÁPIDOS -->
                        <div style="display: flex; gap: 0.4rem; font-size: 1.1rem; cursor: pointer;">
                            <span onclick="addEmoji('👍')">👍</span>
                            <span onclick="addEmoji('❤️')">❤️</span>
                            <span onclick="addEmoji('🚀')">🚀</span>
                            <span onclick="addEmoji('🔥')">🔥</span>
                            <span onclick="addEmoji('😊')">😊</span>
                            <span onclick="addEmoji('🎉')">🎉</span>
                        </div>

                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" id="chatInput" name="chat_message" placeholder="Escribe tu mensaje aquí..." style="flex: 1; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.85rem; outline: none;">
                            <input type="url" name="image_url" placeholder="URL imagen (opcional)" style="width: 180px; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.8rem; outline: none;">
                            <button type="submit" style="background: #38bdf8; color: #000; font-weight: 900; border: none; padding: 0.65rem 1.25rem; border-radius: 6px; cursor: pointer; font-size: 0.85rem;">Enviar 🚀</button>
                        </div>
                    </form>
                </div>

                <!-- CONSOLA DE MODERACIÓN Y SANCIONES -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1rem;">
                    <h3 style="font-size: 0.85rem; text-transform: uppercase; color: #ef4444; margin-bottom: 0.75rem;">🛡️ Auto-Moderador</h3>
                    
                    <form method="POST" style="margin-bottom: 1.5rem;">
                        <input type="hidden" name="action_save_banned_words" value="1">
                        <label style="display: block; font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem;">Palabras Prohibidas (separadas por coma):</label>
                        <textarea name="banned_words" rows="3" style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fca5a5; padding: 0.5rem; border-radius: 6px; font-size: 0.75rem; margin-bottom: 0.5rem; font-family: monospace;"><?= htmlspecialchars($bannedWordsVal) ?></textarea>
                        <button type="submit" style="width: 100%; background: #ef4444; color: #fff; font-weight: bold; border: none; padding: 0.45rem; border-radius: 6px; cursor: pointer; font-size: 0.75rem;">Guardar Filtro</button>
                    </form>

                    <h3 style="font-size: 0.85rem; text-transform: uppercase; color: #f59e0b; margin-bottom: 0.5rem;">⚡ Sancionar Usuario</h3>
                    <form method="POST" style="margin-bottom: 1rem;">
                        <input type="hidden" name="action_sanction_user" value="1">
                        <input type="text" name="target_user" placeholder="Nombre de usuario..." required style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.45rem; border-radius: 6px; font-size: 0.75rem; margin-bottom: 0.4rem;">
                        <select name="sanction_type" style="width: 100%; background: #0f172a; border: 1px solid #334155; color: #fff; padding: 0.45rem; border-radius: 6px; font-size: 0.75rem; margin-bottom: 0.5rem;">
                            <option value="mute">Mute (Silenciar 1 hora)</option>
                            <option value="ban">Ban (Suspender)</option>
                        </select>
                        <button type="submit" style="width: 100%; background: #f59e0b; color: #000; font-weight: bold; border: none; padding: 0.45rem; border-radius: 6px; cursor: pointer; font-size: 0.75rem;">Aplicar Sanción</button>
                    </form>

                    <h4 style="font-size: 0.75rem; color: #94a3b8; margin-bottom: 0.4rem;">Últimas Sanciones:</h4>
                    <div style="max-height: 100px; overflow-y: auto; font-size: 0.7rem; color: #cbd5e1;">
                        <?php foreach ($sanctions as $s): ?>
                            <div style="background: #0f172a; padding: 0.3rem; border-radius: 4px; margin-bottom: 0.25rem;">
                                <strong><?= htmlspecialchars($s['username']) ?></strong> - <?= $s['type'] ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <script>
        function addEmoji(emoji) {
            var input = document.getElementById('chatInput');
            input.value += ' ' + emoji;
            input.focus();
        }
        </script>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../views/layouts/admin.php';
    }
}
