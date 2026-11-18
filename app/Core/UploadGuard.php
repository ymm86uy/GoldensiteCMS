<?php
namespace App\Core;

final class UploadGuard
{
    public static function move(string $temporary, string $destination): bool
    {
        if (!is_uploaded_file($temporary) || !is_file($temporary) || filesize($temporary) > 20971520 || filesize($temporary) < 1) return false;
        $root = dirname(__DIR__, 2);
        $parent = realpath(dirname($destination));
        if (!$parent || is_link(dirname($destination)) || file_exists($destination)) return false;
        $parent = str_replace('\\', '/', $parent) . '/';
        $public = str_replace('\\', '/', realpath($root . '/public/uploads')) . '/';
        $private = str_replace('\\', '/', realpath($root . '/storage')) . '/';
        if (!str_starts_with($parent, $public) && !str_starts_with($parent, $private)) return false;
        $name = basename($destination);
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_.-]*$/D', $name)) return false;
        if (str_starts_with($parent, $public)) {
            $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif', 'pdf' => 'application/pdf', 'mp4' => 'video/mp4', 'webm' => 'video/webm'];
            $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            if (!isset($types[$extension]) || preg_match('/\.(php[0-9]*|phar|phtml|html?|svg|js)(\.|$)/i', $name)) return false;
            if ((new \finfo(FILEINFO_MIME_TYPE))->file($temporary) !== $types[$extension]) return false;
            if (str_starts_with($types[$extension], 'image/')) {
                $image = @getimagesize($temporary);
                if (!$image || $image[0] * $image[1] > 40000000) return false;
            }
        }
        return move_uploaded_file($temporary, $destination);
    }
}
