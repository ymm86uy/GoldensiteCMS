<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle) ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/icon.svg">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }
        body { background: #090d16; color: #f1f5f9; min-height: 100vh; display: flex; flex-direction: column; }
        header { background: #0f172a; border-bottom: 1px solid #1e293b; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #38bdf8; text-decoration: none; font-weight: bold; font-size: 1.1rem; }
        .container { max-width: 900px; margin: 2.5rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
        h1 { font-size: 1.8rem; color: #f8fafc; margin-bottom: 1.5rem; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 2rem; background: #1e293b; border-radius: 8px; overflow: hidden; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #334155; }
        th { background: #0f172a; color: #94a3b8; font-size: 0.85rem; text-transform: uppercase; }
        .product-cell { display: flex; align-items: center; gap: 1rem; }
        .product-cell img { width: 48px; height: 48px; border-radius: 6px; object-fit: cover; }
        .cart-summary { background: #1e293b; border: 1px solid #334155; padding: 1.5rem; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; }
        .total-price { font-size: 1.4rem; font-weight: bold; color: #38bdf8; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; border-radius: 6px; font-weight: bold; text-decoration: none; cursor: pointer; border: none; }
        .btn-primary { background: #0284c7; color: #fff; }
        .btn-danger { color: #ef4444; font-size: 0.85rem; text-decoration: none; }
        .empty-msg { text-align: center; padding: 3rem; background: #1e293b; border-radius: 8px; color: #94a3b8; }
    </style>
</head>
<body>
    <header>
        <a href="/">← <?= htmlspecialchars(\App\Core\Settings::get('site_name', 'GoldenSiteCMS')) ?></a>
    </header>

    <div class="container">
        <h1>Tu Carrito de Compras</h1>

        <?php if (!empty($cart)): ?>
            <table>
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Cantidad</th>
                        <th>Subtotal</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cart as $item): ?>
                        <tr>
                            <td class="product-cell">
                                <?php if (!empty($item['image_url'])): ?>
                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="Producto">
                                <?php endif; ?>
                                <span><?= htmlspecialchars($item['name']) ?></span>
                            </td>
                            <td>$<?= number_format($item['price'], 2) ?></td>
                            <td><?= $item['quantity'] ?></td>
                            <td>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            <td><form action="/cart/remove" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="btn-danger">Eliminar</button></form></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="cart-summary">
                <div>
                    <span style="color: #94a3b8; display: block; font-size: 0.9rem;">Total a Pagar</span>
                    <span class="total-price">$<?= number_format($total, 2) ?></span>
                </div>
                <a href="/checkout" class="btn btn-primary">Proceder al Pago →</a>
            </div>
        <?php else: ?>
            <div class="empty-msg">
                <p style="margin-bottom: 1rem;">Tu carrito está vacío.</p>
                <a href="/" class="btn btn-primary">Explorar Productos</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
