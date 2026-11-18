<?php
namespace App\Core;
final class DownloadLibrary
{
    public static function path(array $file): ?string
    {
        $base=realpath(dirname(__DIR__,2).'/storage/downloads'); if (!$base) return null;
        $stored=(string)($file['file_path']??''); $name=basename(str_replace('\\','/',$stored));
        $path=realpath($stored);
        if (!$path&&preg_match('/^vault_[a-f0-9]{32}\.bin$/D',$name)) $path=realpath($base.'/'.$name);
        if (!$path||!is_file($path)||!str_starts_with(strtolower(str_replace('\\','/',$path)),strtolower(str_replace('\\','/',$base)).'/')) return null;
        return $path;
    }
    private static function fields(array $data): array
    {
        $title=ConsoleView::field($data,'title',200); $folder=ConsoleView::field($data,'folder',80)?:'General'; $version=ConsoleView::field($data,'version_label',32)?:'1.0'; $mode=ConsoleView::field($data,'access_mode',20)?:'public_link';
        $description=$data['description']??''; if (!is_string($description)||strlen($description)>4000||!mb_check_encoding($description,'UTF-8')||str_contains($description,"\0")) throw new \InvalidArgumentException('Descripción inválida.');
        if ($title===''||!in_array($mode,['public_link','authenticated','specific_user'],true)) throw new \InvalidArgumentException('Revisa título y permisos del recurso.');
        $user=filter_var($data['assigned_user']??0,FILTER_VALIDATE_INT)?:0;
        if ($mode==='specific_user'&&($user<1||!Database::getInstance()->fetch("SELECT id FROM users WHERE id=? AND status='active'",[$user]))) throw new \InvalidArgumentException('Selecciona una cuenta activa para este recurso.');
        return [$title,trim($description),$folder,$version,$mode,$mode==='specific_user'?$user:null];
    }
    public static function upload(array $data,array $file): int
    {
        [$title,$description,$folder,$version,$mode,$user]=self::fields($data);
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>20971520) throw new \InvalidArgumentException('Selecciona un archivo permitido de hasta 20 MB.');
        $original=$file['name']??''; if (!is_string($original)||strlen($original)>255) throw new \InvalidArgumentException('Nombre de archivo inválido.'); $original=basename(str_replace('\\','/',$original));
        $base=dirname(__DIR__,2).'/storage/downloads'; if (!is_dir($base)&&!mkdir($base,0700,true)) throw new \RuntimeException('Almacenamiento no disponible.');
        $name='vault_'.bin2hex(random_bytes(16)).'.bin'; $path=$base.'/'.$name;
        if (!UploadGuard::move($file['tmp_name'],$path)) throw new \InvalidArgumentException('No se pudo guardar el archivo. Revisa su tamaño y los permisos del almacenamiento.'); @chmod($path,0600);
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $parent=filter_var($data['parent_id']??0,FILTER_VALIDATE_INT)?:0; $resource=null;
            if ($parent>0) { $prior=$db->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status<>'deleted' FOR UPDATE",[$parent]); if (!$prior) throw new \InvalidArgumentException('El recurso para la nueva versión no existe.'); $resource=$prior['resource_id']?:$prior['id']; $db->fetch('SELECT id FROM goldensite_downloads WHERE id=? FOR UPDATE',[$resource]); if ($db->fetch('SELECT id FROM goldensite_downloads WHERE COALESCE(resource_id,id)=? AND version_label=?',[$resource,$version])) throw new \InvalidArgumentException('Esa versión ya existe para este recurso.'); if (($data['override_permissions']??'')!=='1') { $mode=$prior['access_mode']; $user=$prior['assigned_user']; $folder=$prior['folder']; } }
            $db->execute('INSERT INTO goldensite_downloads (title,description,original_name,file_path,folder,version_label,resource_id,access_mode,assigned_user,file_bytes,sha256,mime_type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)',[$title,$description,$original,$name,$folder,$version,$resource,$mode,$user,filesize($path),hash_file('sha256',$path),mime_content_type($path)?:'application/octet-stream']);
            $id=(int)$pdo->lastInsertId(); $pdo->commit(); ConsoleView::audit('download_resource_uploaded','file:'.$id); return $id;
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); @unlink($path); throw $e; }
    }
    public static function update(int $id,array $data): void
    {
        [$title,$description,$folder,$version,$mode,$user]=self::fields($data);
        $db=Database::getInstance(); $file=$db->fetch("SELECT id FROM goldensite_downloads WHERE id=? AND status<>'deleted'",[$id]); if (!$file) throw new \InvalidArgumentException('Recurso no disponible.');
        // Bytes and version identifiers are immutable; upload a new version to change the file.
        $db->execute('UPDATE goldensite_downloads SET title=?,description=?,folder=?,access_mode=?,assigned_user=? WHERE id=?',[$title,$description,$folder,$mode,$user,$id]); ConsoleView::audit('download_resource_updated','file:'.$id);
    }
    public static function createLink(int $id,array $data): string
    {
        try { self::requireStandalone($id); } catch (\DomainException $e) { throw new \InvalidArgumentException($e->getMessage()); }
        $db=Database::getInstance(); $file=$db->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status='active'",[$id]);
        if (!$file||!self::path($file)) throw new \InvalidArgumentException('Archivo no disponible.');
        $ttl=filter_var($data['ttl_hours']??24,FILTER_VALIDATE_INT); $uses=filter_var($data['max_uses']??0,FILTER_VALIDATE_INT); $user=filter_var($data['bound_user']??0,FILTER_VALIDATE_INT)?:0;
        if ($ttl===false||$ttl<1||$ttl>720||$uses===false||$uses<0||$uses>1000000||($user>0&&!$db->fetch("SELECT id FROM users WHERE id=? AND status='active'",[$user]))) throw new \InvalidArgumentException('Revisa vencimiento de 1 a 720 horas, límite y cuenta destinataria.');
        $link=bin2hex(random_bytes(16)); $db->execute('INSERT INTO goldensite_download_links (id,file_id,bound_user,expires_at,max_uses) VALUES (?,?,?,?,?)',[$link,$id,$user?:null,time()+$ttl*3600,$uses]);
        ConsoleView::audit('download_link_created','file:'.$id); return self::url(['id'=>$link,'file_id'=>$id]);
    }
    public static function url(array $link): string
    {
        $token=hash_hmac('sha256','download-link:'.$link['id'].':'.$link['file_id'],KeyStore::vault()); return '/download/'.$link['file_id'].'?link='.$link['id'].'&token='.$token;
    }
    public static function shortcode(int $id,string $linkId=''): string
    {
        if (!FrontendEngine::isModuleActive('server-downloads')) return '';
        $db=Database::getInstance(); $file=$db->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status='active'",[$id]);
        if (!$file||!self::path($file)) return '';
        $actor=Auth::id(); $e=ConsoleView::escape(...);
        $style='display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:20px;padding:24px;margin:24px 0;border:1px solid currentColor;border-radius:14px;background:var(--card-bg,transparent);color:var(--text-color,inherit)';
        if (($file['access_mode']==='authenticated'&&!$actor)||($file['access_mode']==='specific_user'&&$actor!==(int)$file['assigned_user'])) return '<div style="'.$style.'"><p>Recurso reservado a usuarios autorizados.</p><a href="/login">Iniciar sesión</a></div>';
        $url=null;
        if ($linkId!=='') {
            $link=$db->fetch('SELECT * FROM goldensite_download_links WHERE id=? AND file_id=? AND revoked_at IS NULL AND expires_at>UNIX_TIMESTAMP() AND (max_uses=0 OR uses<max_uses)',[$linkId,$id]);
            if ($link&&($link['bound_user']===null||$actor===(int)$link['bound_user'])) $url=self::url($link);
        } elseif ((int)$file['allow_legacy_links']) $url=PaywallEngine::generateSignedDownloadUrl($id,900);
        if (!$url) return '<div style="'.$style.'"><p>Este recurso necesita un enlace de acceso vigente.</p><a href="/contacto">Solicitar acceso</a></div>';
        return '<div style="'.$style.'"><div><strong>'.$e($file['title']).'</strong><p style="margin:8px 0 0">Versión '.$e($file['version_label']).' · '.$e($file['original_name']).'</p></div><a style="padding:12px 18px;border:1px solid currentColor;border-radius:8px;color:inherit" href="'.$e($url).'" rel="nofollow">Descargar archivo</a></div>';
    }
    public static function revoke(string $id): void
    {
        if (!Database::getInstance()->fetch('SELECT id FROM goldensite_download_links WHERE id=?',[$id])) throw new \InvalidArgumentException('Enlace no disponible.');
        Database::getInstance()->execute('UPDATE goldensite_download_links SET revoked_at=NOW() WHERE id=?',[$id]); ConsoleView::audit('download_link_revoked',$id);
    }
    public static function state(int $id,string $state): void
    {
        if (!in_array($state,['active','archived'],true)) throw new \InvalidArgumentException('Estado inválido.');
        if (!Database::getInstance()->fetch("SELECT id FROM goldensite_downloads WHERE id=? AND status<>'deleted'",[$id])) throw new \InvalidArgumentException('Recurso no disponible.');
        Database::getInstance()->execute("UPDATE goldensite_downloads SET status=? WHERE id=? AND status<>'deleted'",[$state,$id]); ConsoleView::audit('download_resource_'.$state,'file:'.$id);
    }
    public static function purge(int $id): void
    {
        $db=Database::getInstance(); $file=$db->fetch('SELECT * FROM goldensite_downloads WHERE id=?',[$id]); if (!$file) throw new \InvalidArgumentException('Recurso no encontrado.');
        $path=self::path($file); $db->execute("UPDATE goldensite_downloads SET status='archived',allow_legacy_links=0 WHERE id=?",[$id]); $db->execute('UPDATE goldensite_download_links SET revoked_at=NOW() WHERE file_id=?',[$id]); if ($path&&!@unlink($path)) throw new \InvalidArgumentException('Recurso archivado y enlaces revocados. No se pudo retirar el archivo físico; revisa los permisos y vuelve a intentarlo.'); $db->execute("UPDATE goldensite_downloads SET status='deleted' WHERE id=?",[$id]); ConsoleView::audit('download_resource_deleted','file:'.$id);
    }
    private static function requireStandalone(int $id): void
    {
        $db=Database::getInstance();
        if ($db->fetch('SELECT entry_id FROM goldensite_catalog_media WHERE original_id=? OR preview_id=? LIMIT 1',[$id,$id]) ||
            $db->fetch('SELECT id FROM goldensite_catalog_submissions WHERE file_id=? LIMIT 1',[$id])) throw new \DomainException('Este archivo se entrega con los permisos de la biblioteca.');
    }

    public static function authorize(int $id,array $query): array
    {
        self::requireStandalone($id);
        if (!FrontendEngine::isModuleActive('server-downloads')) throw new \DomainException('El servicio de descargas no está activo.');
        $db=Database::getInstance(); $file=$db->fetch("SELECT * FROM goldensite_downloads WHERE id=? AND status='active'",[$id]); if (!$file) throw new \InvalidArgumentException('Archivo no disponible.');
        $actor=Auth::check()?(int)Auth::id():null;
        if (($file['access_mode']==='authenticated'&&!$actor)||($file['access_mode']==='specific_user'&&$actor!==(int)$file['assigned_user'])) throw new \DomainException('Inicia sesión con la cuenta autorizada para este recurso.');
        $link=null;
        if (isset($query['link'])) {
            $key=ConsoleView::field($query,'link',32); $token=ConsoleView::field($query,'token',64); if (!preg_match('/^[a-f0-9]{32}$/D',$key)) throw new \InvalidArgumentException('Enlace inválido.');
            $link=$db->fetch('SELECT * FROM goldensite_download_links WHERE id=? AND file_id=?',[$key,$id]);
            $expected=hash_hmac('sha256','download-link:'.$key.':'.$id,KeyStore::vault());
            if (!$link||!hash_equals($expected,$token)||$link['revoked_at']!==null||(int)$link['expires_at']<=time()||((int)$link['max_uses']>0&&(int)$link['uses']>=(int)$link['max_uses'])) throw new \InvalidArgumentException('El enlace venció, se revocó o alcanzó su límite.');
            if ($link['bound_user']!==null&&$actor!==(int)$link['bound_user']) throw new \DomainException('Este enlace está reservado a otra cuenta.');
        } else {
            $expires=filter_var($query['expires']??null,FILTER_VALIDATE_INT); $signature=ConsoleView::field($query,'signature',64);
            if (!(int)$file['allow_legacy_links']||$expires===false||!PaywallEngine::validateSignedUrl($id,(int)$expires,$signature)) throw new \InvalidArgumentException('El enlace temporal no es válido o venció.');
        }
        $path=self::path($file); if (!$path) throw new \OutOfBoundsException('Archivo físico no disponible.');
        $checksum=hash_file('sha256',$path); if ($file['sha256']!==null&&!hash_equals($file['sha256'],$checksum)) throw new \UnexpectedValueException('No se pudo verificar la integridad del archivo.');
        if ($file['sha256']===null) $db->execute('UPDATE goldensite_downloads SET sha256=?,file_bytes=? WHERE id=? AND sha256 IS NULL',[$checksum,filesize($path),$id]);
        if ($link) {
            $db->execute('UPDATE goldensite_download_links SET uses=uses+1 WHERE id=? AND revoked_at IS NULL AND expires_at>UNIX_TIMESTAMP() AND (max_uses=0 OR uses<max_uses)',[$link['id']]);
            if ((int)$db->getConnection()->query('SELECT ROW_COUNT()')->fetchColumn()!==1) throw new \InvalidArgumentException('El enlace ya no admite accesos.');
        }
        $db->execute('INSERT INTO goldensite_download_events (file_id,link_id,user_id,result,file_bytes) VALUES (?,?,?,\'served\',?)',[$id,$link['id']??null,$actor,filesize($path)]);
        return $file+['resolved_path'=>$path,'actual_checksum'=>$checksum];
    }
    public static function stream(array $file): never
    {
        $path=$file['resolved_path']; $name=basename(str_replace('\\','/',(string)$file['original_name'])); $name=preg_replace('/[\x00-\x1f\x7f]/','_',$name); $fallback=preg_replace('/[^A-Za-z0-9._-]/','_',$name)?:'download.bin';
        $stream=@fopen($path,'rb'); if (!$stream) throw new \OutOfBoundsException('Archivo físico no disponible.');
        // Binary responses must bypass HTML theme, locale and accessibility output filters.
        while (ob_get_level()>0) ob_end_clean(); if (session_status()===PHP_SESSION_ACTIVE) session_write_close();
        header('Content-Type: application/octet-stream'); header('X-Content-Type-Options: nosniff'); header("Content-Security-Policy: sandbox; default-src 'none'"); header('Referrer-Policy: no-referrer'); header('Cache-Control: no-store, private');
        header('Content-Disposition: attachment; filename="'.$fallback.'"; filename*=UTF-8\'\''.rawurlencode($name)); header('Content-Length: '.filesize($path));
        fpassthru($stream); fclose($stream); exit;
    }
}
