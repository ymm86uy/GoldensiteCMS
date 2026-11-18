<?php
declare(strict_types=1);
namespace App\Controllers;

use App\Core\{Auth,ConsoleView,ExtensionPackages,FrontendEngine,ProductEdition};

final class ExtensionController
{
    public function feed(): void
    {
        if (ProductEdition::current()!=='owner') { http_response_code(404); return; }
        $codes=[];
        foreach (ExtensionPackages::installed() as $item) if ($item['published']===true) $codes[]=$item['code'];
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: public, max-age=60');
        echo json_encode($codes,JSON_THROW_ON_ERROR);
    }

    public function package(string $hash): void
    {
        if (ProductEdition::current()==='owner' && preg_match('/^[a-f0-9]{64}$/D',$hash)) {
            foreach (ExtensionPackages::installed() as $item) {
                if ($item['published']!==true || $item['sha256']!==$hash) continue;
                $directory=ExtensionPackages::directory().'/'.$hash;
                $path=$directory.'/package.zip';
                if (is_link($directory)||is_link($path)||!is_file($path)||!hash_equals($hash,hash_file('sha256',$path))) break;
                header('Content-Type: application/zip');
                header('Content-Disposition: attachment; filename="goldensite-extension.zip"');
                header('Content-Length: '.filesize($path));
                header('Cache-Control: public, max-age=60');
                readfile($path);
                return;
            }
        }
        http_response_code(404);
    }

    public function page(string $id): void { $this->run($id,false); }
    public function admin(string $id): void { Auth::requireAdmin(); $this->run($id,true); }

    public function asset(string $id,string $name): void
    {
        $item=ExtensionPackages::installed()[$id]??null;
        $expected=$item['assets'][$name]??null;
        if (!$expected || !isset(ExtensionPackages::ASSETS[$name]) || !FrontendEngine::isModuleActive($id)
            || !preg_match('/^[a-f0-9]{64}$/D',$item['sha256'])) { http_response_code(404); return; }
        $directory=ExtensionPackages::directory().'/'.$item['sha256'];
        $path=$directory.'/'.$name;
        if (is_link($directory) || is_link($path) || !is_file($path) || !hash_equals($expected,hash_file('sha256',$path))) {
            http_response_code(404); return;
        }
        header('Content-Type: '.ExtensionPackages::ASSETS[$name]);
        header('Cache-Control: private, no-store');
        readfile($path);
    }

    private function run(string $id,bool $admin): void
    {
        $item=ExtensionPackages::installed()[$id]??null;
        if (!$item || !FrontendEngine::isModuleActive($id) || !preg_match('/^[a-f0-9]{64}$/D',$item['sha256'])) {
            http_response_code(404); return;
        }
        $expected=$item[$admin?'admin_hash':'main_hash'];
        $directory=ExtensionPackages::directory().'/'.$item['sha256'];
        $path=$directory.'/'.($admin?'admin.php':'main.php');
        if (!$expected || is_link($directory)||is_link($path)||!is_file($path)||!hash_equals($expected,hash_file('sha256',$path))) {
            http_response_code(404); return;
        }
        header('Cache-Control: private, no-store');
        $render=(static fn(string $file)=>require $file)($path);
        if (!$render instanceof \Closure) throw new \RuntimeException('La extensión debe devolver un controlador.');
        $content=$render();
        if (!is_string($content)) throw new \RuntimeException('La extensión debe devolver su contenido como texto.');
        $siteTitle=$title=$item['module']['name'];
        $root=dirname(__DIR__,2);
        require $root.'/views/layouts/'.($admin?'admin.php':'main.php');
    }
}
