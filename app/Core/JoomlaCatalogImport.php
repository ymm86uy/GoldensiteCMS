<?php
namespace App\Core;

use RuntimeException;
use Throwable;

final class JoomlaCatalogImport
{
    public static function stage(): array
    {
        $root=realpath(CatalogMedia::ROOT);
        if (!$root || is_link(CatalogMedia::ROOT)) throw new \InvalidArgumentException('Prepara la bandeja privada de migración.');
        $paths=[];
        foreach (['site.sql','jdownloads.sql'] as $name) {
            $path=$root.DIRECTORY_SEPARATOR.$name;
            if (!is_file($path) || is_link($path) || filesize($path)>CmsDumpReader::MAX_BYTES) throw new \InvalidArgumentException('Coloca site.sql y jdownloads.sql en la bandeja privada, hasta 64 MiB cada uno.');
            $paths[]=$path;
        }
        return $paths;
    }

    public static function fingerprint(array $paths): string
    {
        return hash('sha256',implode(':',array_map(static fn($file)=>hash_file('sha256',$file),$paths)));
    }

    public static function inspect(array $paths,string $prefix): array
    {
        self::validatePrefix($prefix);
        $site=CmsDumpReader::read(self::read($paths[0]),onlyTables:[$prefix.'jdownloads_categories'])['tables'];
        $downloads=CmsDumpReader::read(self::read($paths[1]),onlyTables:[$prefix.'jdownloads_files'])['tables'];
        $entries = $downloads[$prefix.'jdownloads_files'] ?? [];
        return ['categories'=>count($site[$prefix.'jdownloads_categories']??[]),'entries'=>count($entries),
            'inventory'=>CatalogMedia::inventory($entries)];
    }

    private static function validatePrefix(string $prefix): void
    {
        if (!preg_match('/^[a-zA-Z0-9]+_$/D',$prefix)) throw new \InvalidArgumentException('Indica el prefijo de las tablas de Joomla, por ejemplo awgt2_.');
    }

    private static function read(string $path): string
    {
        if (!is_file($path) || filesize($path)>CmsDumpReader::MAX_BYTES) throw new \InvalidArgumentException('SQL ausente o mayor a 64 MiB.');
        $value=file_get_contents($path,false,null,0,CmsDumpReader::MAX_BYTES+1);
        if ($value===false) throw new \InvalidArgumentException('No se pudo leer el SQL.');
        return $value;
    }

