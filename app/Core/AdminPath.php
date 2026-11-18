<?php
namespace App\Core;
final class AdminPath
{
    private const RESERVED = ['api','login','logout','register','registro','mi-cuenta','install','chat','blog','page','uploads','assets','storage','config','vendor','themes','plugins','modules','routes','tools','tests','public','database','cache','downloads','download','tienda','producto','cart','checkout','marketplace','dating','faq','contacto','account','buscar','comunidad','biblioteca','forms','language','politica-cookies','updates','weather-time','forum','social'];

    public static function valid(string $slug): bool
    {
        return preg_match('/^[a-z][a-z0-9_-]{2,30}$/D', $slug) === 1 && !in_array($slug, self::RESERVED, true);
    }

    public static function fromLabel(string $label): string
    {
        $label = strtr(mb_strtolower(trim($label), 'UTF-8'), [
            'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a','é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
            'í'=>'i','ì'=>'i','ï'=>'i','ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
            'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','ñ'=>'n','ç'=>'c',
        ]);
        return preg_replace('/ +/', '-', $label);
    }

    public static function base(): string
    {
        $slug = Settings::get('admin_custom_slug', 'admin');
        return self::valid($slug) ? '/'.$slug : '/admin';
    }
    public static function normalize(string $path): string
    {
        $base = self::base();
        return $path === $base || str_starts_with($path, $base.'/') ? '/admin'.substr($path, strlen($base)) : $path;
    }
    public static function url(string $path): string { return preg_match('~^/admin(?:$|[/#?])~', $path) ? self::base().substr($path, 6) : $path; }
}
