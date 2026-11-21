<?php
namespace App\Core;

final class CompositionStudio
{
    public const SCHEMA='goldensite-site-1';
    public static function defaults(): array
    {
        return ['schema'=>self::SCHEMA,'preset'=>'agency','font'=>'system','accent'=>'#b7f75a',
            'name'=>mb_substr(Settings::get('site_title','Tu estudio'),0,100),'eyebrow'=>'IDEAS CON DIRECCIÓN',
            'headline'=>'Tu visión. Nuestro próximo gran proyecto.','description'=>'Presenta lo que haces con claridad, personalidad y una experiencia que invite a dar el siguiente paso.',
            'cta'=>'Hablemos de tu proyecto','url'=>'/contacto','image'=>'','image_alt'=>'','logo'=>'',
            'services_title'=>'Lo que podemos hacer juntos', 'about_title'=>'Una forma diferente de hacer las cosas.',
            'about_text'=>'Cuenta quién eres, cómo trabajas y qué hace especial a tu equipo. Este espacio es para tu historia.',
            'contact_title'=>'El próximo paso empieza con una conversación.','contact_text'=>'Cuéntanos qué tienes en mente. Encontraremos juntos la mejor forma de hacerlo realidad.',
            'service_1_title'=>'Estrategia','service_1_text'=>'Define un rumbo claro para tu siguiente etapa.',
            'service_2_title'=>'Diseño','service_2_text'=>'Convierte tus ideas en una experiencia con identidad.',
            'service_3_title'=>'Desarrollo','service_3_text'=>'Haz realidad un proyecto preparado para crecer.',
            'sections'=>['services','about','contact'],'form_id'=>'0'];
    }

    public static function guided(array $input): array
    {
        if (($input['schema']??null)!==self::SCHEMA) throw new \InvalidArgumentException('Formato de composición desconocido.');
        $data=['schema'=>self::SCHEMA];
        foreach (['preset'=>20,'font'=>30,'accent'=>7,'name'=>100,'eyebrow'=>80,'headline'=>200,'description'=>1500,'cta'=>60,'url'=>255,
            'image'=>255,'image_alt'=>200,'logo'=>255,'services_title'=>150,'about_title'=>180,'about_text'=>1800,'contact_title'=>180,'contact_text'=>1000,
            'service_1_title'=>100,'service_1_text'=>500,'service_2_title'=>100,'service_2_text'=>500,'service_3_title'=>100,'service_3_text'=>500,'form_id'=>10] as $key=>$max) {
            $value=$input[$key]??'';
            $multiline=in_array($key,['description','about_text','contact_text','service_1_text','service_2_text','service_3_text'],true);
            if (!is_string($value) || strlen($value)>$max*4 || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>$max || preg_match($multiline?'/[\x00-\x08\x0b\x0c\x0e-\x1f]/':'/[\x00-\x1f]/',$value)) throw new \InvalidArgumentException('Revisa el campo '.$key.'.');
            $data[$key]=trim($value);
            if (preg_match('/[<>]/',$data[$key])) throw new \InvalidArgumentException('Usa texto sin etiquetas en los campos del sitio.');
        }
        if (!in_array($data['preset'],['agency','consulting'],true) || !isset(Typography::fonts()[$data['font']]) || !preg_match('/^#[a-f0-9]{6}$/iD',$data['accent'])
            || $data['name']==='' || $data['headline']==='' || $data['cta']==='' || !Navigation::safeUrl($data['url'])) throw new \InvalidArgumentException('Revisa el diseño, la marca, el título y el enlace de contacto.');
        $sections=$input['sections']??[];
        if (!is_array($sections) || count($sections)>3 || array_filter($sections,static fn($v)=>!is_string($v)||!in_array($v,['services','about','contact'],true)) || count(array_unique($sections))!==count($sections)) throw new \InvalidArgumentException('Selecciona secciones válidas sin repetirlas.');
        $data['sections']=array_values($sections);
        foreach (['services'=>'services_title','about'=>'about_title','contact'=>'contact_title'] as $section=>$key) if (in_array($section,$sections,true) && $data[$key]==='') throw new \InvalidArgumentException('Las secciones activas necesitan un título.');
        if ($data['image']!=='') { self::imagePath($data['image']); if ($data['image_alt']==='') throw new \InvalidArgumentException('Describe la imagen para quienes no pueden verla.'); }
        if ($data['logo']!=='') self::imagePath($data['logo']);
        if (!ctype_digit($data['form_id'])) throw new \InvalidArgumentException('Formulario inválido.');
        if ((int)$data['form_id']>0 && (!FrontendEngine::isModuleActive('form-builder') || !Database::getInstance()->fetch('SELECT id FROM goldensite_forms WHERE id=? AND is_active=1',[(int)$data['form_id']]))) throw new \InvalidArgumentException('Activa Form Builder y selecciona un formulario disponible.');
        return $data;
    }

    public static function imagePath(string $url): string
    {
        if (!preg_match('~^/uploads/[A-Za-z0-9_./% -]+\.(?:jpe?g|png|webp|gif)$~iD',$url)) throw new \InvalidArgumentException('Selecciona una imagen de la biblioteca local.');
        $path=AssetLibrary::path(rawurldecode(substr($url,9)));
        if (filesize($path)>5242880 || !@getimagesize($path)) throw new \InvalidArgumentException('Selecciona una imagen local válida de hasta 5 MB.');
        return $path;
    }

    public static function publishedTypography(): ?array
    {
        $saved=json_decode(Settings::get('builder_published_spec','{}'),true);
        if (Settings::get('use_ai_frontend')!=='1' || !is_array($saved) || ($saved['schema']??null)!==self::SCHEMA
            || !is_string($saved['html_sha256']??null) || !hash_equals($saved['html_sha256'],hash('sha256',Settings::get('custom_ai_frontend')))
            || !isset(Typography::fonts()[$saved['font']??''])) return null;
        return ['typography_json'=>json_encode(['font'=>$saved['font'],'size'=>16,'heading'=>80],JSON_THROW_ON_ERROR)];
    }

    public static function snapshot(string $json,string $html): array
    {
        if (strlen($json)>65536) return [];
        $data=json_decode($json,true,12);
        return is_array($data) && ($data['schema']??null)===self::SCHEMA && is_string($data['html_sha256']??null)
            && hash_equals($data['html_sha256'],hash('sha256',$html))?$data:[];
    }

    public static function validate(array $input): array
    {
        if (isset($input['schema'])) return self::guided($input);
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
        if (($data['schema']??null)===self::SCHEMA) {
            $data=self::guided($data);
            $e=ConsoleView::escape(...);
            ob_start(); require dirname(__DIR__,2).'/views/frontend/studio-site.php'; return (string)ob_get_clean();
        }
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
