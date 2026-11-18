<?php

namespace App\Controllers;

use App\Core\Database;
use App\Core\Csrf;
use App\Core\Settings;

class CartController
{
    /**
     * Valida la licencia PRO del módulo Goldensite Commerce & Sales Engine
     */
    public static function isSalesLicenseValid(): bool
    {
        return \App\Core\FrontendEngine::isModuleActive('commerce-sales');
    }

    /**
     * Muestra la vista del Carrito de Compras
     */
    public function index(): void
    {
        if (!self::isSalesLicenseValid()) {
            $this->renderProBlockedNotice();
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cart = $_SESSION['cart'] ?? [];
        $total = array_reduce($cart, fn($sum, $item) => $sum + ($item['price'] * $item['quantity']), 0.0);

        $siteTitle = "Carrito de Compras - " . Settings::get('site_name', 'GoldenSiteCMS');
        $activeTheme = Settings::get('active_theme', 'dark-cyber');

        $cartViewPath = __DIR__ . '/../../views/frontend/cart.php';
        if (file_exists($cartViewPath)) {
            require_once $cartViewPath;
        } else {
            echo "Vista del carrito no encontrada.";
        }
    }

    /**
     * Agrega un producto al carrito
     */
    public function add(): void
    {
        \App\Core\RequestGuard::post();
        if (!self::isSalesLicenseValid()) {
            $this->renderProBlockedNotice();
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $productId = (int)($_POST['product_id'] ?? 0);
            $quantity = min(100, max(1, (int)($_POST['quantity'] ?? 1)));

            try {
                $db = Database::getInstance()->getConnection();
                $stmt = $db->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $productId]);
                $product = $stmt->fetch();

                if ($product) {
                    if (!isset($_SESSION['cart'])) {
                        $_SESSION['cart'] = [];
                    }

                    if (isset($_SESSION['cart'][$productId])) {
                        $_SESSION['cart'][$productId]['quantity'] = min(100, $_SESSION['cart'][$productId]['quantity'] + $quantity);
                    } else {
                        $_SESSION['cart'][$productId] = [
                            'id' => $product['id'],
                            'name' => $product['name'],
                            'price' => (float)$product['price'],
                            'image_url' => $product['image_url'] ?? '',
                            'quantity' => $quantity
                        ];
                    }
                }
            } catch (\Throwable $e) {}
        }

        header('Location: /cart');
        exit;
    }

    /**
     * Elimina un producto del carrito
     */
    public function remove(): void
    {
        \App\Core\RequestGuard::post();
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $productId = (int)($_POST['id'] ?? $_POST['product_id'] ?? 0);
        if (isset($_SESSION['cart'][$productId])) {
            unset($_SESSION['cart'][$productId]);
        }

        header('Location: /cart');
        exit;
    }

    /**
     * Finalización de compra y registro de orden
     */
    public function checkout(): void
    {
        if (!self::isSalesLicenseValid()) {
            $this->renderProBlockedNotice();
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cart = $_SESSION['cart'] ?? [];
        if (empty($cart)) {
            header('Location: /cart');
            exit;
        }

        $status = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (class_exists('\App\Core\Csrf') && !Csrf::validate($_POST['csrf_token'] ?? '')) {
                $status = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
            } else {
                $customerName = trim($_POST['customer_name'] ?? '');
                $customerEmail = trim($_POST['customer_email'] ?? '');
                $total = array_reduce($cart, fn($sum, $item) => $sum + ($item['price'] * $item['quantity']), 0.0);

                if (!empty($customerName) && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                    try {
                        $db = Database::getInstance()->getConnection();
                        $total = \App\Core\CartPricing::total($db, $cart);
                        
                        $db->exec("CREATE TABLE IF NOT EXISTS orders (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            customer_name VARCHAR(150) NOT NULL,
                            customer_email VARCHAR(150) NOT NULL,
                            total DECIMAL(10,2) NOT NULL,
                            status VARCHAR(20) DEFAULT 'pending',
                            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

                        $stmt = $db->prepare("INSERT INTO orders (customer_name, customer_email, total, status) VALUES (:name, :email, :total, 'pending')");
                        $stmt->execute([
                            'name' => $customerName,
                            'email' => $customerEmail,
                            'total' => $total
                        ]);

                        $_SESSION['cart'] = [];
                        $orderSuccess = true;
                    } catch (\Throwable $e) {
                        $status = ['type' => 'danger', 'msg' => 'Error al guardar la orden en la base de datos.'];
                    }
                } else {
                    $status = ['type' => 'danger', 'msg' => 'Por favor completa todos los campos requeridos.'];
                }
            }
        }

        $siteTitle = "Finalizar Compra - " . Settings::get('site_name', 'GoldenSiteCMS');
        $checkoutViewPath = __DIR__ . '/../../views/frontend/checkout.php';
        if (file_exists($checkoutViewPath)) {
            require_once $checkoutViewPath;
        } else {
            echo "Vista de checkout no encontrada.";
        }
    }

    /**
     * Muestra la plantilla visual de bloqueo de licencia PRO
     */
    private function renderProBlockedNotice(): void
    {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Módulo Sales Engine PRO Requerido</title></head>';
        echo '<body style="background:#0f172a; color:#f59e0b; font-family:sans-serif; text-align:center; padding:5rem 1rem;">';
        echo '<div style="background:#1e293b; border:2px solid #f59e0b; border-radius:12px; max-width:550px; margin:0 auto; padding:2.5rem;">';
        echo '<h1 style="font-size:2rem; margin-bottom:1rem; color:#fff;">🔒 Carrito & Sales Engine Bloqueado</h1>';
        echo '<p style="color:#cbd5e1; font-size:1rem; line-height:1.6; margin-bottom:1.5rem;">El procesamiento de carrito de compras requiere una suscripción activa con el precio vigente del catálogo al módulo <strong>Goldensite Commerce & Sales Engine</strong>.</p>';
        echo '<a href="/admin/plugins" style="background:#f59e0b; color:#0f172a; padding:0.6rem 1.2rem; border-radius:6px; font-weight:bold; text-decoration:none; display:inline-block;">Ingresar Licencia PRO</a>';
        echo '</div></body></html>';
        exit;
    }
}
