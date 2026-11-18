<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

class CommentsController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();

        // Procesar moderación
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['comment_status'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/comments');
                exit;
            }

            $action = $_POST['action'] ?? '';
            $id     = (int)($_POST['comment_id'] ?? 0);

            if ($id > 0) {
                if ($action === 'delete') {
                    $stmt = $db->prepare("DELETE FROM goldensite_comments WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $_SESSION['comment_status'] = ['type' => 'success', 'msg' => "Comentario #{$id} eliminado."];
                } elseif ($action === 'approve') {
                    $stmt = $db->prepare("UPDATE goldensite_comments SET status = 'approved' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $_SESSION['comment_status'] = ['type' => 'success', 'msg' => "Comentario #{$id} aprobado."];
                } elseif ($action === 'reject') {
                    $stmt = $db->prepare("UPDATE goldensite_comments SET status = 'rejected' WHERE id = :id");
                    $stmt->execute(['id' => $id]);
                    $_SESSION['comment_status'] = ['type' => 'success', 'msg' => "Comentario #{$id} rechazado."];
                }
            }

            header('Location: /admin/comments');
            exit;
        }

        $comments = [];
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_comments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                post_id INT NOT NULL,
                author_name VARCHAR(100) NOT NULL,
                author_email VARCHAR(150) NOT NULL,
                comment_text TEXT NOT NULL,
                status VARCHAR(20) DEFAULT 'approved',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX(post_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmt = $db->query("SELECT c.*, p.title as post_title FROM goldensite_comments c LEFT JOIN posts p ON c.post_id = p.id ORDER BY c.id DESC");
            $comments = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {}

        $status = $_SESSION['comment_status'] ?? null;
        unset($_SESSION['comment_status']);

        $csrfToken = Csrf::generate();
        $title     = "Moderación de Comentarios - GoldenSiteCMS";

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0; color: #fff;">
            <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; margin-bottom: 0.25rem;">
                        💬 Moderación de Comentarios
                    </h1>
                    <p style="color: #94a3b8; font-size: 0.95rem;">Gestiona y modera la interacción de los usuarios en el blog.</p>
                </div>
            </div>

            <?php if (!empty($status)): ?>
                <div style="background: <?= $status['type'] === 'success' ? '#15803d' : '#b91c1c' ?>; color: #fff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold;">
                    <?= $status['type'] === 'success' ? '✅' : '⚠️' ?> <?= htmlspecialchars($status['msg']) ?>
                </div>
            <?php endif; ?>

            <div style="background: #1e293b; border-radius: 12px; border: 1px solid #334155; padding: 1.5rem;">
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #334155; color: #94a3b8; font-size: 0.8rem; text-transform: uppercase;">
                                <th style="padding: 0.75rem;">ID / Fecha</th>
                                <th style="padding: 0.75rem;">Autor</th>
                                <th style="padding: 0.75rem;">Publicación / Comentario</th>
                                <th style="padding: 0.75rem;">Estado</th>
                                <th style="padding: 0.75rem; text-align: right;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($comments)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 2rem; text-align: center; color: #94a3b8;">No hay comentarios registrados.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($comments as $c): 
                                    $st = $c['status'] ?? 'approved';
                                ?>
                                    <tr style="border-bottom: 1px solid #334155;">
                                        <td style="padding: 0.75rem; color: #94a3b8; font-size: 0.8rem;">
                                            <strong>#<?= (int)$c['id'] ?></strong>
                                            <div style="color: #64748b; font-size: 0.75rem;"><?= htmlspecialchars($c['created_at']) ?></div>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <strong style="color: #fff; font-size: 0.9rem;"><?= htmlspecialchars($c['author_name']) ?></strong>
                                            <div style="color: #38bdf8; font-size: 0.8rem;"><?= htmlspecialchars($c['author_email']) ?></div>
                                        </td>
                                        <td style="padding: 0.75rem; max-width: 320px;">
                                            <div style="color: #fbbf24; font-weight: bold; font-size: 0.85rem; margin-bottom: 0.25rem;">
                                                📖 <?= htmlspecialchars($c['post_title'] ?? 'Entrada ID #' . $c['post_id']) ?>
                                            </div>
                                            <div style="color: #cbd5e1; font-size: 0.8rem; line-height: 1.4; white-space: pre-wrap;"><?= htmlspecialchars($c['comment_text']) ?></div>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <?php if ($st === 'approved'): ?>
                                                <span style="background: #15803d; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">APROBADO</span>
                                            <?php elseif ($st === 'rejected'): ?>
                                                <span style="background: #b91c1c; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">RECHAZADO</span>
                                            <?php else: ?>
                                                <span style="background: #0284c7; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">PENDIENTE</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem; text-align: right; white-space: nowrap;">
                                            <?php if ($st !== 'approved'): ?>
                                                <form action="/admin/comments" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                                    
                                                    <input type="hidden" name="action" value="approve">
                                                    <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                                    <button type="submit" style="background: #22c55e; color: #0f172a; border: none; padding: 0.35rem 0.6rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: bold; margin-right: 0.25rem;">Aprobar</button>
                                                </form>
                                            <?php endif; ?>

                                            <?php if ($st !== 'rejected'): ?>
                                                <form action="/admin/comments" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                                    
                                                    <input type="hidden" name="action" value="reject">
                                                    <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                                    <button type="submit" style="background: #f59e0b; color: #0f172a; border: none; padding: 0.35rem 0.6rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: bold; margin-right: 0.25rem;">Rechazar</button>
                                                </form>
                                            <?php endif; ?>

                                            <form action="/admin/comments" method="POST" style="display: inline;" onsubmit="return confirm('¿Seguro que deseas eliminar este comentario?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                                
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="comment_id" value="<?= (int)$c['id'] ?>">
                                                <button type="submit" style="background: #ef4444; color: #fff; border: none; padding: 0.35rem 0.6rem; border-radius: 4px; font-size: 0.75rem; cursor: pointer; font-weight: bold;">Eliminar</button>
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