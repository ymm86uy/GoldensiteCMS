<?php
namespace App\Core;

final class CartPricing
{
    public static function total(\PDO $db, array $cart): string
    {
        if (count($cart) > 100) throw new \InvalidArgumentException('Demasiados productos.');
        $cents = 0;
        $stmt = $db->prepare('SELECT price FROM products WHERE id = ? LIMIT 1');
        foreach ($cart as $item) {
            $quantity = filter_var($item['quantity'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
            if ($quantity === false) throw new \InvalidArgumentException('Cantidad inválida.');
            $stmt->execute([(int) ($item['id'] ?? 0)]);
            $price = $stmt->fetchColumn();
            if (!is_string($price) || !preg_match('/^(\d+)\.(\d{2})$/D', $price, $match)) throw new \RuntimeException('Producto no disponible.');
            $cents += ((int) $match[1] * 100 + (int) $match[2]) * $quantity;
            if ($cents > 9999999999) throw new \InvalidArgumentException('Importe fuera de rango.');
        }
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
