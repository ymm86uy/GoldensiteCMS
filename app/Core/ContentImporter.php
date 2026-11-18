<?php
namespace App\Core;
final class ContentImporter
{
    public static function import(string $json): int
    {
        if (strlen($json) > 5242880) throw new \InvalidArgumentException('El archivo supera 5 MB.');
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\JsonException $e) { throw new \InvalidArgumentException('JSON inválido. No se ejecutan volcados SQL ni XML.'); }
        $posts = is_array($data) ? ($data['posts'] ?? null) : null;
        if (!is_array($posts) || !array_is_list($posts) || count($posts) < 1 || count($posts) > 1000) throw new \InvalidArgumentException('Se requiere una lista posts de 1 a 1000 artículos.');
        $validated = []; $slugs = [];
        foreach ($posts as $post) {
            if (!is_array($post)) throw new \InvalidArgumentException('Artículo inválido.');
            $title = LicenseService::field($post, 'title', 255); $slug = LicenseService::field($post, 'slug', 255);
            $content = $post['content'] ?? '';
            if ($title === '' || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) || isset($slugs[$slug]) || !is_string($content) || strlen($content) > 200000) throw new \InvalidArgumentException('Comprueba títulos, slugs únicos y contenido hasta 200 KB por artículo.');
            $slugs[$slug] = true;
            // Imported external material is plain text and always starts unpublished.
            $validated[] = [$title, $slug, '<p>' . nl2br(htmlspecialchars(strip_tags($content), ENT_QUOTES, 'UTF-8')) . '</p>'];
        }
        $db = Database::getInstance()->getConnection(); $db->beginTransaction();
        try {
            $exists = $db->prepare('SELECT id FROM goldensite_posts WHERE slug = ?');
            $insert = $db->prepare("INSERT INTO goldensite_posts (title, slug, content, status, source_platform) VALUES (?, ?, ?, 'draft', 'json')");
            foreach ($validated as $post) {
                $exists->execute([$post[1]]); if ($exists->fetchColumn()) throw new \InvalidArgumentException('Ya existe el slug ' . $post[1] . '. No se reemplazó contenido.');
                $insert->execute($post);
            }
            $db->commit(); return count($validated);
        } catch (\Throwable $error) { $db->rollBack(); throw $error; }
    }
}
