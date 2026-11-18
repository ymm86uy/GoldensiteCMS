<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,FormBuilder,RequestGuard};
final class FormBuilderController
{
    public function index(): void {
        Auth::requireAdmin(); $db=Database::getInstance(); $status=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
            $id=(int)($_POST['form_id']??0); $name=ConsoleView::field($_POST,'name',150); $slug=ConsoleView::field($_POST,'slug',100); $success=ConsoleView::field($_POST,'success_message',500); $schema=FormBuilder::schema(ConsoleView::field($_POST,'schema_json',60000));
            if ($name===''||$success===''||!preg_match('/^[a-z0-9][a-z0-9-]{2,99}$/D',$slug)||preg_match('/[<>]/',$name.$success)) throw new \InvalidArgumentException('Revisa nombre, dirección y confirmación del formulario.');
            $params=[$name,$slug,json_encode($schema,JSON_THROW_ON_ERROR),$success,($_POST['is_active']??'')==='1'?1:0];
            if ($id) { if (!$db->fetch('SELECT id FROM goldensite_forms WHERE id=?',[$id])) throw new \InvalidArgumentException('Formulario inexistente.'); $db->execute('UPDATE goldensite_forms SET name=?,slug=?,schema_json=?,success_message=?,is_active=? WHERE id=?',[...$params,$id]); } else { $db->execute('INSERT INTO goldensite_forms (name,slug,schema_json,success_message,is_active) VALUES (?,?,?,?,?)',$params); $id=(int)$db->getConnection()->lastInsertId(); }
            ConsoleView::audit('form_saved','Formulario #'.$id); header('Location: /admin/form-builder?id='.$id); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } catch (\PDOException $e) { http_response_code(422); $status='La dirección del formulario ya existe o no se pudo guardar.'; } }
        $forms=$db->fetchAll('SELECT f.*, (SELECT COUNT(*) FROM goldensite_form_entries e WHERE e.form_id=f.id) entry_count FROM goldensite_forms f ORDER BY id DESC LIMIT 200'); $selected=$db->fetch('SELECT * FROM goldensite_forms WHERE id=?',[(int)($_GET['id']??0)]);
        $page=max(1,min(10000,(int)($_GET['page']??1))); $entries=$selected?$db->fetchAll('SELECT * FROM goldensite_form_entries WHERE form_id=? ORDER BY id DESC LIMIT 20 OFFSET '.(($page-1)*20),[$selected['id']]):[];
        if ($selected&&($_GET['export']??'')==='csv') { header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="goldensite-form-'.$selected['id'].'.csv"'); $out=fopen('php://output','w'); $fields=FormBuilder::schema($selected['schema_json']); fputcsv($out,['ID','Fecha',...array_column($fields,'label')],',','"',''); foreach ($db->fetchAll('SELECT * FROM goldensite_form_entries WHERE form_id=? ORDER BY id DESC LIMIT 10000',[$selected['id']]) as $entry) { $data=json_decode($entry['data_json'],true); $cells=[$entry['id'],$entry['created_at']]; foreach ($fields as $field) $cells[]=$data[$field['id']]??''; fputcsv($out,array_map(static fn($v)=>preg_match('/^[\s]*[=+@-]/u',(string)$v)?"'".$v:$v,$cells),',','"',''); } fclose($out); return; }
        ConsoleView::render('form_builder',['forms'=>$forms,'selected'=>$selected,'entries'=>$entries,'page'=>$page,'status'=>$status],'Goldensite Form Builder');
    }
}
