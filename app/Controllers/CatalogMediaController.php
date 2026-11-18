<?php
namespace App\Controllers;

use App\Core\{Auth, CatalogMedia, CmsMigration, ConsoleView, Csrf, Database, DownloadLibrary, FrontendEngine, IdentityAccess, JoomlaCatalogImport, JoomlaOnlineImport, RateLimiter, Settings};

final class CatalogMediaController
{
    public function migration(): void { $this->admin('migration'); }
    public function library(): void { $this->admin('library'); }

    public function admin(string $section = 'all'): void
    {
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        $db=Database::getInstance();
        $enabled=Settings::get('joomla_catalog_enabled')==='1';
        $plan=null;
        $sourcePlan=null;
        $onlinePlan=null;
        $onlineToken='';
        $message='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            try {
                $action=$_POST['action']??'scan';
                if (($section==='library' && in_array($action,['source_scan','source_import','online_scan','online_import'],true)) ||
                    ($section==='migration' && in_array($action,['policy','approve','reject'],true))) {
                    throw new \InvalidArgumentException('Abre la sección correspondiente para continuar.');
                }
                if ($action==='online_scan') {
                    unset($_SESSION['joomla_online_plan']);
                    $https=strtolower((string)($_SERVER['HTTPS']??''));
                    $local=in_array($_SERVER['REMOTE_ADDR']??'',['127.0.0.1','::1'],true)
                        && preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]{1,5})?$/iD',$_SERVER['HTTP_HOST']??'');
                    if (!$local && !in_array($https,['on','1'],true)) {
                        throw new \InvalidArgumentException('Abre el panel de GoldenSiteCMS mediante HTTPS para introducir las credenciales de Joomla.');
                    }
                    if (!RateLimiter::consume('joomla-online:'.Auth::id(),6,900)) throw new \InvalidArgumentException('Espera unos minutos antes de volver a analizar un sitio.');
                    set_time_limit(60);
                    $onlinePlan=JoomlaOnlineImport::analyze($_POST);
                    $onlineToken=CmsMigration::save($onlinePlan);
                    $_SESSION['joomla_online_plan']=$onlineToken;
                } elseif ($action==='online_import') {
                    $token=ConsoleView::field($_POST,'plan_id',64);
                    $sessionToken=$_SESSION['joomla_online_plan']??'';
                    if ($token==='' || !hash_equals($sessionToken,$token) || ($_POST['confirm']??'')!=='IMPORTAR') {
                        throw new \InvalidArgumentException('Analiza el sitio y confirma IMPORTAR en la vista previa.');
                    }
                    $onlinePlan=CmsMigration::load($token);
                    if ($onlinePlan['cms']!=='joomla-online') throw new \InvalidArgumentException('Análisis de Joomla inválido.');
                    $onlineToken=$token;
                    $this->verifyPassword();
                    $summary=CmsMigration::apply($token);
                    unset($_SESSION['joomla_online_plan']);
                    $onlinePlan=null;
                    $onlineToken='';
                    ConsoleView::audit('joomla_online_import','Artículos importados: '.$summary['posts']);
                    $message='Importación en línea completada. Borradores: '.$summary['posts'].'. Respaldo: '.$summary['backup'];
                } elseif ($action==='source_scan') {
                    unset($_SESSION['joomla_source_plan']);
                    set_time_limit(180);
                    $paths=JoomlaCatalogImport::stage();
                    $prefix=ConsoleView::field($_POST,'prefix',32);
                    $sourcePlan=JoomlaCatalogImport::inspect($paths,$prefix);
                    $_SESSION['joomla_source_plan']=['prefix'=>$prefix,'hash'=>JoomlaCatalogImport::fingerprint($paths),'expires'=>time()+900];
                } elseif ($action==='source_import') {
                    $previous=$_SESSION['joomla_source_plan']??[];
                    unset($_SESSION['joomla_source_plan']);
                    if (($previous['expires']??0)<time() || ($_POST['confirm']??'')!=='IMPORTAR') throw new \InvalidArgumentException('Analiza los SQL y confirma IMPORTAR.');
                    $this->verifyPassword();
                    set_time_limit(180);
                    $paths=JoomlaCatalogImport::stage();
                    if (!hash_equals($previous['hash'],JoomlaCatalogImport::fingerprint($paths))) throw new \InvalidArgumentException('Los SQL cambiaron. Analiza de nuevo.');
                    $summary=JoomlaCatalogImport::import($paths,$previous['prefix']);
                    $enabled=true;
                    $message='Catálogo importado con respaldo previo: '.$summary['entries'].' fichas y '.$summary['categories'].' categorías. Respaldo: '.$summary['backup'];
                } elseif (!$enabled) {
                    throw new \InvalidArgumentException('Importa el catálogo antes de gestionar archivos.');
                } elseif ($action==='scan') {
                    unset($_SESSION['joomla_media_plan']);
                    $plan=CatalogMedia::scan(max(0,(int)($_POST['after']??0)));
                    $_SESSION['joomla_media_plan']=['after'=>$plan['after'],'hash'=>hash('sha256',serialize($plan['matches'])),'expires'=>time()+900];
                } elseif ($action==='link') {
                    $previous=$_SESSION['joomla_media_plan']??[];
                    unset($_SESSION['joomla_media_plan']);
                    if (($previous['expires']??0)<time() || ($_POST['confirm']??'')!=='VINCULAR') throw new \InvalidArgumentException('Confirma VINCULAR después del análisis.');
                    $this->verifyPassword();
                    set_time_limit(180);
                    $plan=CatalogMedia::scan($previous['after']);
                    if (!hash_equals($previous['hash'],hash('sha256',serialize($plan['matches'])))) throw new \InvalidArgumentException('El lote cambió. Analiza de nuevo.');
                    $count=CatalogMedia::link($plan);
                    $plan=null;
                    $message="Archivos vinculados: $count. La reproducción y descarga siguen desactivadas hasta que configures sus permisos.";
                } elseif ($action==='policy') {
                    $entry=(int)($_POST['entry']??0);
                    $modes=array_map(static fn($key)=>$_POST[$key]??'off',['view_mode','preview_mode','download_mode']);
                    if (!array_reduce($modes,static fn($ok,$mode)=>$ok&&in_array($mode,CatalogMedia::MODES,true),true) ||
                        !$db->fetch('SELECT source_id FROM goldensite_joomla_entries WHERE source_id=? AND is_visible=1',[$entry])) throw new \InvalidArgumentException('Ficha o permisos inválidos.');
                    $this->verifyPassword();
                    $db->execute('INSERT INTO goldensite_catalog_media (entry_id,view_mode,preview_mode,download_mode) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE view_mode=VALUES(view_mode),preview_mode=VALUES(preview_mode),download_mode=VALUES(download_mode)',[$entry,...$modes]);
                    $message='Permisos guardados.';
                } elseif (in_array($action,['approve','reject'],true)) {
                    CatalogMedia::review((int)($_POST['submission']??0),$action==='approve');
                    $message='Revisión guardada.';
                } else {
                    throw new \InvalidArgumentException('Operación desconocida.');
                }
            } catch (\InvalidArgumentException $e) {
                http_response_code(422);
                $message=$e->getMessage();
                $plan=null;
            } catch (\Throwable $e) {
                error_log('Catalog operation: '.$e->getMessage());
                http_response_code(500);
                $message='No se pudo completar la operación.';
                $plan=null;
            }
        }
        $queue=$enabled&&$section!=='migration'?$db->fetchAll("SELECT s.id,s.title,s.description,c.title AS category FROM goldensite_catalog_submissions s JOIN goldensite_joomla_categories c ON c.source_id=s.category_id WHERE s.status='pending' ORDER BY s.id LIMIT 50"):[];
        $csrf=Csrf::generate();
        $title=$section==='library'?'Catálogo y permisos · Goldensite':'Biblioteca y migración Joomla · Goldensite';
        ob_start();
        require dirname(__DIR__,2).'/views/admin/joomla_catalog_media.php';
        $content=ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/admin.php';
    }

    public function submit(): void
    {
        if (!$this->enabled()) return;
        if (!Auth::check()) { header('Location: /login',true,303); return; }
        try {
            $category=(int)($_POST['category']??0);
            $title=ConsoleView::field($_POST,'title',200);
            $description=ConsoleView::field($_POST,'description',1000);
            $file=$_FILES['midi']??[];
            if (!$title || !Database::getInstance()->fetch('SELECT source_id FROM goldensite_joomla_categories WHERE source_id=? AND is_visible=1',[$category]) ||
                !is_uploaded_file($file['tmp_name']??'') || !CatalogMedia::mime($file['tmp_name'],$file['name']??'',false)) throw new \InvalidArgumentException('Selecciona una categoría, un título y un MIDI o KAR válido.');
            $actor=(int)Auth::id();
            if (!RateLimiter::consume('catalog-submit:'.$actor,5,3600)) { http_response_code(429); exit('Alcanzaste el límite de aportes por hora.'); }
            $id=DownloadLibrary::upload(['title'=>$title,'description'=>$description,'access_mode'=>'authenticated','folder'=>'Aportes MIDI'],$file);
            try {
                Database::getInstance()->execute('INSERT INTO goldensite_catalog_submissions (category_id,author_id,file_id,title,description) VALUES (?,?,?,?,?)',[$category,$actor,$id,$title,$description]);
            } catch (\Throwable $e) {
                DownloadLibrary::purge($id);
                throw $e;
            }
            $_SESSION['catalog_flash']='Aporte recibido. Se publicará si supera la revisión.';
            header('Location: /index.php/midis?catid='.$category,true,303);
        } catch (\InvalidArgumentException $e) { http_response_code(422); echo htmlspecialchars($e->getMessage(),ENT_QUOTES,'UTF-8'); }
    }

    public function media(string $id,string $kind): void
    {
        if (!$this->enabled()) return;
        if (!preg_match('/^[1-9][0-9]{0,9}$/D',$id) || !in_array($kind,['preview','download'],true)) { http_response_code(404); return; }
        $preview=$kind==='preview';
        $column=$preview?'preview_id':'original_id';
        $mode=$kind.'_mode';
        $file=Database::getInstance()->fetch("SELECT d.*,m.view_mode,m.$mode AS policy FROM goldensite_catalog_media m
            JOIN goldensite_joomla_entries e ON e.source_id=m.entry_id AND e.is_visible=1
            JOIN goldensite_joomla_categories c ON c.source_id=e.category_id AND c.is_visible=1
            JOIN goldensite_downloads d ON d.id=m.$column AND d.status='active' WHERE m.entry_id=?",[(int)$id]);
        if (!$file || !CatalogMedia::allowed($file['view_mode']) || $file['policy']==='off') { http_response_code(404); return; }
        $actor=Auth::check()?(int)Auth::id():null;
        if (!CatalogMedia::allowed($file['policy']) || ($file['access_mode']==='authenticated'&&!$actor) ||
            ($file['access_mode']==='specific_user'&&$actor!==(int)$file['assigned_user'])) { http_response_code(403); return; }
        if (!RateLimiter::consume('catalog-media:'.($_SERVER['REMOTE_ADDR']??''),90,60)) { http_response_code(429); return; }
        $path=DownloadLibrary::path($file);
        if (!$path || !$file['sha256'] || !hash_equals($file['sha256'],hash_file('sha256',$path))) { http_response_code(404); return; }
        if ($preview) {
            $mime=CatalogMedia::mime($path,$file['original_name'],true);
            if (!$mime) { http_response_code(404); return; }
            CatalogMedia::audio($path,$mime);
        }
        Database::getInstance()->execute("INSERT INTO goldensite_download_events (file_id,user_id,result,file_bytes) VALUES (?,?,'served',?)",[$file['id'],$actor,filesize($path)]);
        DownloadLibrary::stream($file+['resolved_path'=>$path]);
    }

    private function enabled(): bool
    {
        if (Settings::get('joomla_catalog_enabled')==='1' && FrontendEngine::isModuleActive('server-downloads')) return true;
        http_response_code(404);
        return false;
    }

    private function verifyPassword(): void
    {
        $password=$_POST['password']??'';
        $row=Database::getInstance()->fetch('SELECT password FROM users WHERE id=?',[Auth::id()]);
        if (!is_string($password)||strlen($password)>72||!$row||!password_verify($password,$row['password'])) throw new \InvalidArgumentException('Confirma la contraseña de administrador.');
    }
}
