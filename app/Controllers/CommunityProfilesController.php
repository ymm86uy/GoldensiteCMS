<?php
namespace App\Controllers;

use App\Core\{Auth, CatalogMedia, ConsoleView, Csrf, Database, IdentityAccess, Settings};

final class CommunityProfilesController
{
    private const DEFAULT_FIELDS=['bio'=>'Biografía','country'=>'País','instrument'=>'Instrumentos'];

    private function fields(): array
    {
        $fields=json_decode(Settings::get('community_profile_fields'),true);
        return is_array($fields)?$fields:self::DEFAULT_FIELDS;
    }

    public function settings(): void
    {
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        $message='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $fields=[];
            $raw=$_POST['fields']??'';
            if (!is_string($raw)||strlen($raw)>1000||!mb_check_encoding($raw,'UTF-8')) {
                http_response_code(422);
                $message='La configuración de campos no es válida.';
                $raw='';
            }
            foreach (preg_split('/\R/u',$raw) ?: [] as $line) {
                if (trim($line)==='') continue;
                [$key,$label]=array_pad(explode('=',trim($line),2),2,'');
                $label=trim($label);
                if (!preg_match('/^[a-z][a-z0-9_]{1,23}$/D',$key)||$label===''||mb_strlen($label)>50||isset($fields[$key])||count($fields)>=12) {
                    http_response_code(422);
                    $message='Usa hasta 12 líneas clave=Etiqueta con claves únicas.';
                    break;
                }
                $fields[$key]=$label;
            }
            if ($message==='') {
                Settings::set('community_profile_fields',json_encode($fields,JSON_THROW_ON_ERROR));
                $message='Campos guardados.';
            }
        }
        $fields=$this->fields();
        $csrf=Csrf::generate();
        $title='Goldensite Community Profiles';
        ob_start();
        require dirname(__DIR__,2).'/views/admin/community_profiles.php';
        $content=ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/admin.php';
    }

    public function edit(): void
    {
        if (!Auth::check()) { header('Location: /login',true,303); return; }
        header('Cache-Control: no-store, private');
        $db=Database::getInstance();
        $actor=(int)Auth::id();
        $fields=$this->fields();
        $saved=$db->fetch('SELECT * FROM goldensite_community_profiles WHERE user_id=?',[$actor]);
        $values=json_decode($saved['fields_json']??'{}',true)?:[];
        $label=$saved['display_name']??'';
        $visibility=$saved['visibility']??'off';
        $history=$saved['history_mode']??'off';
        $message='';
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            $label=ConsoleView::field($_POST,'display_name',80);
            $visibility=$this->mode($_POST['visibility']??'off');
            $history=$this->mode($_POST['history_mode']??'off');
            foreach ($fields as $key=>$name) {
                $values[$key]=['value'=>ConsoleView::field(is_array($_POST['field']??null)?$_POST['field']:[],$key,600),
                    'mode'=>$this->mode(is_array($_POST['privacy']??null)?($_POST['privacy'][$key]??'off'):'off')];
            }
            if ($label==='' && $visibility!=='off') { http_response_code(422); $message='Escribe un nombre visible o conserva el perfil privado.'; }
            else {
                $db->execute('INSERT INTO goldensite_community_profiles (user_id,display_name,visibility,history_mode,fields_json) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),visibility=VALUES(visibility),history_mode=VALUES(history_mode),fields_json=VALUES(fields_json)',
                    [$actor,$label,$visibility,$history,json_encode(array_intersect_key($values,$fields),JSON_THROW_ON_ERROR)]);
                $message='Perfil guardado.';
            }
        }
        $csrf=Csrf::generate();
        $profileId=$actor;
        $edit=true;
        $this->render(compact('edit','fields','values','label','visibility','history','csrf','message','profileId'));
    }

    public function show(string $id): void
    {
        header('Cache-Control: no-store, private');
        $db=Database::getInstance();
        $saved=preg_match('/^[1-9][0-9]{0,9}$/D',$id)?$db->fetch("SELECT p.* FROM goldensite_community_profiles p JOIN users u ON u.id=p.user_id AND u.status='active' WHERE p.user_id=?",[(int)$id]):null;
        if (!$saved || !CatalogMedia::allowed($saved['visibility'])) { http_response_code(404); $this->render(['missing'=>true]); return; }
        $fields=$this->fields();
        $values=json_decode($saved['fields_json'],true)?:[];
        $visible=[];
        foreach ($fields as $key=>$label) if (!empty($values[$key]['value'])&&CatalogMedia::allowed($values[$key]['mode']??'off')) $visible[$label]=$values[$key]['value'];
        $entries=[];
        if (Settings::get('joomla_catalog_enabled')==='1'&&CatalogMedia::allowed($saved['history_mode'])) {
            $policy=Auth::check()?"<> 'off'":"='public'";
            $entries=$db->fetchAll("SELECT e.source_id,e.title FROM goldensite_catalog_submissions s
                JOIN goldensite_joomla_entries e ON e.source_id=2000000000+s.id AND e.is_visible=1
                JOIN goldensite_joomla_categories c ON c.source_id=e.category_id AND c.is_visible=1
                JOIN goldensite_catalog_media m ON m.entry_id=e.source_id AND m.view_mode $policy
                WHERE s.author_id=? AND s.status='approved' ORDER BY s.id DESC LIMIT 50",[(int)$id]);
        }
        $label=$saved['display_name'];
        $this->render(compact('visible','entries','label'));
    }

    private function mode(mixed $value): string { return in_array($value,CatalogMedia::MODES,true)?$value:'off'; }

    private function render(array $data): void
    {
        header('X-Robots-Tag: noindex');
        extract($data,EXTR_SKIP);
        $siteTitle=$label??'Goldensite Community Profiles';
        ob_start();
        require dirname(__DIR__,2).'/views/frontend/community_profile.php';
        $content=ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
