<link rel="stylesheet" href="/assets/marketplace.css">
<div class="nx-market">
<header class="nx-mkt-hero">
    <div><p class="nx-mkt-kicker">GOLDENSITE / OPERACIONES COMERCIALES</p><h1>Marketplace</h1><p>Gestiona productos, vendedores y reservas desde un solo lugar.</p></div>
    <a class="nx-btn primary" href="/marketplace">Ver tienda ↗</a>
</header>
<?php if (!empty($restricted)): ?>
<p role="alert">Activa la licencia de Goldensite Marketplace desde el catálogo de módulos.</p>
<?php return; endif; ?>
<?php if ($notice || $error): ?><p class="nx-mkt-note" role="<?= $error?'alert':'status' ?>"><?= $e($error ?: $notice) ?></p><?php endif; ?>
<div class="nx-mkt-metrics">
<?php foreach (['Productos'=>$counts['products'],'Publicados'=>$counts['published'],'Publicados sin stock'=>$counts['exhausted'],'Reservas pendientes'=>$pending['total']] as $label=>$value): ?>
    <div><span><?= $e($label) ?></span><strong><?= (int)$value ?></strong></div>
<?php endforeach; ?>
</div>
<nav class="nx-mkt-nav" aria-label="Secciones del Marketplace"><a href="#mkt-catalogo">Catálogo</a><a href="#mkt-vendedores">Vendedores</a><a href="#mkt-reservas">Reservas</a><a href="#mkt-ajustes">Ajustes</a></nav>
<div class="nx-mkt-workspace" id="mkt-catalogo">
<section class="nx-mkt-panel" id="mkt-producto">
    <h2><?= $edit?'Editar producto':'Nuevo producto' ?></h2>
    <?php if ($edit): ?><p><a href="<?= $e($base) ?>#mkt-producto">Crear otro producto</a></p><?php endif; ?>
    <?php if (!$vendors): ?><p>Registra primero un vendedor para publicar productos.</p><?php endif; ?>
    <form method="post" action="<?= $e($base) ?>" enctype="multipart/form-data" class="nx-mkt-form">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>">
        <input type="hidden" name="action" value="save_product">
        <input type="hidden" name="product_id" value="<?= (int)($edit['id']??0) ?>">
        <input type="hidden" name="original_stock" value="<?= (int)($edit['stock']??0) ?>">
        <label>Título<input name="title" required maxlength="150" value="<?= $e($edit['title']??'') ?>"></label>
        <label>Vendedor<select name="vendor_id" required><option value="">Seleccionar</option><?php foreach ($vendors as $vendor): ?><option value="<?= (int)$vendor['id'] ?>" <?= ($edit['vendor_id']??null)===$vendor['id']?'selected':'' ?>><?= $e($vendor['store_name']) ?></option><?php endforeach; ?></select></label>
        <label>Categoría<select name="category"><?php foreach ($categories as $category): ?><option <?= ($edit['category']??'General')===$category['name']?'selected':'' ?>><?= $e($category['name']) ?></option><?php endforeach; ?></select></label>
        <div class="nx-mkt-fields"><label>Precio (USD)<input name="price" type="number" min="0.01" max="999999.99" step="0.01" required value="<?= $e($edit['price']??'') ?>"></label><label>Stock disponible<input name="stock" type="number" min="0" max="1000000" required value="<?= (int)($edit['stock']??0) ?>"></label></div>
        <label>Estado<select name="status"><?php foreach (['draft'=>'Borrador','published'=>'Publicado','out_of_stock'=>'Agotado'] as $key=>$label): ?><option value="<?= $key ?>" <?= ($edit['status']??'draft')===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <label>Descripción<textarea name="description" maxlength="3000" rows="3"><?= $e($edit['description']??'') ?></textarea></label>
        <label>Dirección de imagen<input name="image_url" maxlength="255" value="<?= $e($edit['image_url']??'') ?>"></label>
        <label>O cargar una foto<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp,image/gif"></label>
        <button class="nx-btn primary" <?= !$vendors?'disabled':'' ?>>Guardar producto</button>
    </form>
