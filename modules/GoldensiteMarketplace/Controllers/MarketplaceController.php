<?php
namespace Modules\GoldensiteMarketplace\Controllers;

use App\Core\{AdminPath,Auth,ConsoleView,Csrf,Database,FrontendEngine,LicenseEngine,MarketplaceReservations,Money,RateLimiter,RequestGuard,UploadGuard};

final class MarketplaceController
{
    private const FOOTER = [
        'footer_help_url' => ['Centro de ayuda', '/faq'],
        'footer_refund_url' => ['Política de devoluciones', '/contacto'],
        'footer_protection_url' => ['Protección al comprador', '/contacto'],
    ];

    public function index(): void
    {
        Auth::requireAdmin();
        if (!LicenseEngine::isLicenseActive('marketplace')) {
            http_response_code(403);
            ConsoleView::render('marketplace', ['restricted'=>true], 'Goldensite Marketplace');
            return;
        }
        header('Cache-Control: private, no-store');
        $db = Database::getInstance();
        $base = AdminPath::url('/admin/marketplace');
        $error = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RequestGuard::post();
            try {
                $_SESSION['marketplace_admin_notice'] = $this->manage($db);
                header('Location: '.$base, true, 303);
                return;
            } catch (\InvalidArgumentException $exception) {
                http_response_code(422);
                $error = $exception->getMessage();
            }
        }
        $notice = $_SESSION['marketplace_admin_notice'] ?? '';
        unset($_SESSION['marketplace_admin_notice']);
        $q = self::search();
        $page = self::page();
        [$products,$hasNext] = $this->catalog($db, $q, '', '', $page, false);
        $vendors = $db->fetchAll('SELECT id,user_id,store_name,status FROM goldensite_vendors ORDER BY store_name,id');
        $categories = $this->categories($db);
        $editId = filter_var($_GET['edit'] ?? 0,FILTER_VALIDATE_INT);
        $edit = $editId ? $db->fetch('SELECT * FROM goldensite_marketplace_products WHERE id=?', [$editId]) : null;
        $counts = $db->fetch("SELECT COUNT(*) products, COUNT(CASE WHEN status='published' THEN 1 END) published,
            COUNT(CASE WHEN status='published' AND stock<=0 THEN 1 END) exhausted FROM goldensite_marketplace_products");
        $pending = $db->fetch("SELECT COUNT(*) total FROM goldensite_marketplace_reservations WHERE status='pending'");
        $reservationPage = self::page('reservation_page');
        $reservationOffset = ($reservationPage-1)*30;
        $reservations = $db->fetchAll("SELECT r.*, u.username FROM goldensite_marketplace_reservations r
            LEFT JOIN users u ON u.id=r.buyer_id ORDER BY (r.status='pending') DESC,r.id DESC LIMIT 31 OFFSET $reservationOffset");
        $hasMoreReservations = count($reservations)>30;
        $reservations = array_slice($reservations,0,30);
        $history = $db->fetchAll('SELECT o.id,o.total_amount,o.quantity,o.status,o.created_at,p.title
            FROM goldensite_marketplace_orders o LEFT JOIN goldensite_marketplace_products p ON p.id=o.product_id ORDER BY o.id DESC LIMIT 20');
        $links = $this->footer($db);
        ConsoleView::render('marketplace', compact('base','error','notice','q','page','hasNext','products','vendors','categories','edit','counts','pending','reservations','reservationPage','hasMoreReservations','history','links'), 'Goldensite Marketplace');
    }

    private function manage(Database $db): string
    {
        $action = ConsoleView::field($_POST,'action',32);
        if ($action === 'create_category') {
            $name = ConsoleView::field($_POST,'category_name',100);
            if ($name === '') throw new \InvalidArgumentException('Escribe un nombre para la categoría.');
            if ($db->fetch('SELECT id FROM goldensite_marketplace_categories WHERE name=?', [$name])) {
                throw new \InvalidArgumentException('Ya existe una categoría con ese nombre.');
            }
            $db->execute('INSERT INTO goldensite_marketplace_categories (name,slug) VALUES (?,?)', [$name,self::slug($name)]);
            return 'Categoría creada.';
        }
        if ($action === 'save_vendor') {
            $status = ConsoleView::field($_POST,'status',16);
            if (!in_array($status,['active','pending','suspended'],true)) throw new \InvalidArgumentException('Selecciona un estado válido.');
            $id = self::number('vendor_id',0,2147483647);
            if ($id > 0) {
                if (!$db->fetch('SELECT id FROM goldensite_vendors WHERE id=?',[$id])) throw new \InvalidArgumentException('Vendedor inexistente.');
                $db->execute('UPDATE goldensite_vendors SET status=? WHERE id=?', [$status,$id]);
            } else {
                $name = ConsoleView::field($_POST,'store_name',100);
                $account = ConsoleView::field($_POST,'account',150);
                $user = $db->fetch("SELECT id FROM users WHERE status='active' AND (email=? OR username=?) ORDER BY (email=?) DESC LIMIT 1",[$account,$account,$account]);
                if (!$user || $name === '') throw new \InvalidArgumentException('Indica un nombre de tienda y una cuenta activa.');
                $db->execute('INSERT INTO goldensite_vendors (user_id,store_name,slug,status) VALUES (?,?,?,?)',[$user['id'],$name,self::slug($name),$status]);
            }
            ConsoleView::audit('marketplace_vendor_saved');
            return 'Vendedor actualizado.';
        }
        if ($action === 'save_footer_settings') {
            $values = [];
            foreach (self::FOOTER as $key => $definition) {
                $value = ConsoleView::field($_POST,$key,255);
                if (!self::validUrl($value)) throw new \InvalidArgumentException('Usa rutas del sitio o direcciones HTTPS para los enlaces.');
                $values[$key] = $value;
            }
            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            try {
                foreach ($values as $key=>$value) {
                    $db->execute('INSERT INTO goldensite_admin_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)',[$key,$value]);
                }
                $pdo->commit();
            } catch (\Throwable $error) {
                $pdo->rollBack();
                throw $error;
            }
            return 'Enlaces de atención guardados.';
        }
        if ($action === 'resolve_reservation') {
            MarketplaceReservations::resolve(self::number('reservation_id',1,2147483647),ConsoleView::field($_POST,'status',16));
            ConsoleView::audit('marketplace_reservation_resolved');
            return 'Reserva actualizada.';
        }
        if ($action === 'archive_product') {
            $id = self::number('product_id',1,2147483647);
            $db->execute("UPDATE goldensite_marketplace_products SET status='draft' WHERE id=?",[$id]);
            ConsoleView::audit('marketplace_product_archived','product:'.$id);
            return 'Producto retirado de la tienda.';
        }
        if ($action === 'save_product') return $this->saveProduct($db);
        throw new \InvalidArgumentException('Operación no disponible.');
    }

    private function saveProduct(Database $db): string
    {
        $id = self::number('product_id',0,2147483647);
        $previous = $id ? $db->fetch('SELECT id FROM goldensite_marketplace_products WHERE id=?',[$id]) : null;
        if ($id && !$previous) throw new \InvalidArgumentException('Producto inexistente.');
        $vendor = self::number('vendor_id',1,2147483647);
        $stock = self::number('stock',0,1000000);
        $title = ConsoleView::field($_POST,'title',150);
        $description = $_POST['description'] ?? '';
        $category = ConsoleView::field($_POST,'category',100);
        $status = ConsoleView::field($_POST,'status',16);
        $price = Money::cents($_POST['price'] ?? null);
        if ($title === '' || $price < 1 || !in_array($status,['draft','published','out_of_stock'],true)
            || !is_string($description) || !mb_check_encoding($description,'UTF-8') || mb_strlen($description)>3000
            || !$db->fetch('SELECT id FROM goldensite_vendors WHERE id=?',[$vendor])) {
            throw new \InvalidArgumentException('Revisa el título, la descripción, el precio y el vendedor.');
        }
        if ($category !== 'General' && !$db->fetch('SELECT id FROM goldensite_marketplace_categories WHERE name=?',[$category])
            && (!$id || !$db->fetch('SELECT id FROM goldensite_marketplace_products WHERE id=? AND category=?',[$id,$category]))) {
            throw new \InvalidArgumentException('Selecciona una categoría existente.');
        }
        $image = ConsoleView::field($_POST,'image_url',255);
        if ($image !== '' && !self::validUrl($image)) throw new \InvalidArgumentException('La imagen necesita una ruta del sitio o una dirección HTTPS.');
        $path = null;
        $file = $_FILES['product_image'] ?? [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $temporary = $file['tmp_name'] ?? '';
            $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','image/gif'=>'gif'];
            $type = is_string($temporary) && is_uploaded_file($temporary) ? (new \finfo(FILEINFO_MIME_TYPE))->file($temporary) : '';
            if ($file['error'] !== UPLOAD_ERR_OK || !isset($types[$type])) throw new \InvalidArgumentException('Carga una imagen JPG, PNG, WebP o GIF.');
            $image = '/uploads/marketplace_'.bin2hex(random_bytes(16)).'.'.$types[$type];
            $path = dirname(__DIR__,3).'/public'.$image;
            if (!UploadGuard::move($temporary,$path)) throw new \InvalidArgumentException('No se pudo guardar la imagen.');
        }
        $values = [$vendor,$title,trim($description),$category,$image,Money::decimal($price),$stock,$status];
        try {
            if ($id) {
                $originalStock = self::number('original_stock',0,2147483647);
                $pdo = $db->getConnection();
                $pdo->beginTransaction();
                try {
                    $current = $db->fetch('SELECT stock FROM goldensite_marketplace_products WHERE id=? FOR UPDATE',[$id]);
                    if (!$current || (int)$current['stock'] !== $originalStock) {
                        throw new \InvalidArgumentException('El stock cambió mientras editabas. Abre el producto de nuevo.');
                    }
                    $db->execute('UPDATE goldensite_marketplace_products SET vendor_id=?,title=?,description=?,category=?,image_url=?,price=?,stock=?,status=? WHERE id=?',[...$values,$id]);
                    $pdo->commit();
                } catch (\Throwable $error) {
                    $pdo->rollBack();
                    throw $error;
                }
            } else {
                $db->execute('INSERT INTO goldensite_marketplace_products (vendor_id,title,description,category,image_url,price,stock,status,slug) VALUES (?,?,?,?,?,?,?,?,?)',[...$values,self::slug($title)]);
            }
        } catch (\Throwable $error) {
            if ($path !== null) @unlink($path);
            throw $error;
        }
        ConsoleView::audit('marketplace_product_saved');
        return 'Producto guardado.';
    }

    public function publicStore(): void
    {
        header('Cache-Control: private, no-store');
        $available = FrontendEngine::isModuleActive('marketplace');
        $siteTitle = 'Goldensite Marketplace';
        $error = '';
        $notice = '';
        $product = null;
        $orders = [];
        $products = [];
        $categories = [];
        $links = [];
        $buyer = Auth::id();
        $tab = ($_GET['tab'] ?? '') === 'orders' ? 'orders' : 'catalog';
        $q = self::search();
        $page = self::page();
        $category = is_string($_GET['category'] ?? null) ? mb_substr($_GET['category'],0,100) : '';
        $sort = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
        $hasNext = false;
        if (!$available) {
            http_response_code(403);
        } else {
            $db = Database::getInstance();
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                Auth::requireAuth();
                RequestGuard::post();
                try {
                    if (!RateLimiter::consume('marketplace:'.$buyer,20,300)) throw new \InvalidArgumentException('Espera unos minutos antes de volver a intentar.');
                    $action = ConsoleView::field($_POST,'action',32);
                    if ($action === 'reserve') {
                        $id = MarketplaceReservations::reserve((int)$buyer,self::number('product_id',1,2147483647),self::number('quantity',1,20),ConsoleView::field($_POST,'request_token',32));
                        $_SESSION['marketplace_notice'] = 'Reserva #'.$id.' registrada.';
                    } elseif ($action === 'cancel') {
                        MarketplaceReservations::resolve(self::number('reservation_id',1,2147483647),'cancelled',(int)$buyer);
                        $_SESSION['marketplace_notice'] = 'Reserva cancelada. Las unidades volvieron al catálogo.';
                    } else {
                        throw new \InvalidArgumentException('Operación no disponible.');
                    }
                    header('Location: /marketplace?tab=orders', true, 303);
                    return;
                } catch (\InvalidArgumentException $exception) {
                    http_response_code(422);
                    $error = $exception->getMessage();
                    $tab = 'orders';
                }
            }
            $notice = $_SESSION['marketplace_notice'] ?? '';
            unset($_SESSION['marketplace_notice']);
            $links = $this->footer($db);
            if ($tab === 'orders' && $buyer) {
                $offset = ($page-1)*20;
                $orders = $db->fetchAll("SELECT * FROM goldensite_marketplace_reservations WHERE buyer_id=? ORDER BY id DESC LIMIT 21 OFFSET $offset",[$buyer]);
                $hasNext = count($orders)>20;
                $orders = array_slice($orders,0,20);
            } elseif (isset($_GET['product_id'])) {
                $id = filter_var($_GET['product_id'],FILTER_VALIDATE_INT);
                $product = $id ? $db->fetch("SELECT p.*,v.store_name FROM goldensite_marketplace_products p JOIN goldensite_vendors v ON v.id=p.vendor_id WHERE p.id=? AND p.status IN ('published','out_of_stock') AND v.status='active'",[$id]) : null;
                if (!$product) {
                    http_response_code(404);
                    $error = 'Este producto no está disponible.';
                }
            } elseif ($tab === 'catalog') {
                $categories = $this->categories($db);
                [$products,$hasNext] = $this->catalog($db,$q,$category,$sort,$page,true);
            }
        }
        $e = ConsoleView::escape(...);
        $csrfToken = Csrf::generate();
        $requestToken = bin2hex(random_bytes(16));
        ob_start();
        require dirname(__DIR__,3).'/views/marketplace/store.php';
        $content = ob_get_clean();
        require dirname(__DIR__,3).'/views/layouts/main.php';
    }

    public function publicCheckout(): void { $this->publicStore(); }

    private function catalog(Database $db,string $q,string $category,string $sort,int $page,bool $public): array
    {
        $where = $public ? "p.status IN ('published','out_of_stock') AND v.status='active'" : '1=1';
        $params = [];
        if ($q !== '') {
            $where .= " AND (p.title LIKE ? ESCAPE '=' OR p.category LIKE ? ESCAPE '=' OR v.store_name LIKE ? ESCAPE '=')";
            $pattern = '%'.strtr($q,['='=>'==','%'=>'=%','_'=>'=_']).'%';
            $params = [$pattern,$pattern,$pattern];
        }
        if ($category !== '') {
            $where .= ' AND p.category=?';
            $params[] = $category;
        }
        $order = ['price_asc'=>'p.price ASC,p.id DESC','price_desc'=>'p.price DESC,p.id DESC'][$sort] ?? 'p.id DESC';
        $offset = ($page-1)*20;
        $rows = $db->fetchAll("SELECT p.*,v.store_name FROM goldensite_marketplace_products p
            LEFT JOIN goldensite_vendors v ON v.id=p.vendor_id WHERE $where ORDER BY $order LIMIT 21 OFFSET $offset",$params);
        return [array_slice($rows,0,20),count($rows)>20];
    }

    private function categories(Database $db): array
    {
        return $db->fetchAll("SELECT name FROM goldensite_marketplace_categories UNION SELECT DISTINCT category name FROM goldensite_marketplace_products WHERE category<>'' UNION SELECT 'General' ORDER BY name");
    }

    private function footer(Database $db): array
    {
        $links = [];
        $values = array_column($db->fetchAll("SELECT setting_key,setting_value FROM goldensite_admin_settings WHERE setting_key IN ('footer_help_url','footer_refund_url','footer_protection_url')"),'setting_value','setting_key');
        foreach (self::FOOTER as $key=>[$label,$fallback]) {
            $url = $values[$key] ?? '';
            $links[$key] = [$label,self::validUrl($url)?$url:$fallback];
        }
        return $links;
    }

    public static function validUrl(string $url): bool
    {
        if (preg_match('/[\x00-\x20\x7f\\\\]/',$url)) return false;
        if (str_starts_with($url,'/')) return !str_starts_with($url,'//');
        return str_starts_with($url,'https://') && filter_var($url,FILTER_VALIDATE_URL)!==false;
    }

    private static function number(string $key,int $min,int $max): int
    {
        $value = filter_var($_POST[$key] ?? null,FILTER_VALIDATE_INT,['options'=>['min_range'=>$min,'max_range'=>$max]]);
        if ($value===false) throw new \InvalidArgumentException('Revisa los campos numéricos del formulario.');
        return $value;
    }

    private static function search(): string
    {
        return is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,100) : '';
    }

    private static function page(string $parameter = 'page'): int
    {
        return max(1,min(10000,filter_var($_GET[$parameter] ?? 1,FILTER_VALIDATE_INT) ?: 1));
    }

    private static function slug(string $name): string
    {
        return substr(trim(strtolower(preg_replace('/[^a-zA-Z0-9]+/','-',$name)),'-'),0,78).'-'.bin2hex(random_bytes(6));
    }
}
