<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;

class MediaController
{
    private string $uploadDir;

    public function __construct()
    {
        $this->uploadDir = __DIR__ . '/../../../public/uploads/';
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }

    public function index(): void
    {
        if (class_exists('\App\Core\Auth')) {
            Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (class_exists('\App\Core\Csrf') && !Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['media_status'] = ['type' => 'danger', 'msg' => 'Error de seguridad: Token CSRF inválido.'];
                header('Location: /admin/media');
                exit;
            }

            $action = $_POST['action'] ?? '';

            if ($action === 'upload' && isset($_FILES['file'])) {
                $url = (new \App\Core\Uploader())->upload($_FILES['file']);
                $_SESSION['media_status'] = $url
                    ? ['type' => 'success', 'msg' => 'Archivo subido correctamente.']
                    : ['type' => 'danger', 'msg' => 'Archivo inválido. Usa JPG, PNG, GIF, WEBP o PDF de hasta 5 MB.'];
            } elseif ($action === 'delete') {
                $fileName = basename($_POST['file_name'] ?? '');
                $filePath = $this->uploadDir . $fileName;
                if ($fileName !== '' && $fileName[0] !== '.' && is_file($filePath) && !is_link($filePath)) {
                    unlink($filePath);
                    $_SESSION['media_status'] = ['type' => 'success', 'msg' => "Archivo '{$fileName}' eliminado."];
                }
            }

            header('Location: /admin/media');
            exit;
        }

        $status = $_SESSION['media_status'] ?? null;
        unset($_SESSION['media_status']);

        $files = array_values(array_filter(scandir($this->uploadDir), fn ($name) => $name[0] !== '.' && is_file($this->uploadDir . $name) && !is_link($this->uploadDir . $name)));
        $csrfToken = class_exists('\App\Core\Csrf') ? Csrf::generate() : '';
        $title = "Biblioteca de Medios - GoldenSiteCMS";

        ob_start();
        require_once __DIR__ . '/../../../views/admin/media.php';
        $content = ob_get_clean();

        if (file_exists(__DIR__ . '/../../../views/layouts/admin.php')) {
            require_once __DIR__ . '/../../../views/layouts/admin.php';
        } else {
            echo $content;
        }
    }
}