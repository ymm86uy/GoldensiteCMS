<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Settings;
use App\Core\Database;

class AiBuilderController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        if (session_status() === PHP_SESSION_NONE) session_start();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['ai_status'] = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
                header('Location: /admin/ai-builder');
                exit;
            }

            $action = $_POST['action'] ?? '';

            if ($action === 'publish_layout') {
                $htmlCode     = $_POST['html_code'] ?? '';
                $businessType = $_POST['business_type'] ?? 'generic';
                $appName      = $_POST['app_name'] ?? 'BellaHair';
                
                if (!empty($htmlCode)) {
                    Settings::set('custom_ai_frontend', $htmlCode);
                    Settings::set('use_ai_frontend', '1');
                    Settings::set('active_business_type', $businessType);
                    
                    // 1. Aprovisionar base de datos
                    $this->provisionBusinessTables($businessType);

                    // 2. Crear estructura física en public/downloads/android/
                    $downloadsDir = __DIR__ . '/../../../public/downloads/android';
                    if (!is_dir($downloadsDir)) {
                        mkdir($downloadsDir, 0777, true);
                    }

                    // 3. Generar Business Manifest dinámico
                    $manifestData = [
                        'business_name' => $appName,
                        'type'          => $businessType,
                        'package_id'    => 'com.GoldensiteCMS.' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $appName)),
                        'modules'       => $this->getModulesByBusiness($businessType),
                        'api_endpoint'  => '/api/v1/data',
                        'updated_at'    => date('Y-m-d H:i:s')
                    ];
                    
                    file_put_contents($downloadsDir . '/manifest.json', json_encode($manifestData, JSON_PRETTY_PRINT));
                    Settings::set('android_business_manifest', json_encode($manifestData));

                    $_SESSION['ai_status'] = [
                        'type' => 'success', 
                        'msg'  => 'Diseño publicado y manifest.json generado. La aplicación Android requiere compilación y firma con Android SDK.'
                    ];
                } else {
                    $_SESSION['ai_status'] = ['type' => 'danger', 'msg' => 'Por favor presiona "Generar & Previsualizar" antes de integrar.'];
                }
            } elseif ($action === 'disable_ai_frontend') {
                Settings::set('use_ai_frontend', '0');
                $_SESSION['ai_status'] = ['type' => 'warning', 'msg' => '🔴 Diseños IA desactivados. Se restauró la plantilla base.'];
            }

            header('Location: /admin/ai-builder');
            exit;
        }

        $activeAiLayout = Settings::get('custom_ai_frontend', '');
        $isAiActive     = Settings::get('use_ai_frontend', '0') === '1';

        $status = $_SESSION['ai_status'] ?? null;
        unset($_SESSION['ai_status']);

        $csrfToken = Csrf::generate();
        $title     = "Generador Asistido por IA & Constructor Visual - GoldenSiteCMS";

        ob_start();
        require_once __DIR__ . '/../../../views/admin/ai_builder/index.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }

    public function apiManifest(): void
    {
        header('Content-Type: application/json');
        $manifest = Settings::get('android_business_manifest', '{}');
        echo $manifest;
        exit;
    }

    public function apiData(): void
    {
        header('Content-Type: application/json');
        $type = Settings::get('active_business_type', 'generic');
        echo json_encode([
            'status' => 'success',
            'business' => $type,
            'modules' => $this->getModulesByBusiness($type),
            'timestamp' => time()
        ]);
        exit;
    }

    private function getModulesByBusiness(string $type): array
    {
        $map = [
            'salon'      => ['services', 'staff', 'appointments', 'gallery', 'reviews', 'contact'],
            'restaurant' => ['menu', 'wines', 'reservations', 'location', 'contact'],
            'dealer'     => ['vehicles', 'specifications', 'test_drives', 'contact'],
            'workshop'   => ['services', 'obd2_diagnostics', 'appointments', 'contact'],
            'realestate' => ['properties', 'agents', 'inquiries', 'location'],
            'generic'    => ['content', 'pages', 'forms', 'contact']
        ];
        return $map[$type] ?? $map['generic'];
    }

    private function provisionBusinessTables(string $type): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            if ($type === 'salon' || $type === 'workshop' || $type === 'restaurant') {
                $db->exec("CREATE TABLE IF NOT EXISTS goldensite_appointments (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    client_name VARCHAR(150) NOT NULL,
                    client_phone VARCHAR(50) NOT NULL,
                    service_requested VARCHAR(150) NOT NULL,
                    booking_date DATETIME NOT NULL,
                    status VARCHAR(20) DEFAULT 'pending',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
            }
        } catch (\Throwable $e) {}
    }
}