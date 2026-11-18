<?php

namespace App\Controllers;

use App\Core\View;
use App\Models\Post;

class BlogController
{
    public function index(): void
    {
        $posts = Post::published();

        View::render('blog/index', [
            'title' => 'Blog - GoldenSiteCMS',
            'posts' => $posts
        ]);
    }

    public function show(string $slug): void
    {
        $post = Post::findBySlug($slug);

        if (!$post) {
            http_response_code(404);
            View::render('errors/404', [
                'title' => '404 - Artículo No Encontrado'
            ]);
            return;
        }

        $translations=json_decode($post['translations_json']??'{}',true); $language=\App\Core\Locale::language(); if (is_array($translations[$language]??null)) foreach (['title','content'] as $key) if (is_string($translations[$language][$key]??null)&&$translations[$language][$key]!=='') $post[$key]=$translations[$language][$key];
        \App\Core\PublicRuntime::$editorial=$post;
        View::render('blog/show', [
            'title' => $post['title'] . ' - GoldenSiteCMS',
            'post'  => $post
        ]);
    }
}
