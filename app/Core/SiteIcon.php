<?php
namespace App\Core;
final class SiteIcon
{
    public static function head(): string { $url=Installer::installed()?Settings::get('site_icon','/assets/admin/goldensite-mark.svg'):'/assets/admin/goldensite-mark.svg'; if (!preg_match('~^/assets/(?:site-icons/[a-f0-9]{32}\.png|admin/goldensite-mark\.svg)$~D',$url)) $url='/assets/admin/goldensite-mark.svg'; return '<link rel="stylesheet" href="/assets/navigation.css?v=20261105"><link id="goldensite-site-icon" rel="icon" href="'.ConsoleView::escape($url).'" type="'.(str_ends_with($url,'.png')?'image/png':'image/svg+xml').'"><link rel="apple-touch-icon" href="'.ConsoleView::escape($url).'">'; }
    public static function upload(array $file): string {
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_uploaded_file($file['tmp_name']??'')||filesize($file['tmp_name'])>2097152) throw new \InvalidArgumentException('Selecciona un PNG, JPEG o WebP de hasta 2 MB.');
        $info=@getimagesize($file['tmp_name']); if (!$info||!in_array($info[2],[IMAGETYPE_PNG,IMAGETYPE_JPEG,IMAGETYPE_WEBP],true)||$info[0]<32||$info[1]<32||$info[0]>2048||$info[1]>2048) throw new \InvalidArgumentException('El icono debe medir entre 32 y 2048 píxeles por lado.');
        $image=match($info[2]) { IMAGETYPE_PNG=>@imagecreatefrompng($file['tmp_name']),IMAGETYPE_JPEG=>@imagecreatefromjpeg($file['tmp_name']),default=>@imagecreatefromwebp($file['tmp_name']) }; if (!$image) throw new \InvalidArgumentException('No se pudo leer la imagen.');
        $dir=dirname(__DIR__,2).'/public/assets/site-icons'; if (!is_dir($dir)&&!mkdir($dir,0755,true)) throw new \RuntimeException('No se pudo preparar el almacén de iconos.');
        $target=imagecreatetruecolor(256,256); imagealphablending($target,false); imagesavealpha($target,true); imagefill($target,0,0,imagecolorallocatealpha($target,0,0,0,127)); $side=min($info[0],$info[1]); imagecopyresampled($target,$image,0,0,(int)(($info[0]-$side)/2),(int)(($info[1]-$side)/2),256,256,$side,$side);
        $url='/assets/site-icons/'.bin2hex(random_bytes(16)).'.png'; try { if (!imagepng($target,dirname(__DIR__,2).'/public'.$url)) throw new \RuntimeException('No se pudo guardar el icono.'); } finally { imagedestroy($target); imagedestroy($image); }
        return $url;
    }
}
