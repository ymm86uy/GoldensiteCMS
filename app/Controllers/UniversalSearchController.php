<?php
namespace App\Controllers;
use App\Core\{ConsoleView,RateLimiter,UniversalSearch};
final class UniversalSearchController
{
    public function api(): void { header('Content-Type: application/json; charset=utf-8'); header('Cache-Control: no-store'); try { if (!RateLimiter::consume('search:'.($_SERVER['REMOTE_ADDR']??''),60,60)) { http_response_code(429); echo json_encode(['success'=>false,'status'=>'limited','data'=>[]]); return; } $q=UniversalSearch::query($_GET['q']??''); $result=UniversalSearch::find($q,(int)($_GET['page']??1)); echo json_encode(['success'=>true,'status'=>'success']+$result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR); } catch (\InvalidArgumentException $e) { http_response_code(422); echo json_encode(['success'=>false,'data'=>[],'message'=>$e->getMessage()]); } }
    public function index(): void { header('Cache-Control: no-store'); if (!RateLimiter::consume('search:'.($_SERVER['REMOTE_ADDR']??''),60,60)) { http_response_code(429); echo 'Espera antes de repetir la búsqueda.'; return; } $q=''; $status=null; $result=['data'=>[],'total'=>0,'page'=>1,'pages'=>0]; try { $q=UniversalSearch::query($_GET['q']??''); $result=UniversalSearch::find($q,(int)($_GET['page']??1)); } catch (\InvalidArgumentException $e) { http_response_code(422); $status=$e->getMessage(); } $e=ConsoleView::escape(...); $siteTitle='Buscar · Goldensite'; ob_start(); require dirname(__DIR__,2).'/views/frontend/search.php'; $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/main.php'; }
}