</section>
<section class="nx-mkt-panel">
    <h2>Catálogo global</h2>
    <form method="get" action="<?= $e($base) ?>" class="nx-mkt-inline"><label>Buscar en el catálogo<input name="q" maxlength="100" value="<?= $e($q) ?>" placeholder="Producto, categoría o vendedor"></label><button class="nx-btn">Buscar</button></form>
    <div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Producto</th><th>Precio</th><th>Stock</th><th>Estado</th><th>Acciones</th></tr></thead><tbody>
    <?php foreach ($products as $item): ?>
    <tr><td><strong><?= $e($item['title']) ?></strong><small><?= $e(($item['store_name']??'Vendedor no disponible').' · '.$item['category']) ?></small></td><td>USD <?= $e($item['price']) ?></td><td><?= (int)$item['stock'] ?></td><td><?= $e(['draft'=>'Borrador','published'=>'Publicado','out_of_stock'=>'Agotado'][$item['status']]??'Sin estado') ?></td><td><div class="nx-mkt-inline"><a href="<?= $e($base) ?>?edit=<?= (int)$item['id'] ?>#mkt-producto">Editar</a>
    <?php if ($item['status']!=='draft'): ?><form method="post" action="<?= $e($base) ?>"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="product_id" value="<?= (int)$item['id'] ?>"><button class="nx-btn small" name="action" value="archive_product">Retirar</button></form><?php endif; ?></div></td></tr>
    <?php endforeach; ?>
    <?php if (!$products): ?><tr><td colspan="5">No hay productos para esta búsqueda.</td></tr><?php endif; ?>
    </tbody></table></div>
    <nav class="nx-mkt-nav" aria-label="Páginas del catálogo administrativo"><?php if ($page>1): ?><a href="<?= $e($base.'?'.http_build_query(['q'=>$q,'page'=>$page-1])) ?>#mkt-catalogo">Anterior</a><?php endif; ?><?php if ($hasNext): ?><a href="<?= $e($base.'?'.http_build_query(['q'=>$q,'page'=>$page+1])) ?>#mkt-catalogo">Siguiente</a><?php endif; ?></nav>
</section>
</div>
<section class="nx-mkt-panel" id="mkt-vendedores">
    <h2>Vendedores</h2>
    <form method="post" action="<?= $e($base) ?>" class="nx-mkt-inline">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="vendor_id" value="0"><input type="hidden" name="status" value="pending">
        <label>Nombre de la tienda<input name="store_name" required maxlength="100"></label><label>Usuario o correo existente<input name="account" required maxlength="150"></label><button class="nx-btn" name="action" value="save_vendor">Registrar vendedor</button>
    </form>
    <?php foreach ($vendors as $vendor): ?><form method="post" action="<?= $e($base) ?>" class="nx-mkt-inline nx-mkt-line">
        <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="vendor_id" value="<?= (int)$vendor['id'] ?>">
        <strong><?= $e($vendor['store_name']) ?> <small>Cuenta #<?= (int)$vendor['user_id'] ?></small></strong>
        <label>Estado<select name="status"><?php foreach (['pending'=>'Pendiente','active'=>'Activo','suspended'=>'Suspendido'] as $key=>$label): ?><option value="<?= $key ?>" <?= $vendor['status']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
        <button class="nx-btn small" name="action" value="save_vendor">Guardar estado</button>
    </form><?php endforeach; ?>
