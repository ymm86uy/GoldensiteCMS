<link rel="stylesheet" href="/assets/marketplace.css">
<section class="nx-market nx-mkt-store" aria-label="Goldensite Marketplace">
<header class="nx-mkt-hero">
    <div><p class="nx-mkt-kicker">GOLDENSITE / RED DE TIENDAS</p><h1>Marketplace</h1><p>Encuentra productos de distintos vendedores y reserva desde tu cuenta.</p></div>
</header>
<?php if (!$available): ?><p class="nx-mkt-note" role="status">La tienda está fuera de servicio por el momento.</p>
<?php else: ?>
<nav class="nx-mkt-nav" aria-label="Marketplace"><a href="/marketplace" <?= $tab==='catalog'?'aria-current="page"':'' ?>>Explorar catálogo</a><a href="/marketplace?tab=orders" <?= $tab==='orders'?'aria-current="page"':'' ?>>Mis reservas</a></nav>
<?php if ($error || $notice): ?><p class="nx-mkt-note" role="<?= $error?'alert':'status' ?>"><?= $e($error ?: $notice) ?></p><?php endif; ?>
<?php if ($tab==='orders'): ?>
<section class="nx-mkt-panel">
    <h2>Mis reservas</h2>
    <?php if (!$buyer): ?><p><a class="nx-mkt-button" href="/login?return_to=%2Fmarketplace%3Ftab%3Dorders">Inicia sesión para consultar tus reservas</a></p><?php endif; ?>
    <?php if ($buyer && !$orders): ?><p>Todavía no tienes reservas en esta página.</p><?php endif; ?>
    <?php foreach ($orders as $row): ?>
    <article class="nx-mkt-line nx-mkt-inline">
        <div><strong>#<?= (int)$row['id'] ?> · <?= $e($row['product_title']) ?></strong><small><?= $e($row['store_name']) ?> · <?= (int)$row['quantity'] ?> unidades · <?= $e($row['created_at']) ?></small></div>
        <div><?= $e(\App\Core\Money::display((int)$row['total_cents'])) ?><small><?= $e(['pending'=>'Pendiente','fulfilled'=>'Atendida','cancelled'=>'Cancelada'][$row['status']]??'') ?></small></div>
        <?php if ($row['status']==='pending'): ?><form method="post" action="/marketplace">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int)$row['id'] ?>"><button class="nx-mkt-button secondary" name="action" value="cancel">Cancelar reserva</button>
        </form><?php endif; ?>
    </article>
    <?php endforeach; ?>
</section>
<?php elseif ($product): ?>
<article class="nx-mkt-panel nx-mkt-detail">
    <?php $image = $product['image_url']??''; ?>
    <div class="nx-mkt-image"><?php if (\Modules\GoldensiteMarketplace\Controllers\MarketplaceController::validUrl($image)): ?><img src="<?= $e($image) ?>" alt="<?= $e($product['title']) ?>" referrerpolicy="no-referrer"><?php else: ?><span>Sin foto</span><?php endif; ?></div>
    <div><p class="nx-mkt-kicker"><?= $e($product['category']) ?> · <?= $e($product['store_name']) ?></p><h2><?= $e($product['title']) ?></h2><p class="nx-mkt-price">USD <?= $e($product['price']) ?></p>
    <?php if (!empty($product['description'])): ?><p class="nx-mkt-description"><?= $e($product['description']) ?></p><?php endif; ?>
    <?php if ($product['status']==='published' && (int)$product['stock']>0 && (float)$product['price']>0): ?>
        <p><?= (int)$product['stock'] ?> disponibles · Reserva sin pago en línea</p>
        <?php if ($buyer): ?><form method="post" action="/marketplace" class="nx-mkt-inline">
            <input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="request_token" value="<?= $e($requestToken) ?>"><input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
            <label>Cantidad<input name="quantity" type="number" min="1" max="<?= min(20,(int)$product['stock']) ?>" value="1" required></label><button class="nx-mkt-button" name="action" value="reserve">Reservar unidades</button>
        </form><?php else: ?><p><a class="nx-mkt-button" href="/login?return_to=%2Fmarketplace">Iniciar sesión para reservar</a></p><?php endif; ?>
    <?php else: ?><p>Sin disponibilidad para reservar.</p><?php endif; ?>
    </div>
</article>
<?php elseif (!$error): ?>
<form method="get" action="/marketplace" class="nx-mkt-panel nx-mkt-filters">
    <label>Buscar productos<input name="q" value="<?= $e($q) ?>" maxlength="100" placeholder="Producto, categoría o vendedor"></label>
    <label>Categoría<select name="category"><option value="">Todas</option><?php foreach ($categories as $option): ?><option <?= $category===$option['name']?'selected':'' ?>><?= $e($option['name']) ?></option><?php endforeach; ?></select></label>
    <label>Ordenar por<select name="sort"><?php foreach ([''=>'Más recientes','price_asc'=>'Menor precio','price_desc'=>'Mayor precio'] as $key=>$label): ?><option value="<?= $key ?>" <?= $sort===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <button class="nx-mkt-button">Buscar</button>
</form>
<section class="nx-mkt-grid" aria-label="Productos">
    <?php foreach ($products as $item): $image=$item['image_url']??''; ?>
    <article class="nx-mkt-card">
        <a class="nx-mkt-image" href="/marketplace?product_id=<?= (int)$item['id'] ?>" aria-label="<?= $e('Ver '.$item['title']) ?>"><?php if (\Modules\GoldensiteMarketplace\Controllers\MarketplaceController::validUrl($image)): ?><img src="<?= $e($image) ?>" alt="" loading="lazy" referrerpolicy="no-referrer"><?php else: ?><span>Sin foto</span><?php endif; ?></a>
        <div><p class="nx-mkt-kicker"><?= $e($item['category']) ?></p><h2><?= $e($item['title']) ?></h2><small><?= $e($item['store_name']) ?></small>
        <p class="nx-mkt-price">USD <?= $e($item['price']) ?></p><p><?= $item['status']==='published'&&(int)$item['stock']>0 ? (int)$item['stock'].' disponibles' : 'Agotado' ?></p>
        <a class="nx-mkt-button secondary" href="/marketplace?product_id=<?= (int)$item['id'] ?>">Ver producto</a></div>
    </article>
    <?php endforeach; ?>
</section>
<?php if (!$products): ?><p class="nx-mkt-note">No encontramos productos para esta búsqueda. <a href="/marketplace">Ver todo el catálogo</a></p><?php endif; ?>
<?php endif; ?>
<?php if (!$product && ($page>1 || $hasNext)): ?>
<nav class="nx-mkt-nav" aria-label="Paginación"><?php $query=['tab'=>$tab,'q'=>$q,'category'=>$category,'sort'=>$sort]; ?>
    <?php if ($page>1): ?><a href="/marketplace?<?= $e(http_build_query($query+['page'=>$page-1])) ?>">Anterior</a><?php endif; ?>
    <?php if ($hasNext): ?><a href="/marketplace?<?= $e(http_build_query($query+['page'=>$page+1])) ?>">Siguiente</a><?php endif; ?>
</nav>
<?php endif; ?>
<footer class="nx-mkt-nav" aria-label="Atención al cliente"><?php foreach ($links as [$label,$url]): ?><a href="<?= $e($url) ?>"><?= $e($label) ?></a><?php endforeach; ?></footer>
<?php endif; ?>
</section>
