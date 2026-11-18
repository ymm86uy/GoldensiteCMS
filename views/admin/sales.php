<?php use App\Core\Csrf; ?>
<div style="margin-bottom: 2rem;">
    <h1 style="font-size: 1.8rem; color: #f8fafc; margin-bottom: 0.5rem;">Ventas & Catálogo de Productos</h1>
    <p style="color: #94a3b8;">Administra los productos de la tienda online.</p>
</div>

<?php if (isset($status)): ?>
    <div style="padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; background: <?= $status['type'] === 'success' ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' ?>; border: 1px solid <?= $status['type'] === 'success' ? '#10b981' : '#ef4444' ?>; color: <?= $status['type'] === 'success' ? '#6ee7b7' : '#fca5a5' ?>;">
        <?= htmlspecialchars($status['msg']) ?>
    </div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem; height: fit-content;">
        <h2 style="font-size: 1.2rem; color: #38bdf8; margin-bottom: 1.2rem;">Nuevo Producto</h2>
        <form action="/admin/sales" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
            
            <input type="hidden" name="action" value="create_product">
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.4rem;">Nombre del Producto</label>
                <input type="text" name="name" required style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.4rem;">Precio ($)</label>
                <input type="number" step="0.01" name="price" required style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.4rem;">URL de Imagen (Opcional)</label>
                <input type="text" name="image_url" placeholder="https://..." style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
            </div>
            <div style="margin-bottom: 1rem;">
                <label style="display: block; font-size: 0.85rem; color: #94a3b8; margin-bottom: 0.4rem;">Descripción</label>
                <textarea name="description" rows="3" style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;"></textarea>
            </div>
            <button type="submit" style="width: 100%; padding: 0.75rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Guardar Producto</button>
        </form>
    </div>

    <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem;">
        <h2 style="font-size: 1.2rem; color: #38bdf8; margin-bottom: 1.2rem;">Productos en Catálogo</h2>
        <?php if (empty($products)): ?>
            <p style="color: #64748b;">No hay productos registrados en la tienda.</p>
        <?php else: ?>
            <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                <thead>
                    <tr style="border-bottom: 1px solid #334155; color: #94a3b8;">
                        <th style="padding: 0.5rem;">ID</th>
                        <th style="padding: 0.5rem;">Producto</th>
                        <th style="padding: 0.5rem;">Precio</th>
                        <th style="padding: 0.5rem; text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $prod): ?>
                        <tr style="border-bottom: 1px solid #1e293b;">
                            <td style="padding: 0.75rem 0.5rem; color: #64748b;"><?= $prod['id'] ?></td>
                            <td style="padding: 0.75rem 0.5rem; font-weight: bold; color: #f8fafc;"><?= htmlspecialchars($prod['name']) ?></td>
                            <td style="padding: 0.75rem 0.5rem; color: #38bdf8; font-weight: bold;">$<?= number_format($prod['price'], 2) ?></td>
                            <td style="padding: 0.75rem 0.5rem; text-align: right;">
                                <form action="/admin/sales" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                    
                                    <input type="hidden" name="action" value="delete_product">
                                    <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                    <button type="submit" style="background: #ef4444; color: #fff; border: none; padding: 0.35rem 0.75rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>