<?php
namespace App\Core;

final class GalleryCarousel
{
    public const EFFECTS = ['zoom-fade','3d-rotate','slide-parallax','bounce-in'];

    public static function card(array $values): ?array
    {
        [$image, $title, $link] = array_pad($values,3,'');
        if (!is_string($image) || !self::url($image)
            || !is_string($title) || !is_string($link) || ($link !== '' && !self::url($link))) return null;
        return [$image, mb_substr($title,0,120), $link];
    }

    public static function render(array $gallery, mixed $images): string
    {
        if (!is_array($images)) return '';
        $cards = [];
        foreach (array_slice($images,0,40) as $image) {
            $values = is_string($image) ? [$image] : $image;
            if (is_array($values) && ($card = self::card(array_values($values))) !== null) $cards[] = $card;
        }
        if ($cards === []) return '';
        $title = $gallery['title'];
        $effect = in_array($gallery['animation_fx'],self::EFFECTS,true) ? $gallery['animation_fx'] : 'zoom-fade';
        $assets = !self::$assetsRendered;
        self::$assetsRendered = true;
        $escape = ConsoleView::escape(...);
        ob_start();
        require dirname(__DIR__,2).'/views/frontend/gallery_carousel.php';
        return (string)ob_get_clean();
    }

    private static bool $assetsRendered = false;

    private static function url(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048 || preg_match('/[\x00-\x20<>"\'\\\\]/',$url)) return false;
        if (str_starts_with($url,'/')) return !str_starts_with($url,'//');
        return str_starts_with($url,'https://') && filter_var($url,FILTER_VALIDATE_URL) !== false;
    }
}
