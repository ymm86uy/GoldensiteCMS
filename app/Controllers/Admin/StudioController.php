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
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                $action = ConsoleView::field($_POST,'action',30);
                $failedHtml = is_string($_POST['html_code']??null)?substr($_POST['html_code'],0,1048576):null;
                $_SESSION['studio_notice'] = $this->save($db,$action,$canPublish);
                header('Location: '.$base,true,302);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
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
        $versions = $db->fetchAll('SELECT v.id,v.created_at,u.username FROM goldensite_builder_versions v LEFT JOIN users u ON u.id=v.created_by ORDER BY v.id DESC LIMIT 20');
        ConsoleView::render('studio',compact('html','spec','status','active','versions','canPublish','revision','changed','base'),'Estudio de composición · Goldensite');
    }

    private function save(Database $db,string $action,bool $canPublish): string
    {
        $publicChange = in_array($action,['publish_layout','disable_ai_frontend'],true);
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
                $version = $id?$db->fetch('SELECT html_code FROM goldensite_builder_versions WHERE id=?',[$id]):null;
                if (!$version) throw new \InvalidArgumentException('Versión inexistente.');
                Settings::set('builder_draft_html',$version['html_code']);
                $notice = 'Versión recuperada como borrador.';
            } elseif ($action==='generate') {
                $spec = CompositionStudio::validate($_POST);
                Settings::writeMany(['builder_draft_html'=>CompositionStudio::generate($spec),'builder_draft_spec'=>json_encode($spec,JSON_THROW_ON_ERROR)]);
                $notice = 'Composición generada como borrador.';
            } elseif (in_array($action,['save_draft','publish_layout'],true)) {
                $html = self::document($_POST['html_code']??null);
                if ($action==='publish_layout') {
                    if ($published!=='' && $published!==$html) {
                        $db->execute('INSERT INTO goldensite_builder_versions (html_code,created_by) VALUES (?,?)',[$published,Auth::id()]);
                    }
                    $policy = ($_POST['trusted_scripts']??'')==='1'?'trusted':'restricted';
                    Settings::writeMany(['custom_ai_frontend'=>$html,'use_ai_frontend'=>'1','builder_draft_html'=>$html,'builder_content_policy'=>$policy]);
                    $notice = 'Portada publicada.';
                } else {
                    Settings::set('builder_draft_html',$html);
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
