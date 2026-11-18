<?php
namespace App\Controllers;
use App\Core\{ConsoleView,Csrf,Database,FormBuilder,FrontendEngine,Locale,RateLimiter,ThemeService};
final class FormController
{
    public function show(string $slug): void {
        if (!FrontendEngine::isModuleActive('form-builder')||!($form=Database::getInstance()->fetch('SELECT * FROM goldensite_forms WHERE slug=? AND is_active=1',[$slug]))) { http_response_code(404); echo 'Formulario no disponible.'; return; }
        $notice=null;
        if ($_SERVER['REQUEST_METHOD']==='POST') { try {
            \App\Core\RequestGuard::post(); if (!RateLimiter::consume('form:'.($_SERVER['REMOTE_ADDR']??''),10,600)) { http_response_code(429); exit('Espera antes de enviar otro formulario.'); }
            $nonce=ConsoleView::field($_POST,'submission_token',32); $ticket=$_SESSION['form_nonces'][$nonce]??null;
            if (!$ticket||(int)$ticket['form']!==(int)$form['id']||$ticket['until']<time()||($_POST['website_confirm']??'')!=='') throw new \InvalidArgumentException('El formulario venció o ya fue enviado. Recarga la página.');
            $input=$_POST['fields']??[]; if (!is_array($input)) throw new \InvalidArgumentException('Datos inválidos.'); $values=FormBuilder::validate(FormBuilder::schema($form['schema_json']),$input);
            Database::getInstance()->execute('INSERT INTO goldensite_form_entries (form_id,data_json) VALUES (?,?)',[$form['id'],json_encode($values,JSON_THROW_ON_ERROR)]); \App\Core\AutomationEngine::trigger('form_submitted',['form_id'=>(int)$form['id'],'entry_id'=>(int)Database::getInstance()->getConnection()->lastInsertId(),'form_name'=>$form['name']]); unset($_SESSION['form_nonces'][$nonce]); $_SESSION['form_success_'.$form['id']]=true; header('Location: /forms/'.$form['slug'],true,303); return;
        } catch (\InvalidArgumentException $e) { http_response_code(422); $notice=$e->getMessage(); } }
        $e=ConsoleView::escape(...); $siteTitle=$form['name']; $activeTheme=ThemeService::active(); $content=''; if ($notice) $content.='<p role="alert" style="padding:20px">'.$e($notice).'</p>';
        if (!empty($_SESSION['form_success_'.$form['id']])) { unset($_SESSION['form_success_'.$form['id']]); $content.='<p role="status" style="padding:20px">'.$e($form['success_message']).'</p>'; }
        $content.=FormBuilder::render($form); require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
