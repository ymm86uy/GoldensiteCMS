<?php
namespace App\Core;

final class Ads
{
    public const ID = 'ads';
    public const POSITIONS = ['top'=>'Inicio del contenido','bottom'=>'Final del contenido','inline'=>'Dentro del contenido'];
    private const KEY = 'ads_campaigns';
    private const LIMIT = 30;
    private static ?bool $eligible = null;

    public static function campaigns(): array
    {
        return self::decode(Settings::get(self::KEY, '{}'));
    }

    private static function decode(string $json): array
    {
        $campaigns = json_decode($json, true, 8);
        return is_array($campaigns) ? array_slice($campaigns, 0, self::LIMIT, true) : [];
    }

    public static function save(string $id, array $input): string
    {
        $field = static fn(string $name, int $size): string => ConsoleView::field($input, $name, $size);
        $title = $field('title', 100);
        $sponsor = $field('sponsor', 80);
        $copy = $field('copy', 240);
        $destination = $field('destination', 512);
        $image = $field('image', 220);
        $placement = $field('placement', 6);
        $page = $field('page', 160);
        $priority = $field('priority', 2);
        $enabled = ($input['enabled'] ?? '') === '1';
        if ($title === '' || $sponsor === '' || !isset(self::POSITIONS[$placement]) || !ctype_digit($priority)) {
            throw new \InvalidArgumentException('Completa título, anunciante, ubicación y prioridad de 0 a 99.');
        }
        if (!self::safeDestination($destination) || ($page !== '' && !preg_match('~^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$~D', $page))) {
            throw new \InvalidArgumentException('Usa un destino HTTPS o una ruta interna. El filtro de página acepta una ruta literal.');
        }
        if ($image !== '') {
            $path = AssetLibrary::path($image);
            $info = @getimagesize($path);
            if (!$info || !in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true) || filesize($path)>2097152) {
                throw new \InvalidArgumentException('Selecciona un PNG, JPEG o WebP de la biblioteca, hasta 2 MB.');
            }
        }
        $starts = self::timestamp($field('starts',16));
        $ends = self::timestamp($field('ends',16));
        if ($starts !== null && $ends !== null && $starts >= $ends) throw new \InvalidArgumentException('El fin debe ser posterior al inicio.');
        $priority = (int)$priority;
        $new = $id === '';
        if ($new) $id = bin2hex(random_bytes(8));
        self::change($id, static function(array $campaigns) use ($new,$id,$title,$sponsor,$copy,$destination,$image,$placement,$page,$priority,$enabled,$starts,$ends): array {
            if ((!$new && !isset($campaigns[$id])) || ($new && count($campaigns)>=self::LIMIT)) throw new \InvalidArgumentException('La campaña no existe o se alcanzó el límite de 30 campañas.');
            $campaigns[$id] = compact('title','sponsor','copy','destination','image','placement','page','priority','enabled','starts','ends');
            return $campaigns;
        });
        return $id;
    }

    public static function delete(string $id): void
    {
        self::change($id, static function(array $campaigns) use ($id): array {
            if (!isset($campaigns[$id])) throw new \InvalidArgumentException('La campaña ya no existe.');
            unset($campaigns[$id]);
            return $campaigns;
        });
    }

    private static function change(string $id, callable $update): void
    {
        if (!preg_match('/^[a-f0-9]{16}$/D', $id)) throw new \InvalidArgumentException('Campaña inválida.');
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $db->execute('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)', [self::KEY,'{}']);
            $row = $db->fetch('SELECT setting_value FROM settings WHERE setting_key=? FOR UPDATE', [self::KEY]);
            Settings::set(self::KEY, json_encode($update(self::decode($row['setting_value'])), JSON_THROW_ON_ERROR));
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    private static function timestamp(string $text): ?int
    {
        if ($text === '') return null;
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $text);
        if (!$date || $date->format('Y-m-d\TH:i') !== $text) throw new \InvalidArgumentException('Indica una fecha y hora válidas.');
        return $date->getTimestamp();
    }

    private static function safeDestination(string $url): bool
    {
        if ($url === '' || preg_match('~[\\\\\x00-\x20\x7f]~', $url)) return false;
        if (str_starts_with($url, '/')) return !str_starts_with($url, '//');
        $parts = parse_url($url);
        return str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL) !== false
            && !isset($parts['user']) && !isset($parts['pass']);
    }

    public static function render(string $placement, string $path): string
    {
        self::$eligible ??= FrontendEngine::isModuleActive(self::ID) && !PaywallEngine::hasAccess();
        if (!self::$eligible) return '';
        $now = time();
        $eligible = array_filter(self::campaigns(), static fn(array $ad): bool => $ad['enabled'] && $ad['placement']===$placement
            && ($ad['page']==='' || $ad['page']===$path) && ($ad['starts']===null || $ad['starts']<=$now)
            && ($ad['ends']===null || $ad['ends']>$now) && self::safeDestination($ad['destination']));
        if (!$eligible) return '';
        $priority = max(array_column($eligible, 'priority'));
        $rotation = array_filter($eligible, static fn(array $ad): bool => $ad['priority']===$priority);
        $id = array_rand($rotation);
        $ad = $rotation[$id];
        $e = ConsoleView::escape(...);
        $image = $ad['image'] === '' ? '' : '<img alt="" loading="lazy" decoding="async" src="/uploads/'.$e(implode('/', array_map('rawurlencode', explode('/', $ad['image'])))).'">';
        $path = self::metricPath($path);
        $link = '/ads/visit/'.rawurlencode((string)$id).'?'.http_build_query(['page'=>$path,'sig'=>self::signature((string)$id,$path)],'','&',PHP_QUERY_RFC3986);
        return '<aside class="gs-ad" data-gs-ad aria-label="Publicidad"><div>'.$image.'<p class="gs-ad-sponsor">Publicidad · '.$e($ad['sponsor']).'</p><p class="gs-ad-title">'.$e($ad['title']).'</p><p>'.$e($ad['copy']).'</p></div><a href="'.$e($link).'" rel="sponsored nofollow noreferrer">Ver oferta</a></aside>';
    }

    private static function metricPath(string $path): string
    {
        return strlen($path)<=200 && preg_match('~^/(?:[a-zA-Z0-9_-]+/)*[a-zA-Z0-9_-]*$~D',$path) ? $path : '(otras rutas)';
    }

    private static function signature(string $id,string $page): string
    {
        return hash_hmac('sha256','ads-click:'.$id.':'.$page,KeyStore::vault());
    }

    public static function visit(string $id,mixed $page,mixed $signature): ?string
    {
        if (!preg_match('/^[a-f0-9]{16}$/D',$id) || !is_string($page) || strlen($page)>200 || !is_string($signature)
            || !hash_equals(self::signature($id,$page),$signature) || !FrontendEngine::isModuleActive(self::ID) || PaywallEngine::hasAccess()) return null;
        $ad=self::campaigns()[$id]??null;
        $now=time();
        if (!$ad || !$ad['enabled'] || ($ad['starts']!==null && $ad['starts']>$now) || ($ad['ends']!==null && $ad['ends']<=$now)
            || ($ad['page']!=='' && self::metricPath($ad['page'])!==$page) || !self::safeDestination($ad['destination'])) return null;
        $purpose=strtolower(($_SERVER['HTTP_SEC_PURPOSE']??'').' '.($_SERVER['HTTP_PURPOSE']??''));
        if (!str_contains($purpose,'prefetch') && !str_contains($purpose,'prerender')) {
            try {
                Database::getInstance()->execute('INSERT INTO goldensite_ads_clicks (campaign_id,page_path,clicks) VALUES (?,?,1) ON DUPLICATE KEY UPDATE clicks=clicks+1',[$id,$page]);
            } catch (\PDOException $error) { error_log('ADS click could not be recorded.'); }
        }
        return $ad['destination'];
    }

    public static function results(): array
    {
        return Database::getInstance()->fetchAll('SELECT campaign_id,page_path,clicks FROM goldensite_ads_clicks ORDER BY clicks DESC,campaign_id,page_path LIMIT 100');
    }

    public static function decorate(string $html, string $top, string $bottom): string
    {
        if ((http_response_code() ?: 200)!==200 || stripos($html,'</body>')===false) return $html;
        if ($top !== '' && (preg_match('~<main\b[^>]*>~i', $html, $main, PREG_OFFSET_CAPTURE)
            || preg_match('~<body\b[^>]*>~i', $html, $main, PREG_OFFSET_CAPTURE))) {
            $html = substr_replace($html, $top, $main[0][1]+strlen($main[0][0]), 0);
        }
        $offset = strripos($html,'</main>');
        if ($offset === false) $offset = strripos($html,'</body>');
        $html = substr_replace($html, $bottom, $offset, 0);
        $head = stripos($html,'</head>');
        return $head !== false && str_contains($html, 'data-gs-ad')
            ? substr_replace($html, '<link rel="stylesheet" href="/assets/ads.css">', $head, 0) : $html;
    }
}
