<?php
namespace App\Core;

final class MarketplaceReservations
{
    public static function reserve(int $buyer, int $product, int $quantity, string $token): int
    {
        if ($buyer < 1 || $quantity < 1 || $quantity > 20 || !preg_match('/^[a-f0-9]{32}$/D', $token)) {
            throw new \InvalidArgumentException('Revisa la cantidad y vuelve a abrir la ficha del producto.');
        }
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $db->fetch('SELECT id FROM users WHERE id=? FOR UPDATE', [$buyer]);
            $item = $db->fetch("SELECT p.*, v.store_name, v.status vendor_status
                FROM goldensite_marketplace_products p JOIN goldensite_vendors v ON v.id=p.vendor_id
                WHERE p.id=? FOR UPDATE", [$product]);
            $existing = $db->fetch('SELECT id, buyer_id, product_id, quantity FROM goldensite_marketplace_reservations WHERE request_token=?', [$token]);
            if ($existing) {
                if ((int)$existing['buyer_id'] !== $buyer || (int)$existing['product_id'] !== $product || (int)$existing['quantity'] !== $quantity) {
                    throw new \InvalidArgumentException('Abre una nueva ficha para realizar otra reserva.');
                }
                $pdo->commit();
                return (int)$existing['id'];
            }
            if (!$item || $item['status'] !== 'published' || $item['vendor_status'] !== 'active' || (int)$item['stock'] < $quantity) {
                throw new \InvalidArgumentException('El producto ya no tiene esa cantidad disponible.');
            }
            $pending = $db->fetch("SELECT COUNT(*) total FROM goldensite_marketplace_reservations WHERE buyer_id=? AND status='pending'", [$buyer]);
            if ((int)$pending['total'] >= 5) {
                throw new \InvalidArgumentException('Tu cuenta ya tiene cinco reservas pendientes.');
            }
            $unit = Money::cents((string)$item['price']);
            if ($unit < 1) {
                throw new \InvalidArgumentException('El precio requiere revisión de administración.');
            }
            $db->execute('INSERT INTO goldensite_marketplace_reservations
                (request_token,buyer_id,product_id,vendor_id,product_title,store_name,quantity,total_cents)
                VALUES (?,?,?,?,?,?,?,?)', [$token,$buyer,$product,$item['vendor_id'],$item['title'],$item['store_name'],$quantity,$unit*$quantity]);
            $id = (int)$db->lastInsertId();
            $db->execute('UPDATE goldensite_marketplace_products SET stock=stock-? WHERE id=?', [$quantity,$product]);
            $pdo->commit();
            return $id;
        } catch (\Throwable $error) {
            $pdo->rollBack();
            if ($error instanceof \PDOException && (int)($error->errorInfo[1] ?? 0) === 1062) {
                throw new \InvalidArgumentException('Esta solicitud ya se procesó. Consulta tus reservas.');
            }
            throw $error;
        }
    }

    public static function resolve(int $id, string $state, ?int $buyer = null): void
    {
        if (!in_array($state, ['fulfilled','cancelled'], true) || ($buyer !== null && $state !== 'cancelled')) {
            throw new \InvalidArgumentException('Operación inválida.');
        }
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $row = $db->fetch('SELECT * FROM goldensite_marketplace_reservations WHERE id=? FOR UPDATE', [$id]);
            if (!$row || ($buyer !== null && (int)$row['buyer_id'] !== $buyer) || $row['status'] !== 'pending') {
                throw new \InvalidArgumentException('La reserva ya no está pendiente o no está disponible.');
            }
            if ($state === 'cancelled') {
                $db->execute('UPDATE goldensite_marketplace_products SET stock=stock+? WHERE id=?', [$row['quantity'],$row['product_id']]);
            }
            $db->execute('UPDATE goldensite_marketplace_reservations SET status=? WHERE id=?', [$state,$id]);
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
