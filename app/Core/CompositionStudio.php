<?php
namespace App\Core;

final class CompositionStudio
{
    public static function validate(array $input): array
    {
        $data = [];
        foreach (['name'=>100,'headline'=>200,'eyebrow'=>80,'cta'=>60,'url'=>255,'secondary'=>60,'secondary_url'=>255,'template'=>20] as $key=>$max) {
            $data[$key] = ConsoleView::field($input,$key,$max);
        }
        foreach (['description'=>1500,'benefits'=>600] as $key=>$max) {
            $value = $input[$key]??'';
            if (!is_string($value) || strlen($value)>$max || !mb_check_encoding($value,'UTF-8')
                || preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f]/',$value)) {
                throw new \InvalidArgumentException('Revisa la descripción y las ventajas del sitio.');
            }
            $data[$key] = trim($value);
        }
        if ($data['name']==='' || $data['headline']==='' || $data['cta']===''
            || !in_array($data['template'],['studio','editorial','product','theme'],true) || !Navigation::safeUrl($data['url'])) {
            throw new \InvalidArgumentException('Completa el nombre, el título, el estilo y un enlace válido para la acción principal.');
        }
        if (($data['secondary']!=='' || $data['secondary_url']!=='') && ($data['secondary']==='' || !Navigation::safeUrl($data['secondary_url']))) {
            throw new \InvalidArgumentException('La acción secundaria necesita un texto y una ruta del sitio o URL HTTPS.');
        }
        $benefits = array_values(array_filter(array_map('trim',preg_split('/\R/u',$data['benefits'])),static fn($value)=>$value!==''));
        if (count($benefits)>3 || array_filter($benefits,static fn($value)=>mb_strlen($value)>100)) {
            throw new \InvalidArgumentException('Escribe hasta tres ventajas de hasta 100 caracteres, una por línea.');
        }
        return $data;
    }

    public static function generate(array $data): string
    {
        $e = ConsoleView::escape(...);
        $preset = $data['template'];
        $palettes = [
            'studio'=>['#101317','#c5ef69','#f4f5f0','#171d25','system-ui,sans-serif'],
            'editorial'=>['#f3efe7','#cc502d','#202022','#fffcf4','Georgia,serif'],
            'product'=>['#0c101f','#a89cff','#f4f5f0','#191d32','system-ui,sans-serif'],
        ];
        if ($preset==='theme') {
            $theme = ThemeService::context();
            [$bg,$accent,$fg,$surface,$font] = [$theme['bgColor'],$theme['primaryColor'],$theme['textColor'],$theme['cardBg'],$theme['fontFamily']];
        } else {
            [$bg,$accent,$fg,$surface,$font] = $palettes[$preset];
        }
        $accentInk = AdminTheme::contrast($accent);
        $title = $e($data['name']);
        $headline = $e($data['headline']);
        $description = nl2br($e($data['description']));
        $seo = $e(mb_substr(preg_replace('/\s+/u',' ',$data['description'])??'',0,160));
        $eyebrow = $e($data['eyebrow']?:$data['name']);
        $primary = '<a class="cta" href="'.$e($data['url']).'">'.$e($data['cta']).'</a>';
        $secondary = $data['secondary']===''?'':'<a class="cta secondary" href="'.$e($data['secondary_url']).'">'.$e($data['secondary']).'</a>';
        $items = '';
        foreach (array_values(array_filter(array_map('trim',preg_split('/\R/u',$data['benefits'])),static fn($value)=>$value!=='')) as $index=>$value) {
            $items .= '<li><span>'.sprintf('%02d',$index+1).'</span>'.$e($value).'</li>';
        }
        $features = $items===''?'':'<section class="features" aria-labelledby="features-title"><h2 id="features-title">Lo que ofrecemos</h2><ul>'.$items.'</ul></section>';
        $lang = $e(Locale::language());
        $year = date('Y');
        return <<<HTML
<!doctype html>
<html lang="$lang">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="description" content="$seo">
<title>$title</title>
<style>
*{box-sizing:border-box}
body{margin:0;background:$bg;color:$fg;font-family:$font;line-height:1.55}
a{color:inherit}
a:focus-visible{outline:3px solid $accent;outline-offset:4px}
.frame{width:min(100% - 3rem,72rem);margin:auto}
header{border-bottom:1px solid #8885}
.brandbar{min-height:76px;display:flex;align-items:center;justify-content:space-between;gap:1rem}
.brand{font-weight:700;text-decoration:none}
.hero{display:grid;grid-template-columns:1.35fr 1fr;align-items:center;gap:clamp(2rem,6vw,6rem);padding-block:clamp(3.5rem,8vw,7rem)}
.eyebrow{font:700 .75rem system-ui;letter-spacing:.16em;text-transform:uppercase;color:$accent}
h1{font-size:clamp(2.75rem,7vw,6.4rem);line-height:1;letter-spacing:-.045em;margin:.6em 0 .4em;overflow-wrap:anywhere}
.copy{font-size:clamp(1rem,1.8vw,1.25rem);max-width:48ch}
.actions{display:flex;flex-wrap:wrap;gap:.75rem;margin-top:2rem}
.cta{display:inline-block;padding:.85rem 1.15rem;background:$accent;color:$accentInk;border:1px solid $accent;border-radius:.55rem;text-decoration:none;font-weight:700}
.cta.secondary{background:transparent;color:$fg}
.art{aspect-ratio:1;border-radius:42% 8% 38% 6%;background:$accent;position:relative;overflow:hidden}
.art:before,.art:after{content:'';position:absolute;inset:12%;border:1px solid #1118;border-radius:50%}
.art:after{inset:27%;border-radius:4px;transform:rotate(30deg)}
.editorial .hero{grid-template-columns:minmax(0,1fr);border-bottom:1px solid #8885;text-align:center}
.editorial .hero .art{display:none}
.editorial .copy{margin-inline:auto}
.editorial .actions{justify-content:center}
.product .art{border-radius:1.5rem;transform:rotate(-4deg);box-shadow:0 1rem 3rem #0005}
.features{padding-bottom:3rem}
.features ul{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,14rem),1fr));gap:1rem;padding:0;list-style:none}
.features li{padding:1.25rem;border-radius:.65rem;background:$surface;border:1px solid #8885;overflow-wrap:anywhere}
.features li span{display:block;color:$accent;font:700 .8rem system-ui;margin-bottom:.5rem}
footer{border-top:1px solid #8885;padding-block:1.5rem}
@media(max-width:700px){.hero{grid-template-columns:1fr}.art{max-width:270px}}
</style>
</head>
<body class="$preset">
<header><div class="frame brandbar"><a class="brand" href="/">$title</a><a href="/contacto">Contacto</a></div></header>
<main class="frame">
<section class="hero"><div><p class="eyebrow">$eyebrow</p><h1>$headline</h1><p class="copy">$description</p><div class="actions">$primary$secondary</div></div><div class="art" aria-hidden="true"></div></section>
$features
</main>
<footer><div class="frame">$title · $year</div></footer>
</body>
</html>
HTML;
    }
}
