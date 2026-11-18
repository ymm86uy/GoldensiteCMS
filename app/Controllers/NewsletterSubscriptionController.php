<?php
namespace App\Controllers;
use App\Core\{ConsoleView,Csrf,NewsletterService,RateLimiter,RequestGuard};
final class NewsletterSubscriptionController
{
    public function confirm(): void { $this->show('confirm'); }
    public function unsubscribe(): void { $this->show('unsubscribe'); }
    private function show(string $purpose): void
    {
        header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
        $status=null; $valid=false; $done=false; $e=ConsoleView::escape(...); $id=0; $token='';
        try {
            if (!RateLimiter::consume('newsletter-link:'.($_SERVER['REMOTE_ADDR']??''),30,300)) { http_response_code(429); throw new \InvalidArgumentException('Espera antes de volver a intentarlo.'); }
            $data=$_SERVER['REQUEST_METHOD']==='POST'?$_POST:$_GET;
            $id=filter_var($data['subscriber']??null,FILTER_VALIDATE_INT)?:0; $token=ConsoleView::field($data,'token',64);
            if (!NewsletterService::signedSubscriber($id,$token,$purpose)) { http_response_code(422); throw new \InvalidArgumentException('El enlace no es válido o ha vencido.'); }
            $valid=true;
            if ($_SERVER['REQUEST_METHOD']==='POST') { RequestGuard::post(); NewsletterService::changeSubscription($id,$token,$purpose); $done=true; }
        } catch (\InvalidArgumentException $error) { if (http_response_code()<400) http_response_code(422); $status=$error->getMessage(); }
        $siteTitle=$purpose==='confirm'?'Confirmar boletín · Goldensite':'Cancelar suscripción · Goldensite';
        ob_start(); require dirname(__DIR__,2).'/views/frontend/newsletter_subscription.php'; $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
