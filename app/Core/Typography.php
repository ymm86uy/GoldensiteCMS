<?php
namespace App\Core;
final class Typography
{
    public static function fonts(): array { return ['system'=>'Sistema · Segoe UI / sans serif','humanist'=>'Humanista · Trebuchet MS','editorial'=>'Editorial · Georgia','classic'=>'Clásica · Palatino','technical'=>'Técnica · Consolas','rounded'=>'Redondeada · Verdana']; }
    public static function stack(string $id): string { return ['system'=>"'Segoe UI',Arial,sans-serif",'humanist'=>"'Trebuchet MS',Arial,sans-serif",'editorial'=>"Georgia,'Times New Roman',serif",'classic'=>"'Palatino Linotype',Palatino,serif",'technical'=>"Consolas,'Courier New',monospace",'rounded'=>"Verdana,Geneva,sans-serif"][$id]??"'Segoe UI',Arial,sans-serif"; }
    public static function validate(array $data,bool $inherit=false): array {
        $font=ConsoleView::field($data,'font',30); $size=ConsoleView::field($data,'size',3); $heading=ConsoleView::field($data,'heading',3);
        if ($inherit && $font==='inherit') return [];
        if (!isset(self::fonts()[$font])||!ctype_digit($size)||(int)$size<12||(int)$size>28||!ctype_digit($heading)||(int)$heading<24||(int)$heading>80) throw new \InvalidArgumentException('Selecciona una fuente y tamaños entre 12–28 y 24–80 píxeles.');
        return ['font'=>$font,'size'=>(int)$size,'heading'=>(int)$heading];
    }
    public static function global(bool $admin=false): array { $p=json_decode(Settings::get($admin?'admin_typography':'site_typography','{}'),true); return array_merge(['font'=>'system','size'=>16,'heading'=>44],is_array($p)?array_intersect_key($p,array_flip(['font','size','heading'])):[]); }
    public static function style(bool $admin=false,?array $page=null): string {
        $p=self::global($admin); if ($page && is_array($local=json_decode($page['typography_json']??'{}',true)) && $local) $p=array_merge($p,$local);
        $size=max(12,min(28,(int)$p['size'])); $h=max(24,min(80,(int)$p['heading'])); $font=self::stack((string)$p['font']);
        $scope=$admin?'.nx-workspace':'.nx-public';
        return '<style id="goldensite-typography">html{font-size:'.$size.'px}'.$scope.'{--nx-font:'.$font.';--nx-type-size:'.$size.'px;--nx-heading-size:'.$h.'px}'.$scope.','.$scope.' p,'.$scope.' a,'.$scope.' li,'.$scope.' label,'.$scope.' input,'.$scope.' select,'.$scope.' button,'.$scope.' textarea{font-family:var(--nx-font)!important}'.$scope.'{font-size:var(--nx-type-size)}'.$scope.' .nx-editorial,'.$scope.' .nx-editorial p,'.$scope.' .nx-editorial li{font-size:var(--nx-type-size)!important;line-height:1.7}'.$scope.' h1{font-size:clamp(1.7rem,4vw,var(--nx-heading-size))!important}'.$scope.' h2{font-size:calc(var(--nx-heading-size)*.7)}'.$scope.' h3{font-size:calc(var(--nx-heading-size)*.52)}@media(max-width:700px){.navbar{flex-wrap:wrap;gap:16px;padding:18px!important}.navbar .nav-links{width:100%;min-width:0;flex-wrap:wrap;gap:12px!important}.navbar .search-box{flex:1;min-width:120px}.navbar .search-box input{max-width:100%;width:100%}.main-container{padding:20px 12px!important}}.nx-public .nx-language-switch{font-family:var(--nx-font);display:flex;flex-wrap:wrap}.nx-public .nx-language-switch button{border:1px solid #56606d;border-radius:6px;background:#191e25;color:#f4f5ed;cursor:pointer}</style>';
    }
}
