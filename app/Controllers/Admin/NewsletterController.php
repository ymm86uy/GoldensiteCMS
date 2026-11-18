<?php
namespace App\Controllers\Admin;
use App\Core\{Auth,ConsoleView,Database,ModuleJobs,NewsletterService,RequestGuard,Settings};
final class NewsletterController
{
 public function index(): void {
  Auth::requireAdmin(); $db=Database::getInstance(); $notice=$_SESSION['newsletter_notice']??null; unset($_SESSION['newsletter_notice']);
  if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); try {
   $action=ConsoleView::field($_POST,'action',24); $id=filter_var($_POST['id']??0,FILTER_VALIDATE_INT)?:0;
   if ($action==='unsubscribe') { $db->execute("UPDATE goldensite_subscribers SET status='inactive',consented_at=NULL WHERE id=?",[$id]); $notice='Suscripción desactivada. Las tareas pendientes comprobarán su baja antes de enviar.'; }
   elseif ($action==='save_campaign') { NewsletterService::save($id,$_POST); $notice='Borrador guardado. No se ha programado ningún envío.'; }
   elseif ($action==='queue_campaign') {
    if (!\App\Core\RateLimiter::consume('newsletter-schedule:'.Auth::id(),5,300)) throw new \InvalidArgumentException('Espera antes de volver a programar.'); ConsoleView::password(); $date=ConsoleView::field($_POST,'send_at',16); $when=0;
    if ($date!=='') { $zone=Settings::get('site_timezone','UTC'); if (!in_array($zone,timezone_identifiers_list(),true)) $zone='UTC'; $parsed=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date,new \DateTimeZone($zone)); if (!$parsed||$parsed->format('Y-m-d\TH:i')!==$date) throw new \InvalidArgumentException('Fecha de programación inválida.'); $when=$parsed->getTimestamp(); }
    $count=NewsletterService::queue($id,$when); $notice="Campaña programada para $count suscriptores confirmados. La procesará el trabajador de módulos.";
   } elseif ($action==='cancel_campaign') { NewsletterService::cancel($id); $notice='Campaña cancelada. Los envíos ya aceptados o en curso no se pueden retirar.'; }
   else throw new \InvalidArgumentException('Acción desconocida.');
   $_SESSION['newsletter_notice']=$notice; header('Location: /admin/newsletter'); return;
  } catch (\InvalidArgumentException $error) { http_response_code(422); $notice=$error->getMessage(); } }
  $id=filter_var($_GET['campaign']??0,FILTER_VALIDATE_INT)?:0; $selected=$id?$db->fetch('SELECT * FROM goldensite_newsletter_campaigns WHERE id=?',[$id]):null;
  $campaigns=$db->fetchAll('SELECT * FROM goldensite_newsletter_campaigns ORDER BY id DESC LIMIT 25'); foreach ($campaigns as &$campaign) $campaign['counts']=ModuleJobs::counts('campaign:'.$campaign['id']); unset($campaign);
  ConsoleView::render('newsletter',['notice'=>$notice,'selected'=>$selected,'campaigns'=>$campaigns,'audience'=>(int)$db->fetch("SELECT COUNT(*) n FROM goldensite_subscribers WHERE status='active' AND consented_at IS NOT NULL AND consent_key IS NOT NULL")['n'],'subscribers'=>$db->fetchAll('SELECT id,email,status,consented_at,subscribed_at FROM goldensite_subscribers ORDER BY id DESC LIMIT 100'),'worker'=>Settings::get('module_worker_seen'),'zone'=>Settings::get('site_timezone','UTC')],'Goldensite Newsletter PRO');
 }
}
