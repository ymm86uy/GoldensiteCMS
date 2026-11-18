<?php
namespace App\Core;
final class ModuleGate
{
    public static function idForAdminPath(string $path): ?string
    {
        $path=AdminPath::normalize($path);
        foreach (ModuleCatalog::all() as $module) if ($path===$module['url']||str_starts_with($path,$module['url'].'/')) return $module['id'];
        if (preg_match('~^/admin/(analytics|maintenance|chat-moderation|sales|migrator|seo-settings|release-backup)(?:/|$)~',$path,$m)) return ['analytics'=>'native-metrics','maintenance'=>'maintenance-mode','chat-moderation'=>'live-chat','sales'=>'commerce-sales','migrator'=>'universal-importer','seo-settings'=>'seo-analytics','release-backup'=>'release-backup'][$m[1]];
        return null;
    }
    public static function publicRoute(string $path): void
    {
        $id=match(true) {
            preg_match('~^/(?:blog(?:/|$)|api/blog/)~',$path)===1=>'blog',
            (in_array($path,['/sitemap.xml','/sitemap-core.xml','/robots.txt'],true)||str_starts_with($path,'/sitemap-library/'))=>'sitemap',
            str_starts_with($path,'/forms/')=>'form-builder',
            default=>null,
        };
        if ($id&&ModuleCatalog::isPro($id)) LicenseMiddleware::enforce($id,ModuleCatalog::get($id)['name'],ModuleCatalog::price($id));
    }
    public static function admin(string $path): void
    {
        $aliases = ['analytics'=>'native-metrics','maintenance'=>'maintenance-mode','site-health'=>'site-health','posts'=>'blog','chat-moderation'=>'live-chat','downloads' => 'server-downloads', 'paywall' => 'digital-vault', 'commerce' => 'commerce-sales', 'sales' => 'commerce-sales', 'importer' => 'universal-importer', 'migrator' => 'universal-importer', 'seo-tracker' => 'seo-analytics', 'seo-settings' => 'seo-analytics', 'backups' => 'release-backup'];
        if (!preg_match('~^/admin/([^/]+)(?:/|$)~D', $path, $match)) return;
        $id = $aliases[$match[1]] ?? ModuleCatalog::canonical($match[1]);
        if (ModuleCatalog::isPro($id)) LicenseMiddleware::enforce($id, ModuleCatalog::get($id)['name'], ModuleCatalog::price($id));
    }
}
