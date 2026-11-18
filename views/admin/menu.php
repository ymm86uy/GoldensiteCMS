<div style="padding: 1.5rem;">
    <h1 style="color: #38bdf8; margin-bottom: 0.5rem;">🔗 Gestor de Menús de Navegación</h1>
    <p style="color: #94a3b8; margin-bottom: 2rem;">Configura los enlaces del encabezado y pie de página del sitio.</p>

    <?php if (!empty($status)): ?>
        <div style="padding: 1rem; border-radius: 6px; margin-bottom: 1.5rem; background: <?= $status['type'] === 'success' ? 'rgba(16, 185, 129, 0.2)' : 'rgba(239, 68, 68, 0.2)' ?>; border: 1px solid <?= $status['type'] === 'success' ? '#10b981' : '#ef4444' ?>; color: #fff;">
            <?= htmlspecialchars($status['msg']) ?>
        </div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
        <!-- Agregar Elemento -->
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem;">
            <h3 style="color: #f8fafc; margin-bottom: 1rem;">Agregar Enlace</h3>
            <form action="/admin/menu" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                
                <input type="hidden" name="action" value="add">

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.3rem;">Título del Menú</label>
                    <input type="text" name="title" required placeholder="Ej. Inicio, Nosotros, Contacto" style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.3rem;">URL / Ruta</label>
                    <input type="text" name="url" required placeholder="/nosotros o https://..." style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
                </div>

                <div style="margin-bottom: 1rem;">
                    <label style="display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.3rem;">Ubicación</label>
                    <select name="location" style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
                        <option value="header">Encabezado (Header)</option>
                        <option value="footer">Pie de Página (Footer)</option>
                    </select>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display: block; color: #94a3b8; font-size: 0.85rem; margin-bottom: 0.3rem;">Orden</label>
                    <input type="number" name="sort_order" value="0" style="width: 100%; padding: 0.65rem; background: #0f172a; border: 1px solid #334155; border-radius: 6px; color: #fff;">
                </div>

                <button type="submit" style="width: 100%; padding: 0.75rem; background: #0284c7; color: #fff; border: none; border-radius: 6px; font-weight: bold; cursor: pointer;">Añadir al Menú</button>
            </form>
        </div>

        <!-- Lista de Elementos -->
        <div style="background: #1e293b; border: 1px solid #334155; border-radius: 8px; padding: 1.5rem;">
            <h3 style="color: #f8fafc; margin-bottom: 1rem;">Estructura Actual</h3>
            <table style="width: 100%; border-collapse: collapse; text-align: left;">
                <thead>
                    <tr style="border-bottom: 1px solid #334155; color: #94a3b8; font-size: 0.85rem;">
                        <th style="padding: 0.75rem;">Título</th>
                        <th style="padding: 0.75rem;">URL</th>
                        <th style="padding: 0.75rem;">Ubicación</th>
                        <th style="padding: 0.75rem;">Orden</th>
                        <th style="padding: 0.75rem; text-align: right;">Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($menuItems)): ?>
                        <?php foreach ($menuItems as $item): ?>
                            <tr style="border-bottom: 1px solid #334155;">
                                <td style="padding: 0.75rem; color: #f8fafc; font-weight: 600;"><?= htmlspecialchars($item['title']) ?></td>
                                <td style="padding: 0.75rem; color: #38bdf8;"><?= htmlspecialchars($item['url']) ?></td>
                                <td style="padding: 0.75rem;">
                                    <span style="padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.75rem; font-weight: bold; background: rgba(56, 189, 248, 0.2); color: #38bdf8;">
                                        <?= strtoupper($item['location']) ?>
                                    </span>
                                </td>
                                <td style="padding: 0.75rem; color: #94a3b8;"><?= $item['sort_order'] ?></td>
                                <td style="padding: 0.75rem; text-align: right;">
                                    <form action="/admin/menu" method="POST" style="display: inline;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                                        
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                        <button type="submit" style="background: #ef4444; color: #fff; border: none; padding: 0.3rem 0.6rem; border-radius: 4px; cursor: pointer; font-size: 0.8rem;">Eliminar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="padding: 1.5rem; text-align: center; color: #94a3b8;">No hay enlaces registrados en el menú.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>