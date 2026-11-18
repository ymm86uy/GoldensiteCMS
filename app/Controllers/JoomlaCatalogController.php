<?php
namespace App\Controllers;

use App\Core\{Auth, CatalogMedia, CmsMigration, Csrf, Database, Environment, FrontendEngine, Settings};

final class JoomlaCatalogController
{
    private const BASE = '/index.php/midis';
    private const PER_PAGE = 5;

    public function home(): void
    {
        $this->render('home', ['title'=>'Inicio']);
    }

    public function catalog(): void
    {
        $this->listing();
    }

    public function recent(): void
    {
        $this->listing(recent: true);
    }

    public function search(): void
    {
        $this->listing(search: true);
    }

    public function category(string $alias): void
    {
        if (!preg_match('/^([1-9][0-9]{0,9})(?:-.+)?$/D', $alias, $match)) { $this->missing(); return; }
        $this->listing((int)$match[1]);
    }

    private function listing(?int $categoryId = null, bool $recent = false, bool $search = false): void
    {
        $db = Database::getInstance();
        $categoryId ??= $this->number('catid');
        $category = $categoryId ? $db->fetchOne('SELECT * FROM goldensite_joomla_categories WHERE source_id=? AND is_visible=1', [$categoryId]) : null;
        if ($categoryId && !$category) { $this->missing(); return; }
        $query = $search && is_string($_GET['q'] ?? null) ? mb_substr(trim($_GET['q']),0,120) : '';
        $tagId = $search ? $this->number('tag') : 0;
        $tag = $tagId ? $db->fetchOne('SELECT title FROM goldensite_joomla_tags WHERE source_id=?', [$tagId]) : null;
        if ($tagId && !$tag) { $this->missing(); return; }
        $matchMode = in_array($_GET['match'] ?? '', ['all','any','exact'], true) ? $_GET['match'] : 'all';
        $sort = in_array($_GET['sort'] ?? '', ['recent','popular','title'], true) ? $_GET['sort'] : 'recent';
        $order = match ($sort) {
            'popular' => 'downloads DESC,source_id DESC',
            'title' => 'title ASC,source_id ASC',
            default => 'created_at DESC,source_id DESC',
        };
        $offset = min(100000, $this->number('start'));
        $categories = $search || $recent ? [] : $db->fetchAll(
            'SELECT * FROM goldensite_joomla_categories WHERE parent_id '.($categoryId ? '=?' : '<=1').' AND is_visible=1 ORDER BY position,source_id',
            $categoryId ? [$categoryId] : []
        );
        $searchCategories = $search ? $db->fetchAll('SELECT source_id,title FROM goldensite_joomla_categories WHERE is_visible=1 ORDER BY title') : [];
        $items = [];
        $total = 0;
        if ($category || $recent || ($search && ($query !== '' || $tagId))) {
            $conditions = ['is_visible=1', 'category_id IN (SELECT source_id FROM goldensite_joomla_categories WHERE is_visible=1)', self::viewFilter()];
            $params = [];
            if ($categoryId) { $conditions[]='category_id=?'; $params[]=$categoryId; }
            if ($tagId) {
                $conditions[] = 'source_id IN (SELECT entry_id FROM goldensite_joomla_entry_tags WHERE tag_id=?)';
                $params[] = $tagId;
            }
            if ($query !== '') {
                $words = $matchMode === 'exact' ? [$query] : array_slice(preg_split('/\s+/u', $query, -1, PREG_SPLIT_NO_EMPTY) ?: [$query], 0, 12);
                $parts = [];
                foreach ($words as $word) {
                    $parts[] = "(title LIKE ? ESCAPE '!' OR version_name LIKE ? ESCAPE '!')";
                    $needle = '%'.strtr($word, ['!'=>'!!','%'=>'!%','_'=>'!_']).'%';
                    array_push($params, $needle, $needle);
                }
                $conditions[] = '('.implode($matchMode === 'any' ? ' OR ' : ' AND ', $parts).')';
            }
            $where = implode(' AND ', $conditions);
            $total = (int)$db->fetchOne("SELECT COUNT(*) AS total FROM goldensite_joomla_entries WHERE $where", $params)['total'];
            $items = $db->fetchAll("SELECT source_id,title,description,version_name,size_label,downloads,created_at FROM goldensite_joomla_entries WHERE $where ORDER BY $order LIMIT ".self::PER_PAGE." OFFSET $offset", $params);
        }
        $title = $tag['title'] ?? $category['title'] ?? ($recent ? 'Novedades MIDI' : ($search ? 'Buscador MIDI' : 'Biblioteca MIDI'));
        $path = $recent ? '/index.php/novedades' : ($search ? '/index.php/buscador/search' : self::BASE);
        $pageUrl = static fn(int $start): string => $path.'?'.http_build_query(array_filter(['catid'=>$categoryId,'q'=>$query,'tag'=>$tagId,'match'=>$matchMode,'sort'=>$sort,'start'=>$start]));
        $this->render('catalog',compact('categories','category','items','total','offset','title','query','pageUrl','search','matchMode','sort','searchCategories','categoryId') + ['perPage'=>self::PER_PAGE]);
    }