    public static function import(array $paths,string $prefix): array
    {
        self::validatePrefix($prefix);
        $site = self::read($paths[0]);
        $tables = CmsDumpReader::read($site, onlyTables: array_map(fn($name) => $prefix . $name,
            ['content','categories','jdownloads_categories','menu','tags','contentitem_tag_map']))['tables'];
        $posts = CmsMigration::plan($site, 'joomla')['items'];
        unset($site);
        $downloads = CmsDumpReader::read(self::read($paths[1]), onlyTables: [$prefix . 'jdownloads_files'])['tables'][$prefix . 'jdownloads_files'] ?? [];
        $categories = $tables[$prefix . 'jdownloads_categories'] ?? [];
        if (!$downloads || !$categories) throw new RuntimeException('Faltan las tablas de archivos o categorías de jDownloads.');
        $db = Database::getInstance()->getConnection();
        $sql = file_get_contents(dirname(__DIR__,2) . '/database/joomla-catalog.sql');
        foreach (explode(';', $sql) as $ddl) if (trim($ddl) !== '') $db->exec($ddl);
        CatalogMedia::install();
        if ($db->query('SELECT 1 FROM goldensite_catalog_media LIMIT 1')->fetchColumn()) throw new \InvalidArgumentException('El catálogo tiene vínculos o permisos propios. La recarga requiere una migración controlada.');
        $backup=DatabaseBackup::directory().'/before-joomla-catalog-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.sql';
        DatabaseBackup::write($backup);

        $text = static fn($value, int $limit = 255): string => mb_substr(trim(html_entity_decode(strip_tags((string)$value), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, $limit);
        $slug = static fn($value): string => mb_substr(trim((string)$value), 0, 191);
        $date = static fn($value): ?string => preg_match('/^[1-9][0-9]{3}-[0-9]{2}-[0-9]{2} [0-9:]{8}$/D', (string)$value) ? $value : null;
        $visible = static fn($row): bool => (int)$row['published'] === 1 && (int)$row['access'] === 1 &&
            (int)($row['user_access'] ?? 0) === 0 && empty($row['password']) && empty($row['password_md5']) &&
            (int)($row['use_timeframe'] ?? 0) === 0;
        $categoryIndex = array_column($categories, null, 'id');
        $publicCategories = [];
        foreach ($categories as $row) {
            $seen = [];
            $node = $row;
            $ok = true;
            while ($node) {
                $id = (int)$node['id'];
                if (isset($seen[$id]) || !$visible($node)) { $ok = false; break; }
                $seen[$id] = true;
                $parent = (int)$node['parent_id'];
                if ($parent <= 1) break;
                $node = $categoryIndex[$parent] ?? null;
                if (!$node) $ok = false;
            }
            $publicCategories[(int)$row['id']] = $ok && (int)$row['id'] > 1;
        }
        $db->beginTransaction();
        try {
            foreach (['entry_tags','categories','menus','articles','tags'] as $table) $db->exec("DELETE FROM goldensite_joomla_$table");
            $db->exec('DELETE FROM goldensite_joomla_entries WHERE source_id<2000000000');
            $insert = $db->prepare('INSERT INTO goldensite_joomla_categories VALUES (?,?,?,?,?,?,?)');
            foreach ($categories as $r) $insert->execute([(int)$r['id'], (int)$r['parent_id'], $text($r['title']), $slug($r['alias']), $text($r['description'], 3000), (int)$r['lft'], (int)$publicCategories[(int)$r['id']]]);
            $insert = $db->prepare('INSERT INTO goldensite_joomla_entries VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($downloads as $r) {
                if ((int)$r['id']>=2000000000) throw new RuntimeException('El ID de origen supera el rango reservado.');
                $cid = (int)$r['catid'];
                $insert->execute([(int)$r['id'], $cid, $text($r['title']), $slug($r['alias']), $text($r['description']."\n".$r['description_long'], 4000), $text($r['release']), $text($r['size'], 40), max(0,(int)$r['downloads']), $date($r['created']), $text($r['url_download']), $text($r['preview_filename']), (int)$r['ordering'], (int)(($publicCategories[$cid] ?? false) && $visible($r))]);
            }
            $articles = array_column($tables[$prefix.'content'] ?? [], null, 'id');
            $editorial = array_column($tables[$prefix.'categories'] ?? [], null, 'id');
            $insert = $db->prepare('INSERT INTO goldensite_joomla_articles VALUES (?,?,?,?)');
            foreach ($posts as $r) {
                $origin = $articles[$r['source_id']] ?? [];
                $cid = (int)($origin['catid'] ?? 0);
                $insert->execute([$r['source_id'], $r['slug'], $cid, $text($editorial[$cid]['title'] ?? '')]);
            }
            $insert = $db->prepare('INSERT INTO goldensite_joomla_menus VALUES (?,?,?,?,?,?,?,?)');
            foreach ($tables[$prefix.'menu'] ?? [] as $r) {
                if ((int)$r['client_id'] !== 0 || (int)$r['published'] !== 1 || (int)$r['access'] !== 1 ||
                    !in_array($r['menutype'], ['mainmenu','menu-extra'], true)) continue;
                $path = $r['path'];
                if (!preg_match('~^[a-z0-9_-]+(?:/[a-z0-9_-]+)*$~iD', $path)) continue;
                parse_str((string)parse_url($r['link'], PHP_URL_QUERY), $link);
                $component = is_string($link['option'] ?? null) ? $link['option'] : '';
                $view = is_string($link['view'] ?? null) ? $link['view'] : '';
                $insert->execute([(int)$r['id'], $text($r['title']), mb_substr($path,0,255), $component, $view, (int)($link['id'] ?? 0), (int)$r['lft'], (int)($r['link'] === 'https://tiendamusicasampler.com/')]);
            }
            $insert = $db->prepare('INSERT INTO goldensite_joomla_tags VALUES (?,?)');
            foreach ($tables[$prefix.'tags'] ?? [] as $r) if ((int)$r['published'] === 1 && (int)$r['access'] === 1 && (int)$r['id'] > 1) $insert->execute([(int)$r['id'], $text($r['title'])]);
            $insert = $db->prepare("INSERT IGNORE INTO goldensite_joomla_entry_tags (entry_id,tag_id)
                SELECT e.source_id,t.source_id FROM goldensite_joomla_entries e JOIN goldensite_joomla_tags t ON t.source_id=? WHERE e.source_id=?");
            foreach ($tables[$prefix.'contentitem_tag_map'] ?? [] as $r) {
                if ($r['type_alias'] === 'com_jdownloads.download') $insert->execute([(int)$r['tag_id'],(int)$r['content_item_id']]);
            }
            $db->commit();
        } catch (Throwable $error) {
            if ($db->inTransaction()) $db->rollBack();
            throw $error;
        }
        Settings::set('joomla_catalog_enabled', '1');
    $summary=[];
    foreach (['entries','categories','menus','articles','tags','entry_tags'] as $table) $summary[$table]=(int)$db->query("SELECT COUNT(*) FROM goldensite_joomla_$table")->fetchColumn();
    return $summary + ['backup'=>basename($backup)];
    }
}
