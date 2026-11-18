<?php
namespace App\Core;

final class CatalogMedia
{
    public const ROOT = __DIR__ . '/../../storage/joomla-media-inbox';
    public const MODES = ['off','member','public'];

    public static function install(): void
    {
        $db = Database::getInstance()->getConnection();
        foreach (explode(';', file_get_contents(dirname(__DIR__,2).'/database/catalog-media.sql')) as $sql) {
            if (trim($sql) !== '') $db->exec($sql);
        }
    }

    public static function allowed(string $mode): bool
    {
        return $mode === 'public' || ($mode === 'member' && Auth::check());
    }

    public static function mime(string $path, string $name, bool $preview): ?string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!$preview) {
            return in_array($extension,['mid','midi','kar'],true) && file_get_contents($path,false,null,0,4)==='MThd'
                ? 'application/octet-stream' : null;
        }
        $accepted = ['mp3'=>'audio/mpeg','ogg'=>'audio/ogg','wav'=>'audio/x-wav'];
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if ($extension==='wav' && $mime==='audio/wav') $mime='audio/x-wav';
        return isset($accepted[$extension]) && $mime===$accepted[$extension] ? $mime : null;
    }

    public static function inventory(array $rows): array
    {
        $files = self::inboxFiles();
        $references = [];
        foreach ($rows as $row) foreach (['url_download','preview_filename'] as $column) {
            $name = strtolower((string)($row[$column] ?? ''));
            if ($name !== '') $references[$column][$name] = ($references[$column][$name] ?? 0) + 1;
        }
        $summary = ['candidate'=>0,'missing'=>0,'ambiguous'=>0];
        foreach ($references as $names) foreach ($names as $name=>$count) {
            if (!array_key_exists($name,$files)) $summary['missing'] += $count;
            elseif ($count > 1 || $files[$name] === null) $summary['ambiguous'] += $count;
            else $summary['candidate']++;
        }
        return $summary;
    }

    private static function inboxFiles(): array
    {
        $root = realpath(self::ROOT);
        $files = [];
        if ($root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
            $prefix = rtrim(str_replace('\\','/',$root),'/').'/';
            foreach ($iterator as $info) {
                if (!$info->isFile() || $info->isLink()) continue;
                $resolved = str_replace('\\','/', (string)$info->getRealPath());
                if (!str_starts_with($resolved,$prefix)) continue;
                $name = strtolower($info->getFilename());
                if (!preg_match('/\.(mid|midi|kar|mp3|wav|ogg)$/iD',$name)) continue;
                if (count($files)>=150000 && !isset($files[$name])) throw new \InvalidArgumentException('La bandeja supera 150.000 nombres.');
                $files[$name] = array_key_exists($name,$files) ? null : $resolved;
            }
        }
        return $files;
    }

    public static function scan(int $after): array
    {
        $db = Database::getInstance();
        $rows = $db->fetchAll("SELECT e.source_id,e.title,e.original_file,e.preview_file,m.original_id,m.preview_id
            FROM goldensite_joomla_entries e LEFT JOIN goldensite_catalog_media m ON m.entry_id=e.source_id
            WHERE e.is_visible=1 AND e.source_id>? ORDER BY e.source_id LIMIT 1000",[$after]);
        $files = self::inboxFiles();
        $stats=['ready'=>0,'missing'=>0,'ambiguous'=>0,'linked'=>0,'invalid'=>0];
        $matches=[];
        foreach ($rows as $row) foreach (['original','preview'] as $kind) {
            if ($row[$kind.'_id']) { $stats['linked']++; continue; }
            $column=$kind.'_file';
            $name=$row[$column];
            if ($name==='') continue;
            $key=strtolower($name);
            if (!array_key_exists($key,$files)) { $stats['missing']++; continue; }
            $path=$files[$key];
            if (!$path || (int)$db->fetch("SELECT COUNT(*) AS n FROM goldensite_joomla_entries WHERE $column=?",[$name])['n']!==1) {
                $stats['ambiguous']++; continue;
            }
            $size=filesize($path);
            $mime=$size>0 && $size<=20971520 ? self::mime($path,$name,$kind==='preview') : null;
            if (!$mime) { $stats['invalid']++; continue; }
            $stats['ready']++;
            if (count($matches)<25) $matches[]=['entry'=>(int)$row['source_id'],'kind'=>$kind,'title'=>$row['title'],'name'=>$name,'path'=>$path,'size'=>$size,'mime'=>$mime,'sha'=>hash_file('sha256',$path)];
        }
        return ['stats'=>$stats,'matches'=>$matches,'after'=>$after,'next'=>$rows ? (int)end($rows)['source_id'] : null];
    }

    public static function review(int $id,bool $approve): void
    {
        $db=Database::getInstance()->getConnection();
        $db->beginTransaction();
        try {
            $query=$db->prepare("SELECT s.* FROM goldensite_catalog_submissions s JOIN goldensite_joomla_categories c ON c.source_id=s.category_id
                JOIN goldensite_downloads d ON d.id=s.file_id AND d.status='active'
                WHERE s.id=? AND s.status='pending' AND c.is_visible=1 FOR UPDATE");
            $query->execute([$id]);
            $row=$query->fetch(\PDO::FETCH_ASSOC);
            if (!$row) throw new \InvalidArgumentException('El aporte ya fue revisado o la categoría no está disponible.');
            if ($approve) {
                $entry=2000000000+$id;
                $db->prepare("INSERT INTO goldensite_joomla_entries (source_id,category_id,title,alias,description,version_name,size_label,downloads,created_at,original_file,preview_file,position,is_visible)
                    VALUES (?,?,?,'',?,'','',0,NOW(),'','',0,1)")->execute([$entry,$row['category_id'],$row['title'],$row['description']]);
                $db->prepare("INSERT INTO goldensite_catalog_media (entry_id,original_id,download_mode) VALUES (?,?,'member')")->execute([$entry,$row['file_id']]);
            } else {
                $db->prepare("UPDATE goldensite_downloads SET status='archived',allow_legacy_links=0 WHERE id=?")->execute([$row['file_id']]);
            }
            $db->prepare('UPDATE goldensite_catalog_submissions SET status=? WHERE id=?')->execute([$approve?'approved':'rejected',$id]);
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            throw $e;
        }
        ConsoleView::audit('catalog_submission_reviewed','submission:'.$id);
    }

    public static function audio(string $path,string $mime): never
    {
        $size=filesize($path);
        $start=0;
        $end=$size-1;
        $range=$_SERVER['HTTP_RANGE']??'';
        if ($range!=='') {
            $valid=preg_match('/^bytes=(\d{1,12})-(\d{0,12})$/D',$range,$matches);
            if ($valid) { $start=(int)$matches[1]; if ($matches[2]!=='') $end=min($end,(int)$matches[2]); }
            elseif (preg_match('/^bytes=-(\d{1,12})$/D',$range,$matches) && (int)$matches[1]>0) {
                $start=max(0,$size-(int)$matches[1]);
                $valid=true;
            }
            if (!$valid||$start>$end) { http_response_code(416); header('Content-Range: bytes */'.$size); exit; }
            http_response_code(206);
            header("Content-Range: bytes $start-$end/$size");
        }
        $stream=fopen($path,'rb');
        if (!$stream) { http_response_code(404); exit; }
        while (ob_get_level()>0) ob_end_clean();
        if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: '.$mime);
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: sandbox; default-src 'none'");
        header('Cache-Control: no-store, private');
        header('Accept-Ranges: bytes');
        header('Content-Length: '.($end-$start+1));
        fseek($stream,$start);
        $left=$end-$start+1;
        while ($left>0 && !feof($stream)) {
            $bytes=fread($stream,min(65536,$left));
            if ($bytes===false || $bytes==='') break;
            echo $bytes;
            $left-=strlen($bytes);
        }
        fclose($stream);
        exit;
    }

    public static function link(array $plan): int
    {
        if (!$plan['matches']) return 0;
        $backup = DatabaseBackup::directory().'/before-catalog-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.sql';
        DatabaseBackup::write($backup);
        $directory=dirname(__DIR__,2).'/storage/downloads';
        if (!is_dir($directory) && !mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Almacén no disponible.');
        $db=Database::getInstance()->getConnection();
        $insert=$db->prepare("INSERT INTO goldensite_downloads (title,description,original_name,file_path,folder,version_label,access_mode,file_bytes,sha256,mime_type,allow_legacy_links) VALUES (?,?,?,?,'Joomla','1.0','public_link',?,?,?,0)");
        $copied=[];
        $db->beginTransaction();
        try {
            foreach ($plan['matches'] as $item) {
                $column=$item['kind']==='preview'?'preview_id':'original_id';
                $db->prepare('INSERT IGNORE INTO goldensite_catalog_media (entry_id) VALUES (?)')->execute([$item['entry']]);
                $check=$db->prepare("SELECT $column FROM goldensite_catalog_media WHERE entry_id=? FOR UPDATE");
                $check->execute([$item['entry']]);
                if ($check->fetchColumn()) continue;
                $name='vault_'.bin2hex(random_bytes(16)).'.bin';
                $target=$directory.'/'.$name;
                $source=fopen($item['path'],'rb');
                $output=fopen($target,'xb');
                if (is_resource($output)) $copied[]=$target;
                if (!$source || !$output) {
                    if (is_resource($source)) fclose($source);
                    if (is_resource($output)) fclose($output);
                    throw new \RuntimeException('No se pudo copiar el archivo.');
                }
                try { $size=stream_copy_to_stream($source,$output,20971521); }
                finally { fclose($source); fclose($output); }
                if ($size!==$item['size'] || !hash_equals($item['sha'],hash_file('sha256',$target))) throw new \RuntimeException('El archivo cambió durante la copia.');
                @chmod($target,0600);
                $insert->execute([mb_substr($item['title'],0,200),'',$item['name'],$name,$size,$item['sha'],$item['mime']]);
                $id=(int)$db->lastInsertId();
                $db->prepare("UPDATE goldensite_catalog_media SET $column=? WHERE entry_id=?")->execute([$id,$item['entry']]);
            }
            $db->commit();
        } catch (\Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            foreach ($copied as $target) @unlink($target);
            throw $e;
        }
        return count($copied);
    }
}
