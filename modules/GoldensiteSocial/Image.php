<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

final class Image
{
    public static function store(): ?string
    {
        $file=$_FILES['image']??null;
        if ($file===null || ($file['error']??null)===UPLOAD_ERR_NO_FILE) return null;
        $tmp=$file['tmp_name']??null;
        if (($file['error']??null)!==UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) || filesize($tmp)>4194304) {
            throw new \InvalidArgumentException('Usa una imagen de hasta 4 MB.');
        }
        $info=@getimagesize($tmp);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!$info || $info['mime']!==$mime || $info[0]*$info[1]>8000000) {
            throw new \InvalidArgumentException('La imagen no es válida o supera 8 megapíxeles.');
        }
        if (!function_exists('imagewebp') || !(gd_info()['WebP Support']??false)) {
            throw new \InvalidArgumentException('La carga de imágenes necesita GD con soporte WebP.');
        }
        $source=match($mime) {
            'image/jpeg'=>@imagecreatefromjpeg($tmp),
            'image/png'=>@imagecreatefrompng($tmp),
            'image/webp'=>@imagecreatefromwebp($tmp),
            default=>false,
        };
        if (!$source) throw new \InvalidArgumentException('Usa una imagen JPG, PNG o WebP válida.');
        $scale=min(1,1600/max($info[0],$info[1]));
        $width=max(1,(int)round($info[0]*$scale));
        $height=max(1,(int)round($info[1]*$scale));
        $image=imagecreatetruecolor($width,$height);
        imagealphablending($image,false);
        imagesavealpha($image,true);
        imagecopyresampled($image,$source,0,0,0,0,$width,$height,$info[0],$info[1]);
        $key=bin2hex(random_bytes(16));
        $path=Store::newMediaPath($key);
        $handle=fopen($path,'xb');
        if (!$handle) throw new \RuntimeException('No se pudo preparar la imagen.');
        try {
            $saved=imagewebp($image,$handle,84);
        } catch (\Throwable $error) {
            fclose($handle);
            Store::removeMedia($key);
            throw $error;
        } finally {
            if (is_resource($handle)) fclose($handle);
        }
        if (!$saved || !filesize($path)) {
            Store::removeMedia($key);
            throw new \RuntimeException('No se pudo guardar la imagen.');
        }
        return $key;
    }

    public function stream(string $id): void
    {
        if (!Access::enter()) return;
        $path=null;
        if (preg_match('/^[1-9][0-9]{0,17}$/D',$id)) {
            try {
                $key=Store::visible((int)$id)['image_key'];
                if (is_string($key)) $path=Store::mediaPath($key);
            } catch (\OutOfBoundsException) {}
        }
        if ($path===null) { Access::json(['error'=>'Imagen no disponible.'],404); return; }
        header('Content-Type: image/webp');
        header('Content-Disposition: inline; filename="eco.webp"');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Cross-Origin-Resource-Policy: same-origin');
        header('Content-Length: '.filesize($path));
        readfile($path);
    }
}
