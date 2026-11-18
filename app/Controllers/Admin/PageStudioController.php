<?php
namespace App\Controllers\Admin;
use App\Core\{AdminPath,Auth,CacheStore,ConsoleView,Database,EditorialHistory,EditorialWorkflow,HomePage,RequestGuard,Settings,ThemeService};
class PageStudioController
{
    protected string $table='goldensite_pages';
    protected string $route='/admin/pages';
    protected string $entity='page';
    protected function query(string $sql): string { return str_replace('goldensite_pages',$this->table,$sql); }

    public function home(): void
    {
        Auth::requireAdmin();
        $route=AdminPath::url('/admin/pages/home');
        $status=$_SESSION['home_notice']??null;
        unset($_SESSION['home_notice']);
        $home=HomePage::values();
        if ($_SERVER['REQUEST_METHOD']==='POST') {
            RequestGuard::post();
            try {
                HomePage::save($_POST,$_FILES['home_image']??[]);
                CacheStore::clear();
                ConsoleView::audit('home_page_updated');
                $_SESSION['home_notice']='Cambios de portada guardados.';
                header('Location: '.$route,true,303);
                return;
            } catch (\InvalidArgumentException $error) {
                http_response_code(422);
                $status=$error->getMessage();
                foreach ($home as $key=>$value) {
                    if ($key!=='image' && is_string($_POST[$key]??null)) $home[$key]=mb_substr($_POST[$key],0,600);
                }
            }
        }
        $catalog=Settings::get('joomla_catalog_enabled')==='1' && Settings::get('joomla_catalog_home')==='1';
        $composed=Settings::get('use_ai_frontend','0')==='1' && Settings::get('custom_ai_frontend')!=='';
        ConsoleView::render('home_page',compact('home','route','status','catalog','composed'),'Página de inicio · Goldensite');
    }
    public function index(): void {
        Auth::requireAdmin(); $db=Database::getInstance(); $status=$_SESSION['page_notice']??null; unset($_SESSION['page_notice']); $selected=null;
        $route=AdminPath::url($this->route);
        $draftKey='editorial_recovery_'.$this->entity;
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            $action=ConsoleView::field($_POST,'action',20); $id=(int)($_POST['page_id']??0);
            if (in_array($action,['request_review','approve_review','schedule_publish','cancel_schedule'],true)) {
                EditorialWorkflow::change($this->entity,$id,$action,ConsoleView::field($_POST,'revision',64),ConsoleView::field($_POST,'publish_at',16));
                ConsoleView::audit('editorial_'.$action,$this->entity.' #'.$id);
                $_SESSION['page_notice']='Flujo editorial actualizado.';
                header('Location: '.$route.'?id='.$id,true,303); return;
            }
            if (in_array($action,['recover','discard_recovery'],true)) {
                $current=$db->fetch($this->query('SELECT * FROM goldensite_pages WHERE id=?'),[$id]);
                if (!$current) throw new \InvalidArgumentException('El contenido ya no existe.');
                if ($action==='recover') {
                    $version=EditorialHistory::find($this->entity,$id,(int)($_POST['history_id']??0));
                    if (!$version) throw new \InvalidArgumentException('La versión ya no está disponible.');
                    $_SESSION[$draftKey]=['document'=>$id,'version'=>(int)$version['id'],'base'=>EditorialHistory::fingerprint($current)];
                } else unset($_SESSION[$draftKey]);
                header('Location: '.$route.'?id='.$id,true,303); return;
            }
            if ($action==='delete') {
                $db->getConnection()->beginTransaction();
                $old=$db->fetch($this->query('SELECT slug,status FROM goldensite_pages WHERE id=? FOR UPDATE'),[$id]);
                if (!$old) throw new \InvalidArgumentException('El contenido ya no existe.');
                if ($old['status']==='published' && !\App\Core\IdentityAccess::allows(($this->entity==='page'?'pages':'posts').'.publish')) throw new \InvalidArgumentException('Tu cuenta no puede retirar contenido publicado.');
                \App\Core\Navigation::contentDeleted($id,$old['slug'],$this->entity==='post');
                EditorialHistory::remove($this->entity,$id);
                EditorialWorkflow::remove($this->entity,$id);
                $db->execute($this->query('DELETE FROM goldensite_pages WHERE id=?'),[$id]);
                $db->getConnection()->commit();
                if (($_SESSION[$draftKey]['document']??null)===$id) unset($_SESSION[$draftKey]);
            }
            elseif (in_array($action,['create','save','publish_recovery'],true)) { $db->getConnection()->beginTransaction();
                if ($action==='publish_recovery' && $id<1) throw new \InvalidArgumentException('Selecciona el documento original antes de publicar la recuperación.');
                $title=ConsoleView::field($_POST,'title',255); $slug=ConsoleView::field($_POST,'slug',200); $content=ConsoleView::field($_POST,'content',1048576); $metaTitle=ConsoleView::field($_POST,'meta_title',255); $metaDescription=ConsoleView::field($_POST,'meta_description',2000); $state=ConsoleView::field($_POST,'status',20)?:'published';
                if (!\App\Core\IdentityAccess::superuser()) $content=\App\Core\CmsMigration::clean($content);
                if ($slug==='') $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$title)?:''),'-'));
                if ($title===''||preg_match('/[<>]/',$title.$metaTitle.$metaDescription)||!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug)||!in_array($state,['draft','published'],true)) throw new \InvalidArgumentException('Revisa título, dirección y estado de publicación.');
                if ('/'.$slug === \App\Core\AdminPath::base()) throw new \InvalidArgumentException('La dirección está reservada para el panel administrativo.');
                $canPublish=\App\Core\IdentityAccess::allows(($this->entity==='page'?'pages':'posts').'.publish');
                if ($state==='published' && !$canPublish) throw new \InvalidArgumentException('Tu cuenta puede guardar borradores y enviarlos a revisión.');
                if ($id) {
                    $selected=$db->fetch($this->query('SELECT * FROM goldensite_pages WHERE id=? FOR UPDATE'),[$id]);
                    if (!$selected) throw new \InvalidArgumentException('El contenido ya no existe.');
                    if ($selected['status']==='published' && !$canPublish) throw new \InvalidArgumentException('Tu cuenta no puede modificar una publicación vigente.');
                    $revision=ConsoleView::field($_POST,'revision',64);
                    $fingerprint=EditorialHistory::fingerprint($selected);
                    if ($revision!==''&&!hash_equals($fingerprint,$revision)) throw new \InvalidArgumentException('El contenido cambió. Recarga antes de guardar.');
                    if ($action==='publish_recovery') {
                        $draft=$_SESSION[$draftKey]??[];
                        if (($draft['document']??null)!==$id || (int)($_POST['recovery_version']??0)!==($draft['version']??null)
                            || ($draft['base']??null)!==$fingerprint || $state!=='published') throw new \InvalidArgumentException('Vuelve a recuperar la versión sobre el contenido vigente antes de publicarla.');
                    }
                    EditorialHistory::record($this->entity,$selected,null);
                    $db->execute($this->query('UPDATE goldensite_pages SET title=?,slug=?,content=?,meta_title=?,meta_description=?,status=?,is_published=? WHERE id=?'),[$title,$slug,$content,$metaTitle,$metaDescription,$state,$state==='published'?1:0,$id]);
                }
                else { if ($db->fetch($this->query('SELECT id FROM goldensite_pages WHERE slug=?'),[$slug])) throw new \InvalidArgumentException('Ya existe una página con esa dirección. Ábrela para editarla.'); $db->execute($this->query('INSERT INTO goldensite_pages (title,slug,content,meta_title,meta_description,status,is_published) VALUES (?,?,?,?,?,?,?)'),[$title,$slug,$content,$metaTitle,$metaDescription,$state,$state==='published'?1:0]); $id=(int)$db->getConnection()->lastInsertId(); }
                EditorialHistory::record($this->entity,['id'=>$id,'title'=>$title,'slug'=>$slug,'content'=>$content,'meta_title'=>$metaTitle,'meta_description'=>$metaDescription,'status'=>$state],Auth::user());
                EditorialWorkflow::remove($this->entity,$id);
                if ($state==='published' && (!$selected||$selected['status']!=='published'||$selected['title']!==$title||$selected['content']!==$content)) \App\Core\WebhookEngine::enqueue($this->entity==='post'?'post_published':'page_published',($this->entity==='post'?'Artículo publicado: ':'Página publicada: ').$title);
                $db->getConnection()->commit();
                if ($action==='publish_recovery') unset($_SESSION[$draftKey]);
                \App\Core\AutomationEngine::trigger($this->entity==='post'?'post_published':'page_published',['title'=>$title,'id'=>$id,'status'=>$state]);
            } else throw new \InvalidArgumentException('Acción desconocida.');
            CacheStore::clear(); ConsoleView::audit('page_'.$action,'Contenido #'.$id); $_SESSION['page_notice']=$action==='publish_recovery'?'Versión recuperada y publicada.':'Cambios guardados.'; header('Location: '.$route.($action==='delete'?'':'?id='.$id)); return;
        } catch (\InvalidArgumentException $e) { if ($db->getConnection()->inTransaction()) $db->getConnection()->rollBack(); http_response_code(422); $status=$e->getMessage(); } catch (\PDOException $e) { if ($db->getConnection()->inTransaction()) $db->getConnection()->rollBack(); http_response_code(422); $status='La dirección ya existe o no se pudo guardar el contenido.'; } catch (\Throwable $e) { if ($db->getConnection()->inTransaction()) $db->getConnection()->rollBack(); throw $e; } }
        $selected=$db->fetch($this->query('SELECT * FROM goldensite_pages WHERE id=?'),[(int)($_GET['id']??0)])?:$selected; $q=is_string($_GET['q']??null)?substr(trim($_GET['q']),0,100):''; $page=max(1,min(10000,(int)($_GET['page']??1))); $where=$q!==''?' WHERE title LIKE ? OR slug LIKE ?':''; $params=$q!==''?['%'.$q.'%','%'.$q.'%']:[];
        $pages=$db->fetchAll($this->query('SELECT id,title,slug,status,updated_at FROM goldensite_pages').$where.' ORDER BY updated_at DESC,id DESC LIMIT 30 OFFSET '.(($page-1)*30),$params);
        $history=[]; $compare=null; $recovery=null; $editor=$selected;
        if ($selected) {
            $documentId=(int)$selected['id'];
            $history=EditorialHistory::list($this->entity,$documentId);
            $compare=EditorialHistory::find($this->entity,$documentId,(int)($_GET['compare']??0));
            $draft=$_SESSION[$draftKey]??[];
            if (($draft['document']??null)===$documentId) {
                $recovery=EditorialHistory::find($this->entity,$documentId,$draft['version']);
                if ($recovery) $editor=EditorialHistory::snapshot($recovery)+$selected;
            }
        }
        $workflow=$selected?EditorialWorkflow::get($this->entity,(int)$selected['id']):null;
        ConsoleView::render('page_studio',compact('selected','editor','history','compare','recovery','pages','q','page','status','route','workflow')+['entity'=>$this->entity,'palette'=>ThemeService::profile(),'theme'=>ThemeService::active()],$this->entity==='post'?'Artículos · Goldensite':'Páginas · Goldensite');
    }
}
