<?php
namespace App\Core;
/** Original Goldensite line system: cut corners, offset nodes, 28-unit grid. No icon library. */
final class GoldensiteIcon
{
    public static function svg(string $name,int $size=24): string
    {
        $paths=[
            'goldensite'=>'M24 5H9l-5 5v13h20v-9H14m10-9v4',
            'dashboard'=>'M4 5h8v8H4zM16 5h8v4h-8zM16 13h8v10h-8zM4 17h8v6H4z',
            'messages'=>'M4 6h20v13H11l-5 4v-4H4zM8 10h12M8 14h8',
            'activity-log'=>'M5 4h14l4 4v16H5zM17 4v6h6M8 17h3l2-5 3 9 2-4h3',
            'pages'=>'M6 3h11l5 5v17H6zM16 3v7h6M10 14h8M10 18h8M10 22h4',
            'posts'=>'M5 8h9M5 13h7M5 18h5M15 17l6-12 4 2-7 13-5 3z',
            'menus'=>'M5 5h6v6H5zM17 17h6v6h-6zM5 17h6v6H5zM8 11v6M11 8h9v9',
            'ai-builder'=>'M5 5h18v18H5zM5 11h18M11 11v12M15 16l2-2 2 2-2 3z',
            'themes'=>'M4 9l10-6 10 6-10 6zM4 15l10 6 10-6M4 21l10 6 10-6',
            'customize'=>'M5 7h18M5 14h18M5 21h18M9 4v6M19 11v6M12 18v6',
            'plugins'=>'M4 4h8v8H4zM17 4h7v8h-7zM4 17h8v7H4zM17 17h7v7h-7zM12 8h5M8 12v5M20 12v5M12 20h5',
            'waf'=>'M14 3l10 4v8c0 5-5 9-10 11C9 24 4 20 4 15V7zM9 14l4 4 7-9',
            'settings'=>'M4 8h20M4 20h20M8 4v8M20 16v8M14 8v12M10 14h8',
            'owner'=>'M4 9l5 5 5-9 5 9 5-5-2 13H6zM8 25h12',
            'typography'=>'M3 7h14M10 7v17M6 24h8M16 22l4-10 5 10M18 18h5',
            'identity-access'=>'M14 3l10 5v8l-10 9L4 16V8zM10 11a4 4 0 1 0 8 0 4 4 0 1 0-8 0M8 21v-3l6-2 6 2v3',
            'deploy-manager'=>'M4 18h8v7H4zM16 3h8v7h-8zM8 18v-9h8M12 5l4 4-4 4M16 21h8M20 17l4 4-4 4',
            'form-builder'=>'M5 3h18v22H5zM9 8h3v3H9zM15 9h4M9 15h3v3H9zM15 16h4M9 22h10',
            'users'=>'M7 10a4 4 0 1 0 8 0 4 4 0 1 0-8 0M3 25v-5l8-4 8 4v5M19 7l5 3v5M22 19l3 2v4',
            'search'=>'M5 11a7 7 0 1 0 14 0 7 7 0 1 0-14 0M17 17l8 8',
            'arrow'=>'M4 14h19M17 7l7 7-7 7',
            'language'=>'M3 6h14M10 3v3M6 6c0 8 4 11 9 13M14 6c0 7-4 10-10 14M15 25l5-13 5 13M17 21h6',
            'gdpr-banner'=>'M4 6h20v17H4zM4 12h20M8 17h7M18 16l3 3-3 3',
            'sitemap'=>'M10 3h8v6h-8zM3 19h6v6H3zM19 19h6v6h-6zM6 19v-5h16v5M14 9v5',
            'webhooks'=>'M4 6h7v7H4zM17 15h7v7h-7zM11 9h10v6M7 13v10h10M17 19l3 3-3 3',
            'asset-optimizer'=>'M5 4h18v20H5zM9 9h10M9 14h7M9 19h4M18 16l3 3-3 3',
            'qr-generator'=>'M3 3h8v8H3zM17 3h8v8h-8zM3 17h8v8H3zM17 17h4v4h4v4h-8zM7 7h0M21 7h0M7 21h0',
            'import-export'=>'M4 8h18M17 3l5 5-5 5M24 20H6M11 15l-5 5 5 5',
            'blog'=>'M5 4h18v20H5zM9 8h10v5H9zM9 17h10M9 20h6',
            'edge-waf'=>'M14 2l11 6-4 14-7 4-7-4L3 8zM9 10h10v9H9zM14 6v4M14 19v4',
            'native-metrics'=>'M4 24V5M4 24h21M8 19v-5h4v5M15 19V8h4v11M22 19V3',
            'monitor-redirects'=>'M4 6h11v7H4zM17 16h7v7h-7zM15 9h6v7M5 20h8M10 17l3 3-3 3',
            'admin-tools'=>'M5 4l7 7-3 3-7-7M9 14l12 12 5-5L14 9M16 7l4-4 5 1-1 5-4 4',
            'maintenance-mode'=>'M5 18l9-15 10 15v7H5zM8 18h13M11 10h6M14 19v6',
            'site-health'=>'M4 5h20v18H4zM4 11h20M7 17h3l2-4 3 8 2-4h4',
            'live-chat'=>'M3 4h17v14H9l-6 6zM20 9h5v16l-6-4h-6M7 9h9M7 13h6',
            'login-security'=>'M5 3h17v22H5zM10 8h7v8h-7zM13 5v3M14 16v5M9 25h10',
            'marketplace'=>'M3 10l3-6h16l3 6v5H3zM6 15v10h16V15M10 25v-6h8v6M9 4v11M18 4v11',
            'server-downloads'=>'M4 3h20v7H4zM4 14h8v9H4zM8 6h0M20 14v11M16 21l4 4 4-4',
            'gallery'=>'M4 4h20v20H4zM4 20l7-8 5 5 3-4 5 7M17 8h3v3h-3z',
            'universal-importer'=>'M4 3h11v9H4zM17 16h8v9h-8zM8 12v9h9M13 17l4 4-4 4M15 7h8v5',
            'newsletter'=>'M3 7h22v16H3zM3 7l11 10L25 7M7 3h14M3 23l7-7M25 23l-7-7',
            'digital-vault'=>'M4 4h20v20H4zM8 8h12v12H8zM14 10v8M10 14h8M24 9h2M24 19h2',
            'commerce-sales'=>'M3 4h4l3 14h12l3-10H8M11 22h2v3h-2zM20 22h2v3h-2zM13 12h8',
            'seo-analytics'=>'M3 3h15v17H3zM7 7h7M7 11h5M18 20l7 6M12 17a5 5 0 1 0 10 0 5 5 0 1 0-10 0',
            'dam'=>'M3 8h9l3-4h10v21H3zM3 12h22M8 17h4v4H8zM16 17h5M16 21h5',
            'db-cleaner'=>'M4 7c0-5 20-5 20 0s-20 5-20 0v13c0 5 20 5 20 0V7M4 13c0 4 20 4 20 0M14 15v10',
            'automation-hub'=>'M10 3h8v8h-8zM3 18h7v7H3zM18 18h7v7h-7zM14 11v4M7 18v-3h14v3M11 7h6',
            'sso'=>'M4 9h7v11H4zM17 9h7v11h-7zM11 14h6M7 9V4h14v5M14 4v5',
            'release-backup'=>'M4 3h16l4 4v18H4zM8 3v8h11V3M9 25v-9h10v9M16 19v4',
            'dating'=>'M14 25L4 15V7l5-4 5 4 5-4 5 4v8zM7 12h4l3 5 3-5h4',
        ];
        if (!isset($paths[$name])) { $n=hexdec(substr(hash('sha256',$name),0,2)); $x=7+$n%12; $paths[$name]='M6 3h12l7 7v12H10l-7-7V6zM6 3l19 19M'.$x.' 7v14M7 14h14'; }
        return '<svg class="nx-icon" width="'.$size.'" height="'.$size.'" viewBox="0 0 28 28" fill="none" stroke="currentColor" stroke-width="1.55" stroke-linecap="square" stroke-linejoin="bevel" aria-hidden="true" focusable="false"><path d="'.$paths[$name].'"/></svg>';
    }
}