</section>
<section class="nx-mkt-panel" id="mkt-reservas">
    <h2>Reservas de inventario</h2><p>Una reserva pendiente aparta unidades. Cancelarla devuelve las unidades al catálogo. Marcarla como atendida registra la entrega.</p>
    <p class="nx-mkt-note">Este flujo funciona sin cobro en línea. Los importes son valores de catálogo.</p>
    <div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Reserva</th><th>Comprador</th><th>Producto</th><th>Total</th><th>Estado y acciones</th></tr></thead><tbody>
    <?php foreach ($reservations as $row): ?><tr>
        <td>#<?= (int)$row['id'] ?><small><?= $e($row['created_at']) ?></small></td><td><?= $e($row['username']??('Cuenta #'.$row['buyer_id'])) ?></td>
        <td><?= $e($row['product_title']) ?><small><?= (int)$row['quantity'] ?> unidades · <?= $e($row['store_name']) ?></small></td><td><?= $e(\App\Core\Money::display((int)$row['total_cents'])) ?></td>
        <td><?= $e(['pending'=>'Pendiente','fulfilled'=>'Atendida','cancelled'=>'Cancelada'][$row['status']]??'') ?>
        <?php if ($row['status']==='pending'): ?><form method="post" action="<?= $e($base) ?>" class="nx-mkt-inline">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="resolve_reservation"><input type="hidden" name="reservation_id" value="<?= (int)$row['id'] ?>">
            <button class="nx-btn small" name="status" value="fulfilled">Atender</button><button class="nx-btn small" name="status" value="cancelled">Cancelar</button>
        </form><?php endif; ?></td>
    </tr><?php endforeach; ?>
    <?php if (!$reservations): ?><tr><td colspan="5">Las reservas de la tienda aparecerán aquí.</td></tr><?php endif; ?>
    </tbody></table></div>
    <?php if ($reservationPage>1 || $hasMoreReservations): ?>
    <nav class="nx-mkt-nav" aria-label="Páginas de reservas">
        <?php if ($reservationPage>1): ?><a href="<?= $e($base) ?>?reservation_page=<?= $reservationPage-1 ?>#mkt-reservas">Anterior</a><?php endif; ?>
        <?php if ($hasMoreReservations): ?><a href="<?= $e($base) ?>?reservation_page=<?= $reservationPage+1 ?>#mkt-reservas">Siguiente</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php if ($history): ?><details><summary>Historial anterior · últimos 20 registros</summary>
        <p>Registros de la versión anterior. El estado histórico no acredita un pago.</p>
        <div class="nx-table-wrap"><table class="nx-table"><thead><tr><th>Registro</th><th>Producto</th><th>Importe registrado</th><th>Estado anterior</th></tr></thead><tbody>
        <?php foreach ($history as $row): ?><tr><td>#<?= (int)$row['id'] ?> <small><?= $e($row['created_at']) ?></small></td><td><?= $e($row['title']??'Producto retirado') ?> · <?= (int)$row['quantity'] ?> unidades</td><td>USD <?= $e($row['total_amount']) ?></td><td><?= $e(['pending'=>'Pendiente','completed'=>'Completado','cancelled'=>'Cancelado'][$row['status']]??'Sin estado') ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </details><?php endif; ?>
</section>
<div class="nx-mkt-workspace" id="mkt-ajustes">
    <section class="nx-mkt-panel"><h2>Categorías</h2>
        <form method="post" action="<?= $e($base) ?>" class="nx-mkt-form"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><label>Nueva categoría<input name="category_name" required maxlength="100"></label><button class="nx-btn" name="action" value="create_category">Crear categoría</button></form>
    </section>
    <section class="nx-mkt-panel"><h2>Atención al cliente</h2>
        <form method="post" action="<?= $e($base) ?>" class="nx-mkt-form"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><?php foreach ($links as $key=>[$label,$url]): ?><label><?= $e($label) ?><input name="<?= $e($key) ?>" maxlength="255" required value="<?= $e($url) ?>"></label><?php endforeach; ?><button class="nx-btn" name="action" value="save_footer_settings">Guardar enlaces</button></form>
    </section>
</div>
</div>
