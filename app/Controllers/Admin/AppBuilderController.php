<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;

class AppBuilderController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        $downloadsDir = __DIR__ . '/../../../public/downloads/android';
        if (!is_dir($downloadsDir)) {
            mkdir($downloadsDir, 0777, true);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['app_builder_msg'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/app-builder');
                exit;
            }

            $appName    = trim($_POST['app_name'] ?? 'BellaHair');
            $packageId  = trim($_POST['package_id'] ?? 'com.GoldensiteCMS.bellahair');
            $primaryCol = trim($_POST['primary_color'] ?? '#38bdf8');
            $businessType = Settings::get('active_business_type', 'salon');

            $manifestData = [
                'business_name' => $appName,
                'package_id'    => $packageId,
                'type'          => $businessType,
                'primary_color' => $primaryCol,
                'api_endpoint'  => '/api/v1/data',
                'version'       => '1.0.0',
                'updated_at'    => date('Y-m-d H:i:s')
            ];

            Settings::set('android_business_manifest', json_encode($manifestData));
            file_put_contents($downloadsDir . '/manifest.json', json_encode($manifestData, JSON_PRETTY_PRINT));
            
            $_SESSION['app_builder_msg'] = [
                'type' => 'success', 
                'msg'  => 'Manifest Android guardado. Para obtener un APK o AAB instalable es necesario compilar y firmar con Android SDK.'
            ];

            header('Location: /admin/app-builder');
            exit;
        }

        $manifestJson = Settings::get('android_business_manifest', '{}');
        $manifest = json_decode($manifestJson, true) ?: [
            'business_name' => 'BellaHair',
            'package_id'    => 'com.GoldensiteCMS.bellahair',
            'primary_color' => '#38bdf8'
        ];

        $status = $_SESSION['app_builder_msg'] ?? null;
        unset($_SESSION['app_builder_msg']);

        $title = "Android Goldensite App Builder - Configuración & Build";
        
        ob_start();
        require_once __DIR__ . '/../../../views/admin/app_builder/index.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}