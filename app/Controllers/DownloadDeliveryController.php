<?php
namespace App\Controllers;
use App\Core\{ConsoleView,DownloadLibrary,FrontendEngine,RateLimiter};
final class DownloadDeliveryController
{
    public function download(string $id): void
    {
        header('Cache-Control: no-store, private'); header('Referrer-Policy: no-referrer'); header('X-Robots-Tag: noindex, nofollow');
        $status=null;
        try {
            if (!FrontendEngine::isModuleActive('server-downloads')) throw new \DomainException('El servicio de descargas no está activo.');
            if (!RateLimiter::consume('download:'.($_SERVER['REMOTE_ADDR']??''),60,60)) { http_response_code(429); throw new \DomainException('Espera antes de solicitar más descargas.'); }
            if (!ctype_digit($id)||(int)$id<1) throw new \InvalidArgumentException('Enlace inválido.');
            $file=DownloadLibrary::authorize((int)$id,$_GET); DownloadLibrary::stream($file);
        } catch (\DomainException $e) { if (http_response_code()<400) http_response_code(403); $status=$e->getMessage(); }
        catch (\OutOfBoundsException $e) { http_response_code(404); $status=$e->getMessage(); }
        catch (\UnexpectedValueException $e) { http_response_code(409); $status=$e->getMessage(); }
        catch (\InvalidArgumentException $e) { http_response_code(401); $status='El enlace no está disponible. Puede haber vencido, sido revocado o alcanzado su límite.'; }
        $e=ConsoleView::escape(...); $siteTitle='Descargas · Goldensite'; $content='<link rel="stylesheet" href="/assets/account.css?v=20261104r2"><section class="nx-account-page"><h1>Goldensite Storage & Downloads</h1><div class="nx-account-card"><p role="alert">'.$e($status).'</p><p><a href="/login">Iniciar sesión</a> · <a href="/contacto">Contactar con administración</a></p></div></section>'; require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
