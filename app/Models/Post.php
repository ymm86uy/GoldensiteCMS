<?php

namespace App\Models;

use App\Core\Database;

class Post
{
    public static function published(): array
    {
        $db = Database::getInstance();
        $combined = [];
        foreach (['goldensite_posts', 'posts'] as $table) {
            $where = $table === 'posts' ? " AND NOT EXISTS (SELECT 1 FROM goldensite_posts canonical WHERE canonical.slug=posts.slug)" : '';
            foreach ($db->fetchAll("SELECT * FROM $table WHERE status = 'published'$where ORDER BY created_at DESC") as $post) {
                if (isset($combined[$post['slug']])) continue;
                $post['excerpt'] = $post['excerpt'] ?? mb_substr(strip_tags(\App\Core\PaywallEngine::parse($post['content'] ?? '')), 0, 200);
                $combined[$post['slug']] = $post;
            }
        }
        $posts = array_values($combined);
        usort($posts, static fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $posts;
    }
    public static function all(): array
    {
        $db = Database::getInstance();
        return $db->fetchAll("SELECT * FROM posts ORDER BY created_at DESC");
    }

    public static function findBySlug(string $slug): ?array
    {
        $db = Database::getInstance();
        $canonical = $db->fetchOne("SELECT * FROM goldensite_posts WHERE slug = :slug", ['slug' => $slug]);
        if ($canonical !== null) return $canonical['status'] === 'published' ? $canonical : null;
        return $db->fetchOne("SELECT * FROM posts WHERE slug = :slug AND status = 'published'", ['slug' => $slug]);
    }

    public static function findById(int $id): ?array
    {
        $db = Database::getInstance();
        return $db->fetchOne("SELECT * FROM posts WHERE id = :id", ['id' => $id]);
    }

    public static function create(array $data): bool
    {
        $db = Database::getInstance();
        $sql = "INSERT INTO posts (title, slug, excerpt, content, status) 
                VALUES (:title, :slug, :excerpt, :content, :status)";
        return $db->execute($sql, [
            'title'   => $data['title'],
            'slug'    => $data['slug'],
            'excerpt' => $data['excerpt'],
            'content' => $data['content'],
            'status'  => $data['status'] ?? 'published'
        ]);
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::getInstance();
        $sql = "UPDATE posts 
                SET title = :title, slug = :slug, excerpt = :excerpt, content = :content, status = :status 
                WHERE id = :id";
        return $db->execute($sql, [
            'id'      => $id,
            'title'   => $data['title'],
            'slug'    => $data['slug'],
            'excerpt' => $data['excerpt'],
            'content' => $data['content'],
            'status'  => $data['status']
        ]);
    }

    public static function delete(int $id): bool
    {
        $db = Database::getInstance();
        return $db->execute("DELETE FROM posts WHERE id = :id", ['id' => $id]);
    }
}
