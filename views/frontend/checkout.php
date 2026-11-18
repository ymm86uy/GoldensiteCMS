<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($siteTitle ?? 'Checkout - GoldenSiteCMS') ?></title>
    <link rel="icon" type="image/svg+xml" href="/assets/img/icon.svg">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }
        body { background: #090d16; color: #f1f5f9; min-height: 100vh; display: flex; flex-direction: column; }
        header { background: #0f172a; border-bottom: 1px solid #1e293b; padding: 1.25rem 2rem; display: flex; justify-content: space-between; align-items: center; }
        header a { color: #38bdf8; text-decoration: none; font-weight: bold; font-size: 1.1rem; }
        .container { max-width: 950px; margin: 2.5rem auto; padding: 0 1.5rem; flex: 1; width: 100%; }
        h1 { font-size: 1.8rem; color: #f8fafc; margin-bottom: 1.5rem; }
        .checkout-grid { display: grid; grid-template-columns: 1fr 360px; gap: 2rem; }
        @media (max-width: 768px) { .checkout-grid { grid-template-columns: 1fr; } }
        .card { background: #1e293b; border: 1px solid #334155; padding: 1.75rem; border-radius: 12px; }
        .form-group { margin-bottom: 1.25rem; }
        .form-group label { display: block; font-size: 0.85rem; font-weight: bold; color: #cbd5e1; margin-bottom: 0.5rem; }
        .form-group input { width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.75rem 1rem; border-radius: 8px; font-size: 0.95rem; outline: none; }
        .form-group input:focus { border-color: #38bdf8; }
        .summary-item { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid #334155; font-size: 0.9rem; color: #cbd5e1; }
        .summary-total { display: flex; justify-content: space-between; padding-top: 1rem; font-size: 1.3rem; font-weight: bold; color: #38bdf8; }
        .btn-submit { width: 100%; background: linear-gradient(135deg, #38bdf8, #0284c7); color: #0f172a; border: none; padding: 1rem; font-weight: bold; font-size: 1rem; border-radius: 8px; cursor: pointer; margin-top: 1rem; }
        .btn-submit:hover { opacity: 0.9; }
        .alert { padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: bold; }
        .alert-danger { background: rgba(239, 68, 68, 0.2); border: 1px solid rgba(239, 68, 68, 0.4); color: #f87171; }
        .success-card { text-align: center; padding: 3rem 1.5rem; }
        .success-card h2 { font-size: 2rem; color: #22c55e; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <header>
        <a href="/cart">← Volver al Carrito</a>
    </header>

    <div class="container">
        <?php if (!empty($orderSuccess)): ?>
            <div class="card success-card">
                <div style="font-size: 3.5rem; margin-bottom: 1rem;">🎉</div>
                <h2>Solicitud registrada; pago pendiente</h2>
                <p style="color: #94a3b8; margin-bottom: 2rem; font-size: 0.95rem;">Hemos registrado tu solicitud correctamente. El pedido queda pendiente de pago y confirmación.</p>
                <a href="/" style="background: #38bdf8; color: #0f172a; padding: 0.75rem 1.5rem; border-radius: 8px; text-decoration: none; font-weight: bold;">Volver a la Tienda</a>
            </div>
        <?php else: ?>
            <h1>Finalizar Compra</h1>

            <?php if (!empty($status)): ?>
                <div class="alert alert-danger">
                    ⚠️ <?= htmlspecialchars($status['msg']) ?>
                </div>
            <?php endif; ?>

            <div class="checkout-grid">
                <!-- Formulario de Datos del Cliente -->
                <div class="card">
                    <h2 style="font-size: 1.2rem; color: #fff; margin-bottom: 1.25rem;">Información del Comprador</h2>
                    <form action="/checkout" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        
                        <div class="form-group">
                            <label>Nombre Completo:</label>
                            <input type="text" name="customer_name" required placeholder="Juan Pérez">
                        </div>

                        <div class="form-group">
                            <label>Correo Electrónico:</label>
                            <input type="email" name="customer_email" required placeholder="juan@ejemplo.com">
                        </div>

                        <button type="submit" class="btn-submit">Registrar pedido pendiente</button>
                    </form>
                </div>

                <!-- Resumen de Pedido -->
                <div class="card" style="height: fit-content;">
                    <h2 style="font-size: 1.2rem; color: #fff; margin-bottom: 1rem;">Resumen de Compra</h2>
                    <?php 
                        $totalCalc = 0;
                        foreach ($cart as $item): 
                            $subtotal = $item['price'] * $item['quantity'];
                            $totalCalc += $subtotal;
                    ?>
                        <div class="summary-item">
                            <span><?= htmlspecialchars($item['name']) ?> (x<?= $item['quantity'] ?>)</span>
                            <strong>$<?= number_format($subtotal, 2) ?></strong>
                        </div>
                    <?php endforeach; ?>

                    <div class="summary-total">
                        <span>Total:</span>
                        <span>$<?= number_format($totalCalc, 2) ?></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>