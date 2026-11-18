<?php
namespace App\Controllers;

use App\Core\PaywallEngine;
use App\Models\Post;

class BlogApiController
{
    public function getPaginatedPosts(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        $page = max(1, min(10000, (int) ($_GET['page'] ?? 1)));
        $all = Post::published();
        $posts = array_slice($all, ($page - 1) * 6, 6);
        foreach ($posts as &$post) {
            $post['formatted_date'] = date('d M, Y', strtotime($post['created_at']));
            $post['excerpt'] = mb_substr(strip_tags(PaywallEngine::parse($post['content'] ?? '')), 0, 140);
            unset($post['content']);
        }
        unset($post);
        echo json_encode(['success' => true, 'page' => $page, 'total_pages' => (int) ceil(count($all) / 6), 'posts' => $posts], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
