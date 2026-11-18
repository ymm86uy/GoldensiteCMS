<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

class SalesController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();

        // Actualizar estado de una orden desde el backend
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['sales_status'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/sales');
                exit;
            }

            $orderId   = (int)($_POST['order_id'] ?? 0);
            $newStatus = trim($_POST['status'] ?? 'completed');

            if ($orderId > 0 && in_array($newStatus, ['completed', 'pending', 'cancelled'], true)) {
                try {
                    $stmt = $db->prepare("UPDATE orders SET status = :status WHERE id = :id");
                    $stmt->execute(['status' => $newStatus, 'id' => $orderId]);
                    $_SESSION['sales_status'] = ['type' => 'success', 'msg' => "Estado de la Orden #{$orderId} actualizado a '{$newStatus}'."];
                } catch (\Throwable $e) {
                    $_SESSION['sales_status'] = ['type' => 'danger', 'msg' => 'Error al actualizar el estado de la orden.'];
                }
            }

            header('Location: /admin/sales');
            exit;
        }

        $orders = [];
        $totalRevenue = 0.0;

        try {
            $db->exec("CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_name VARCHAR(150) NOT NULL,
                customer_email VARCHAR(150) NOT NULL,
                total DECIMAL(10,2) NOT NULL,
                status VARCHAR(20) DEFAULT 'completed',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmt = $db->query("SELECT * FROM orders ORDER BY id DESC");
            $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            foreach ($orders as $o) {
                if (($o['status'] ?? '') === 'completed') {
                    $totalRevenue += (float)$o['total'];
                }
            }
        } catch (\Throwable $e) {}

        $status = $_SESSION['sales_status'] ?? null;
        unset($_SESSION['sales_status']);

        $csrfToken = Csrf::generate();
        $title     = "Ventas & Catálogo - GoldenSiteCMS";

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0; color: #fff;">
            <div style="margin-bottom: 2rem; display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; margin-bottom: 0.25rem;">
                        🛍️ Gestión de Ventas & Órdenes
                    </h1>
                    <p style="color: #94a3b8; font-size: 0.95rem;">Panel de control comercial del motor Goldensite Commerce Engine.</p>
                </div>
                <div style="background: #0f172a; border: 1px solid #334155; padding: 0.75rem 1.25rem; border-radius: 10px; text-align: right;">
                    <span style="color: #94a3b8; font-size: 0.75rem; display: block; uppercase;">Ingresos Totales (Completados)</span>
                    <strong style="color: #38bdf8; font-size: 1.25rem;">$<?= number_format($totalRevenue, 2) ?></strong>
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
                                <th style="padding: 0.75rem;">ID Orden / Fecha</th>
                                <th style="padding: 0.75rem;">Cliente</th>
                                <th style="padding: 0.75rem;">Monto Total</th>
                                <th style="padding: 0.75rem;">Estado</th>
                                <th style="padding: 0.75rem; text-align: right;">Cambiar Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="5" style="padding: 2rem; text-align: center; color: #94a3b8;">No se han registrado transacciones aún.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $ord): 
                                    $st = $ord['status'] ?? 'completed';
                                ?>
                                    <tr style="border-bottom: 1px solid #334155;">
                                        <td style="padding: 0.75rem; color: #94a3b8; font-size: 0.8rem;">
                                            <strong>#<?= (int)$ord['id'] ?></strong>
                                            <div style="color: #64748b; font-size: 0.75rem;"><?= htmlspecialchars($ord['created_at']) ?></div>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <strong style="color: #fff; font-size: 0.9rem;"><?= htmlspecialchars($ord['customer_name']) ?></strong>
                                            <div style="color: #38bdf8; font-size: 0.8rem;"><?= htmlspecialchars($ord['customer_email']) ?></div>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <strong style="color: #38bdf8; font-size: 1rem;">$<?= number_format((float)$ord['total'], 2) ?></strong>
                                        </td>
                                        <td style="padding: 0.75rem;">
                                            <?php if ($st === 'completed'): ?>
                                                <span style="background: #15803d; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">COMPLETADO</span>
                                            <?php elseif ($st === 'pending'): ?>
                                                <span style="background: #0284c7; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">PENDIENTE</span>
                                            <?php else: ?>
                                                <span style="background: #b91c1c; color: #fff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold;">CANCELADO</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 0.75rem; text-align: right;">
                                            <form action="/admin/sales" method="POST" style="display: inline-flex; gap: 0.5rem; align-items: center;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                                
                                                <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>">
                                                
                                                <select name="status" style="background: #0f172a; border: 1px solid #475569; color: #fff; font-size: 0.8rem; padding: 0.35rem; border-radius: 6px; outline: none;">
                                                    <option value="completed" <?= $st === 'completed' ? 'selected' : '' ?>>Completado</option>
                                                    <option value="pending" <?= $st === 'pending' ? 'selected' : '' ?>>Pendiente</option>
                                                    <option value="cancelled" <?= $st === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                                                </select>

                                                <button type="submit" style="background: #38bdf8; color: #0f172a; border: none; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.75rem; font-weight: bold; cursor: pointer;">Guardar</button>
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