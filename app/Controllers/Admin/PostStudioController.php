<?php
namespace App\Controllers\Admin;
final class PostStudioController extends PageStudioController
{
    protected string $table='goldensite_posts';
    protected string $route='/admin/posts';
    protected string $entity='post';
}
