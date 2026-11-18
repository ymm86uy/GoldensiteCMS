<?php
namespace App\Core;

final class HomePage
{
    public const SETTING = 'theme_homepage';
    public const DEFAULTS = [
        'eyebrow' => '⚡ Plataforma Web Empresarial',
        'headline' => 'Construye Experiencias Digitales',
        'highlight' => 'Escalables y Seguras',
        'description' => 'Infraestructura modular optimizada con velocidad ultrarrápida, protección WAF proactiva e integraciones dinámicas en tiempo real.',
        'primary_label' => 'Iniciar Proyecto',
        'primary_link' => '/contacto',
        'secondary_label' => 'Preguntas Frecuentes',
        'secondary_link' => '/faq',
        'services_title' => 'Servicios Destacados',
        'services_description' => 'Módulos avanzados diseñados para máxima eficiencia en producción.',
        'layout' => 'center',
        'image' => '',
        'image_alt' => '',
    ];
    private const LIMITS = [
        'eyebrow'=>90, 'headline'=>160, 'highlight'=>160, 'description'=>600,
        'primary_label'=>65, 'primary_link'=>200, 'secondary_label'=>65, 'secondary_link'=>200,
        'services_title'=>120, 'services_description'=>300, 'layout'=>12, 'image_alt'=>200,
    ];

    public static function values(): array
    {
        $saved=json_decode(Settings::get(self::SETTING,'{}'),true);
        $saved=is_array($saved)?array_filter(array_intersect_key($saved,self::DEFAULTS),'is_string'):[];
        $values=$saved+self::DEFAULTS;
        if (!preg_match('~^/uploads/home/[a-f0-9]{32}\.jpg$~D',$values['image'])) $values['image']='';
        foreach (['primary_link','secondary_link'] as $key) {
            if (!self::validLink($values[$key])) $values[$key]='';
        }
        return $values;
    }

    public static function save(array $input,array $file): void
    {
        $values=[];
        foreach (self::LIMITS as $key=>$limit) {
            $value=$input[$key]??'';
            if (!is_string($value) || !mb_check_encoding($value,'UTF-8') || mb_strlen($value)>$limit
                || preg_match('/[<>\x00-\x08\x0B\x0C\x0E-\x1F]/',$value)) {
                throw new \InvalidArgumentException('Revisa los textos de la portada y sus límites.');
            }
            $values[$key]=trim($value);
        }
        if ($values['headline']==='' || !in_array($values['layout'],['center','split'],true)) {
            throw new \InvalidArgumentException('Escribe el título y selecciona un diseño válido.');
        }
        foreach (['primary','secondary'] as $button) {
            $link=$values[$button.'_link'];
            if (!self::validLink($link) || (($link==='')!==($values[$button.'_label']===''))) {
                throw new \InvalidArgumentException('Cada botón necesita un texto y una dirección interna que empiece por /.');
            }
        }
        $values['image']=($input['remove_image']??'')==='1'?'':self::values()['image'];
        $newImage=self::upload($file);
        if ($newImage!=='') $values['image']=$newImage;
        try {
            Settings::set(self::SETTING,json_encode($values,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
        } catch (\Throwable $error) {
            if ($newImage!=='') unlink(dirname(__DIR__,2).'/public'.$newImage);
            throw $error;
        }
    }

    private static function validLink(string $link): bool
    {
        return $link==='' || (str_starts_with($link,'/') && !str_starts_with($link,'//')
            && !preg_match('/[\x00-\x20\x7f\\\\]/',$link));
    }

    private static function upload(array $file): string
    {
        $error=$file['error']??UPLOAD_ERR_NO_FILE;
        if ($error===UPLOAD_ERR_NO_FILE) return '';
        $tmp=$file['tmp_name']??null;
        if ($error!==UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) || filesize($tmp)>4194304) {
            throw new \InvalidArgumentException('Selecciona una imagen JPEG, PNG o WebP de hasta 4 MB.');
        }
        $info=@getimagesize($tmp);
        if (!$info || $info[0]*$info[1]>8000000) throw new \InvalidArgumentException('La imagen debe ser válida y tener hasta 8 megapíxeles.');
        $source=match ($info[2]) {
            IMAGETYPE_JPEG=>@imagecreatefromjpeg($tmp),
            IMAGETYPE_PNG=>@imagecreatefrompng($tmp),
            IMAGETYPE_WEBP=>function_exists('imagecreatefromwebp')?@imagecreatefromwebp($tmp):false,
            default=>false,
        };
        if (!$source) throw new \InvalidArgumentException('No se pudo leer la imagen. Usa JPEG, PNG o WebP.');
        $scale=min(1,1600/max($info[0],$info[1]));
        $width=max(1,(int)round($scale*$info[0]));
        $height=max(1,(int)round($scale*$info[1]));
        $image=imagecreatetruecolor($width,$height);
        imagefill($image,0,0,imagecolorallocate($image,255,255,255));
        imagecopyresampled($image,$source,0,0,0,0,$width,$height,$info[0],$info[1]);
        $root=dirname(__DIR__,2).'/public';
        $directory=$root.'/uploads/home';
        if (is_link($directory) || is_link($root.'/uploads') || (!is_dir($directory) && !@mkdir($directory,0755,true) && !is_dir($directory))) {
            throw new \RuntimeException('El almacén de imágenes de la portada no está disponible.');
        }
        $url='/uploads/home/'.bin2hex(random_bytes(16)).'.jpg';
        $path=$root.$url;
        if (!imagejpeg($image,$path,85) || !is_file($path) || filesize($path)===0) {
            if (is_file($path)) unlink($path);
            throw new \RuntimeException('No se pudo guardar la imagen de portada.');
        }
        return $url;
    }
}
