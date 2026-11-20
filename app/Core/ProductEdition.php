<?php
namespace App\Core;

final class ProductEdition
{
    public static function current(): string
    {
        $file = dirname(__DIR__,2).'/release-edition.txt';
        return !is_file($file) || trim((string)file_get_contents($file))==='owner' ? 'owner' : 'commercial';
    }

    public static function ownerOnlyPath(string $path): bool
    {
        $path = strtolower($path);
        if (preg_match('~^docs/canal-owner-[^/]+\.md$|^deployment/commercial-channel-index\.php$~D',$path)) return true;
        return preg_match('~^(?:app/controllers/admin/owner(?:security)?controller\.php$|app/core/owner(?:access|licensing|licenseservice|catalog|updatepublication)\.php$|views/admin/owner(?:[./_]|$)|tools/(?:configure-owner|sign-update|sign-extension|build-release|build-update|build-forum-package|publish-update)\.php$|docs/(?:owner-y-licencias\.md|politica-de-cookies-goldensitecms\.html)$|tests/)~D',$path)===1;
    }
}
