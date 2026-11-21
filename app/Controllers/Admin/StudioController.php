<?php
namespace App\Controllers\Admin;

use App\Core\{AdminPath,Auth,CacheStore,CompositionStudio,ConsoleView,Database,IdentityAccess,RequestGuard,Settings};

final class StudioController
{
    public function index(): void
    {
        Auth::requireAdmin();
        header('Cache-Control: private, no-store');
        $base = AdminPath::url('/admin/ai-builder');
        $status = $_SESSION['studio_notice']??null;
        unset($_SESSION['studio_notice']);
        $db = Database::getInstance();
        $canPublish = IdentityAccess::superuser();
        $failedHtml = null;
        $failedSpec = null;
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                $action = ConsoleView::field($_POST,'action',30);
                if (in_array($action,['preview_guided','upload_image'],true)) {
                    header('Content-Type: application/json; charset=utf-8');
                    if (!\App\Core\RateLimiter::consume('studio-'.$action.':'.Auth::id(),$action==='upload_image'?30:60,$action==='upload_image'?3600:60)) { http_response_code(429); echo json_encode(['error'=>'Espera antes de repetir la operación.']); return; }
                    if ($action==='preview_guided') $response=['html'=>CompositionStudio::generate(CompositionStudio::guided($_POST))];
                    else {
                        $file=$_FILES['studio_image']??[];
                        if (!is_string($file['tmp_name']??null) || !is_string($file['name']??null) || !preg_match('/\.(?:png|jpe?g|gif|webp)$/iD',$file['name'])) throw new \InvalidArgumentException('Selecciona una imagen JPEG, PNG, WebP o GIF de hasta 5 MB.');
                        $url=(new \App\Core\Uploader())->upload($file);
                        if ($url===null) throw new \InvalidArgumentException('No se pudo validar la imagen. Revisa su formato y tamaño.');
                        ConsoleView::audit('studio_image_uploaded');
                        $response=['url'=>$url];
                    }
                    echo json_encode($response,JSON_THROW_ON_ERROR); return;
                }
                $failedHtml = is_string($_POST['html_code']??null)?substr($_POST['html_code'],0,1048576):null;
                $_SESSION['studio_notice'] = $this->save($db,$action,$canPublish);
                header('Location: '.$base.(($_POST['editor_mode']??'')==='advanced'?'?mode=advanced':''),true,302);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                if (in_array($action??'', ['preview_guided','upload_image'],true)) { echo json_encode(['error'=>$error->getMessage()],JSON_THROW_ON_ERROR); return; }
                if (in_array($action??'', ['save_guided','publish_guided'],true)) $failedSpec=$_POST;
                $status = $error->getMessage();
            }
        }
        $published = Settings::get('custom_ai_frontend');
        $draft = Settings::get('builder_draft_html',$published);
        $revision = hash('sha256',$draft."\0".$published."\0".Settings::get('use_ai_frontend','0'));
        $html = $failedHtml??$draft;
        $spec = json_decode(Settings::get('builder_draft_spec','{}'),true)?:[
            'name'=>Settings::get('site_title','Goldensite'),'headline'=>'Ideas que encuentran su lugar.',
            'description'=>'Un espacio para presentar lo que haces, con claridad y carácter.',
            'cta'=>'Contactar','url'=>'/contacto','template'=>'studio',
        ];
        $active = Settings::get('use_ai_frontend')==='1';
        $changed = $draft!==$published;
        $guidedMode=\App\Core\ProductEdition::current()==='commercial' && ($_GET['mode']??'')!=='advanced';
        $bound=CompositionStudio::snapshot(Settings::get('builder_draft_spec','{}'),$draft);
        $guidedSpec=$bound?:CompositionStudio::defaults();
        if ($failedSpec!==null) foreach (CompositionStudio::defaults() as $key=>$default) {
            if (is_string($failedSpec[$key]??null)) $guidedSpec[$key]=mb_substr($failedSpec[$key],0,1800);
        }
        if (is_array($failedSpec['sections']??null)) $guidedSpec['sections']=array_values(array_unique(array_filter($failedSpec['sections'],static fn($v)=>is_string($v)&&in_array($v,['services','about','contact'],true))));
        $legacyDraft=$draft!=='' && !$bound;
        $media=$guidedMode?array_slice(array_values(array_filter(\App\Core\AssetLibrary::items(),static fn($asset)=>$asset['image']&&$asset['bytes']<=5242880)),0,80):[];
        $forms=$guidedMode&&\App\Core\FrontendEngine::isModuleActive('form-builder')?$db->fetchAll('SELECT id,name FROM goldensite_forms WHERE is_active=1 ORDER BY id DESC LIMIT 100'):[];
        $previewHtml=$draft!==''?$draft:CompositionStudio::generate($guidedSpec);
        $editorMode=$guidedMode?'guided':'advanced';
        $versions = $db->fetchAll('SELECT v.id,v.created_at,u.username FROM goldensite_builder_versions v LEFT JOIN users u ON u.id=v.created_by ORDER BY v.id DESC LIMIT 20');
        ConsoleView::render($guidedMode?'studio_guided':'studio',compact('html','spec','status','active','versions','canPublish','revision','changed','base','guidedSpec','legacyDraft','media','forms','previewHtml','editorMode'),'Estudio de composición · Goldensite');
    }

    private function save(Database $db,string $action,bool $canPublish): string
    {
        $publicChange = in_array($action,['publish_layout','publish_guided','disable_ai_frontend'],true);
        if ($publicChange && !$canPublish) {
            http_response_code(403);
            exit('La publicación de la portada requiere un administrador completo.');
        }
        if ($publicChange || $action==='restore_version') ConsoleView::password();

        $pdo = $db->getConnection();
        $initialDraft = Settings::get('custom_ai_frontend');
        $pdo->beginTransaction();
        try {
            // All studio writes share a row lock, even before a first draft exists.
            $db->execute("INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('builder_draft_html',?)",[$initialDraft]);
            $db->fetch("SELECT setting_value FROM settings WHERE setting_key='builder_draft_html' FOR UPDATE");
            Settings::reset();
            $published = Settings::get('custom_ai_frontend');
            $draft = Settings::get('builder_draft_html');
            $active = Settings::get('use_ai_frontend','0');
            $revision = $_POST['revision']??null;
            if ($revision!==null && (!is_string($revision) || !hash_equals(hash('sha256',$draft."\0".$published."\0".$active),$revision))) {
                throw new \InvalidArgumentException('El borrador o la portada cambiaron en otra sesión. Revisa la versión actual antes de guardar.');
            }
            if ($action==='disable_ai_frontend') {
                Settings::set('use_ai_frontend','0');
                $notice = 'La portada del tema volvió a estar activa.';
            } elseif ($action==='restore_version') {
                $id = filter_var($_POST['version_id']??null,FILTER_VALIDATE_INT);
                $version = $id?$db->fetch('SELECT html_code,composition_json FROM goldensite_builder_versions WHERE id=?',[$id]):null;
                if (!$version) throw new \InvalidArgumentException('Versión inexistente.');
                Settings::writeMany(['builder_draft_html'=>$version['html_code'],'builder_draft_spec'=>$version['composition_json']?:'{}']);
                $notice = 'Versión recuperada como borrador.';
            } elseif ($action==='generate') {
                $spec = CompositionStudio::validate($_POST);
                Settings::writeMany(['builder_draft_html'=>CompositionStudio::generate($spec),'builder_draft_spec'=>json_encode($spec,JSON_THROW_ON_ERROR)]);
                $notice = 'Composición generada como borrador.';
            } elseif (in_array($action,['save_draft','publish_layout','save_guided','publish_guided'],true)) {
                $guided=in_array($action,['save_guided','publish_guided'],true);
                if ($guided) {
                    if (!is_string($_POST['revision']??null) || !preg_match('/^[a-f0-9]{64}$/D',$_POST['revision'])) throw new \InvalidArgumentException('Recarga el estudio antes de guardar el diseño.');
                    if ($draft!=='' && !CompositionStudio::snapshot(Settings::get('builder_draft_spec','{}'),$draft) && ($_POST['replace_draft']??'')!=='1') throw new \InvalidArgumentException('Confirma que deseas sustituir el borrador avanzado. La portada publicada se conserva hasta que publiques.');
                    $newSpec=CompositionStudio::guided($_POST);
                    $html=CompositionStudio::generate($newSpec);
                    $newSpec['html_sha256']=hash('sha256',$html);
                } else {
                    $html = self::document($_POST['html_code']??null);
                    $newSpec=CompositionStudio::snapshot(Settings::get('builder_draft_spec','{}'),$html);
                }
                $specJson=json_encode($newSpec,JSON_THROW_ON_ERROR);
                if (in_array($action,['publish_layout','publish_guided'],true)) {
                    if ($published!=='' && $published!==$html) {
                        $db->execute('INSERT INTO goldensite_builder_versions (html_code,created_by,composition_json) VALUES (?,?,?)',[$published,Auth::id(),Settings::get('builder_published_spec','{}')]);
                    }
                    $policy = !$guided && ($_POST['trusted_scripts']??'')==='1'?'trusted':'restricted';
                    Settings::writeMany(['custom_ai_frontend'=>$html,'use_ai_frontend'=>'1','builder_draft_html'=>$html,'builder_content_policy'=>$policy,'builder_draft_spec'=>$specJson,'builder_published_spec'=>$specJson]);
                    $notice = 'Portada publicada.';
                } else {
                    Settings::writeMany(['builder_draft_html'=>$html,'builder_draft_spec'=>$specJson]);
                    $notice = 'Borrador guardado.';
                }
            } else {
                throw new \InvalidArgumentException('Acción desconocida.');
            }
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            Settings::reset();
            throw $error;
        }
        if ($publicChange) CacheStore::clear();
        ConsoleView::audit('studio_'.$action);
        return $notice;
    }

    private static function document(mixed $value): string
    {
        if (!is_string($value) || strlen($value)>1048576 || !mb_check_encoding($value,'UTF-8')
            || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value)
            || !preg_match('/<html\b/i',$value) || stripos($value,'</head>')===false || stripos($value,'</body>')===false) {
            throw new \InvalidArgumentException('Introduce un documento HTML completo en UTF-8, con cabecera y cuerpo, de hasta 1 MB.');
        }
        return $value;
    }
}
