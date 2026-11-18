<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Database;
use App\Core\GalleryCarousel;

class GalleryController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        $db = Database::getInstance()->getConnection();
        
        // Crear tabla para almacenar galerías si no existe
        $db->exec("CREATE TABLE IF NOT EXISTS goldensite_galleries (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            layout_type VARCHAR(50) DEFAULT 'masonry',
            animation_fx VARCHAR(50) DEFAULT 'zoom-fade',
            images_json LONGTEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        $galleries = $db->query("SELECT * FROM goldensite_galleries ORDER BY id DESC")->fetchAll();

        $title = "Galerías & Motion FX - GoldenSiteCMS";

        ob_start();
        require_once __DIR__ . '/../../../views/admin/gallery.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }

    public function save(): void
    {
        Auth::requireAdmin();
        \App\Core\RequestGuard::post();
        $title = is_string($_POST['title'] ?? null) ? mb_substr(trim($_POST['title']),0,255) : '';
        $layout = $_POST['layout_type'] ?? 'masonry';
        $animation = $_POST['animation_fx'] ?? 'zoom-fade';
        if (!in_array($layout,['masonry','carousel','slider','grid'],true) || !in_array($animation,GalleryCarousel::EFFECTS,true)) {
            http_response_code(422);
            echo 'Elige un diseño y un efecto disponibles.';
            return;
        }
        $imagesArr = $_POST['images'] ?? [];
        $images = [];
        foreach (is_array($imagesArr) ? $imagesArr : [] as $entry) {
            if (!is_string($entry)) continue;
            foreach (preg_split('/\R/', $entry) as $line) {
                $line = trim($line);
                if ($layout === 'carousel') {
                    if ($line === '') continue;
                    $card = GalleryCarousel::card(array_map('trim',explode('|',$line,3)));
                    if ($card === null || count($images) >= 40) {
                        http_response_code(422);
                        echo 'El carrusel admite hasta 40 tarjetas con imágenes HTTPS o rutas locales. Revisa también los enlaces.';
                        return;
                    }
                    $images[] = $card;
                } elseif (preg_match('~^(?:https?://|/uploads/)~i', $line) && !preg_match('/[\r\n]/', $line)) {
                    $images[$line] = $line;
                }
            }
        }
        if ($layout === 'carousel' && ($title === '' || $images === [])) {
            http_response_code(422);
            echo 'Escribe un título y agrega al menos una tarjeta.';
            return;
        }

        if (!empty($title)) {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare("INSERT INTO goldensite_galleries (title, layout_type, animation_fx, images_json) VALUES (:title, :layout, :anim, :images)");
            $stmt->execute([
                'title'  => $title,
                'layout' => $layout,
                'anim'   => $animation,
                'images' => json_encode(array_values($images), JSON_THROW_ON_ERROR)
            ]);
        }

        header('Location: /admin/gallery');
        exit;
    }
}
