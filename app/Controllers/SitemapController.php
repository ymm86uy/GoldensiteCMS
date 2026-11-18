<?php
namespace App\Controllers;

use App\Core\Database;
use App\Core\Environment;
use App\Core\Settings;

class SitemapController
{
    private function baseUrl(): string
    {
        $fallback = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? 'localhost');
        $url = Environment::get('APP_URL', Settings::get('site_url', $fallback));
        if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) $url = $fallback;
        return rtrim($url, '/');
    }

    public function xml(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        if (Settings::get('joomla_catalog_enabled')==='1') {
            $urls=[$this->baseUrl().'/sitemap-core.xml'];
            $max=(int)Database::getInstance()->fetch("SELECT MAX(source_id) AS max_id FROM goldensite_joomla_entries WHERE source_id<2000000000")['max_id'];
            for ($page=1;$page<=min(1000,(int)ceil($max/10000));$page++) $urls[]=$this->baseUrl().'/sitemap-library/'.$page.'.xml';
            $urls[]=$this->baseUrl().'/sitemap-library/contributions.xml';
            echo $this->encode($urls,true);
        } else echo $this->document();
        exit;
    }

    public function core(): void
    {
        header('Content-Type: application/xml; charset=utf-8');
        echo $this->document();
        exit;
    }

    public function library(string $file): void
    {
        if (Settings::get('joomla_catalog_enabled')!=='1' || !preg_match('/^(contributions|[1-9][0-9]{0,3})\.xml$/D',$file,$match)) { http_response_code(404); return; }
        $min=$match[1]==='contributions'?2000000000:((int)$match[1]-1)*10000;
        $max=$match[1]==='contributions'?4294967295:$min+10000;
        $rows=Database::getInstance()->fetchAll("SELECT e.source_id FROM goldensite_joomla_entries e
            JOIN goldensite_joomla_categories c ON c.source_id=e.category_id AND c.is_visible=1
            LEFT JOIN goldensite_catalog_media m ON m.entry_id=e.source_id
            WHERE e.is_visible=1 AND COALESCE(m.view_mode,'public')='public' AND e.source_id>? AND e.source_id<=? ORDER BY e.source_id LIMIT 50000",[$min,$max]);
        header('Content-Type: application/xml; charset=utf-8');
        $base=$this->baseUrl();
        echo $this->encode(array_map(static fn($row)=>$base.'/biblioteca/ficha/'.(int)$row['source_id'],$rows));
        exit;
    }

    public function document(): string
    {
        $base = $this->baseUrl();
        $urls = [$base . '/', $base . '/blog', $base . '/faq', $base . '/contacto'];
        $db = Database::getInstance();
        foreach (['goldensite_pages', 'goldensite_posts', 'posts'] as $table) {
            $condition = $table === 'goldensite_pages' ? ' AND is_published = 1' : '';
            foreach ($db->fetchAll("SELECT slug FROM $table WHERE status = 'published'$condition") as $row) $urls[] = $base . ($table === 'goldensite_pages' ? '/page/' : '/blog/') . rawurlencode($row['slug']);
        }
        return $this->encode($urls);
    }

    private function encode(array $urls,bool $index=false): string
    {
        $root=$index?'sitemapindex':'urlset';
        $item=$index?'sitemap':'url';
        $xml='<?xml version="1.0" encoding="UTF-8"?>'."\n".'<'.$root.' xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach (array_unique($urls) as $url) $xml.='<'.$item.'><loc>'.htmlspecialchars($url,ENT_XML1|ENT_QUOTES,'UTF-8').'</loc></'.$item.'>';
        return $xml.'</'.$root.'>';
    }

    public function robots(): void
    {
        header('Content-Type: text/plain; charset=utf-8');
        $setting = Database::getInstance()->fetch("SELECT setting_value FROM goldensite_sitemap_settings WHERE setting_key = 'robots_content'");
        $robots = trim($setting['setting_value'] ?? '') ?: "User-agent: *\nDisallow: /admin/\nDisallow: /api/\nDisallow: /login\nAllow: /";
        $robots = str_replace('Sitemap: http://localhost:8000/sitemap.xml', 'Sitemap: ' . $this->baseUrl() . '/sitemap.xml', $robots);
        if (!preg_match('/^Sitemap:/mi', $robots)) $robots .= "\n\nSitemap: " . $this->baseUrl() . '/sitemap.xml';
        echo $robots . "\n";
        exit;
    }
}