    public function legacyEntry(string $category, string $id): void
    {
        if (!preg_match('/^([1-9][0-9]{0,9})(?:-.+)?$/D',$id,$match) ||
            !Database::getInstance()->fetch("SELECT e.source_id FROM goldensite_joomla_entries e JOIN goldensite_joomla_categories c ON c.source_id=e.category_id AND c.is_visible=1 WHERE e.source_id=? AND e.is_visible=1 AND ".self::viewFilter('e.source_id'),[(int)$match[1]])) { $this->missing(); return; }
        header('Cache-Control: private, no-store');
        header('Location: /biblioteca/ficha/'.(int)$match[1],true,301);
    }

    public function entry(string $id): void
    {
        if (!preg_match('/^([1-9][0-9]{0,9})(?:-.+)?$/D', $id, $match)) { $this->missing(); return; }
        $entry = Database::getInstance()->fetchOne(
            'SELECT e.source_id,e.title,e.description,e.version_name,e.size_label,e.downloads,e.created_at,c.title AS category,c.source_id AS category_id,m.view_mode,m.preview_mode,m.download_mode,m.preview_id,m.original_id
             FROM goldensite_joomla_entries e JOIN goldensite_joomla_categories c ON c.source_id=e.category_id
             LEFT JOIN goldensite_catalog_media m ON m.entry_id=e.source_id
             WHERE e.source_id=? AND e.is_visible=1 AND c.is_visible=1', [(int)$match[1]]
        );
        if (!$entry || !CatalogMedia::allowed($entry['view_mode']??'public')) { $this->missing(); return; }
        $this->render('entry', ['title'=>$entry['title'],'entry'=>$entry]);
    }

    public function editorial(string $slug, ?string $articleSlug = null): void
    {
        $db = Database::getInstance();
        $menu = $db->fetchOne("SELECT view_name,target_id,label FROM goldensite_joomla_menus WHERE path=? AND component='com_content' ORDER BY position LIMIT 1", [$slug]);
        if (!$menu || !in_array($menu['view_name'], ['article','category'], true)) { $this->missing(); return; }
        $where = $menu['view_name'] === 'article' ? 'a.source_id' : 'a.category_id';
        $params = [$menu['target_id']];
        if ($articleSlug !== null || $menu['view_name'] === 'article') {
            if ($articleSlug !== null) { $where .= '=? AND p.slug'; $params[] = $articleSlug; }
            $article = $db->fetchOne("SELECT p.title,p.content,a.category_title FROM goldensite_joomla_articles a JOIN goldensite_posts p ON p.slug=a.slug WHERE $where=? AND p.status='published'", $params);
            if (!$article) { $this->missing(); return; }
            $article['content'] = CmsMigration::clean((string)$article['content']);
            $this->render('article', ['title'=>$article['title'],'article'=>$article]);
            return;
        }
        $items = $db->fetchAll("SELECT p.slug,p.title,a.category_title FROM goldensite_joomla_articles a JOIN goldensite_posts p ON p.slug=a.slug WHERE $where=? AND p.status='published' ORDER BY p.created_at DESC,p.id DESC", $params);
        $this->render('editorial', ['title'=>$menu['label'],'articles'=>$items,'editorialPath'=>'/index.php/'.$slug]);
    }

    public function tags(): void
    {
        $tags = Database::getInstance()->fetchAll('SELECT source_id,title FROM goldensite_joomla_tags ORDER BY title,source_id');
        $this->render('tags', ['title'=>'Etiquetas','tags'=>$tags]);
    }

    public function contact(): void
    {
        header('Location: /contacto', true, 302);
    }

    public function unavailable(): void
    {
        http_response_code(503);
        $this->render('unavailable',['title'=>'Sección pendiente de migración']);
    }

    private static function viewFilter(string $column = 'source_id'): string
    {
        return "$column NOT IN (SELECT entry_id FROM goldensite_catalog_media WHERE view_mode "
            .(Auth::check() ? "= 'off')" : "<> 'public')");
    }

    private function number(string $key): int
    {
        $value = $_GET[$key] ?? '';
        return is_string($value) && preg_match('/^[0-9]{1,10}$/D',$value) ? (int)$value : 0;
    }

    private function missing(): void
    {
        http_response_code(404);
        $this->render('missing', ['title'=>'Página no encontrada']);
    }

    private function render(string $screen, array $data): void
    {
        if (in_array(parse_url(Environment::get('APP_URL'),PHP_URL_HOST),['localhost','127.0.0.1','::1'],true) || $screen==='unavailable') header('X-Robots-Tag: noindex, nofollow');
        $brand=Settings::get('joomla_catalog_brand',Settings::get('site_title','GoldenSiteCMS'));
        $downloadModule=FrontendEngine::isModuleActive('server-downloads');
        $canSubmit=$downloadModule && Auth::check();
        $csrf=$canSubmit?Csrf::generate():'';
        $flash=$_SESSION['catalog_flash']??null;
        unset($_SESSION['catalog_flash']);
        header('Cache-Control: private, no-store');
        header("Content-Security-Policy: default-src 'self'; script-src 'none'; object-src 'none'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
        extract($data, EXTR_SKIP);
        $nav = Database::getInstance()->fetchAll("SELECT label,path,shop FROM goldensite_joomla_menus WHERE path IN ('home','midis','novedades','etiquetas','foro','buscador','shop','contenido','tools','vst','partituras') ORDER BY position,source_id");
        if (!in_array('partituras', array_column($nav, 'path'), true)) $nav[] = ['label'=>'Partituras','path'=>'partituras','shop'=>0];
        require dirname(__DIR__,2).'/views/frontend/joomla_catalog.php';
    }
}
