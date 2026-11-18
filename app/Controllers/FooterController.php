<?php
namespace App\Controllers;
use App\Core\{FrontendEngine,NewsletterService,RateLimiter,RequestGuard};
final class FooterController {
 public function subscribeNewsletter(): void {
  RequestGuard::post();
  if (!RateLimiter::consume('newsletter:'.($_SERVER['REMOTE_ADDR']??''),10,300)) { http_response_code(429); exit('Espera antes de volver a suscribirte.'); }
  try { $email=$_POST['newsletter_email']??$_POST['email']??''; if (!is_string($email)) throw new \InvalidArgumentException('Correo inválido.'); NewsletterService::subscribe(trim($email),($_POST['consent']??'')==='1'); $_SESSION['newsletter_status']=['type'=>'success','msg'=>'Solicitud registrada. Si corresponde, recibirás un enlace para confirmar la suscripción.']; }
  catch (\InvalidArgumentException $e) { $_SESSION['newsletter_status']=['type'=>'danger','msg'=>$e->getMessage()]; }
  header('Location: /#newsletter');
 }
}
