<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum;

use App\Core\{ErrorPage,Settings,Typography};

final class Appearance
{
    public const STYLES = ['theme'=>'Tema del sitio','gold'=>'Dorado','ocean'=>'Océano','forest'=>'Bosque'];
    private const COLORS = ['theme'=>'var(--primary, currentColor)','gold'=>'#d5a849','ocean'=>'#56b8e8','forest'=>'#76bd89'];

    public static function get(): array
    {
        $stored=json_decode(Settings::get('forum_appearance','{}'),true);
        $data=(is_array($stored)?$stored:[])+['name'=>'Goldensite Forum','description'=>'Conversaciones, preguntas y anuncios de la comunidad.',
            'type'=>[],'heading_font'=>'inherit','style'=>'theme','logo'=>null,'cover'=>null,'access'=>'members'];
        if (!isset(self::STYLES[$data['style']])) $data['style']='theme';
        return $data;
    }

    public static function css(array $data): string
    {
        $type=$data['type'];
        $style='--gs-forum-accent:'.self::COLORS[$data['style']].';';
        if ($type) {
            if ($type['font']!=='inherit') $style.='--gs-forum-font:'.Typography::stack($type['font']).';';
            $style.='--gs-forum-size:'.max(12,min(28,(int)$type['size'])).'px;--gs-forum-heading:'.max(24,min(80,(int)$type['heading'])).'px;';
        }
        if ($data['heading_font']!=='inherit') $style.='--gs-forum-heading-font:'.Typography::stack($data['heading_font']).';';
        return '<style>.gs-forum{'.$style.'}</style>';
    }

    public static function save(): void
    {
        $data=self::get();
        $data['name']=Forum::field('forum_name',80);
        $data['description']=Forum::field('forum_description',220,false);
        $font=Forum::field('font',30);
        $size=filter_var($_POST['size']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>12,'max_range'=>28]]);
        $heading=filter_var($_POST['heading']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>24,'max_range'=>80]]);
        if (!isset((['inherit'=>true]+Typography::fonts())[$font]) || $size===false || $heading===false) {
            throw new \InvalidArgumentException('Revisa la fuente y los tamaños del foro.');
        }
        $data['type']=['font'=>$font,'size'=>$size,'heading'=>$heading];
        $data['heading_font']=Forum::field('heading_font',30);
        $data['style']=Forum::field('style',15);
        $data['access']=Forum::field('access',15);
        if (!isset(self::STYLES[$data['style']]) || !isset((['inherit'=>true]+Typography::fonts())[$data['heading_font']])
            || !in_array($data['access'],['members','roles'],true)) throw new \InvalidArgumentException('Revisa el estilo, la tipografía de títulos y el permiso de publicación.');
        $written=[];
        $retired=[];
        try {
            Forum::transaction(static function () use ($data,&$written,&$retired): void {
                $db=Forum::db();
                $db->execute("INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('forum_appearance','{}')");
                $row=$db->fetch("SELECT setting_value FROM settings WHERE setting_key='forum_appearance' FOR UPDATE");
                $previous=json_decode($row['setting_value'],true);
                $next=$data;
                foreach (['logo','cover'] as $kind) {
                    $old=$previous[$kind]??null;
                    $new=self::upload($kind,$written);
                    $next[$kind]=$new ?? ((($_POST['remove_'.$kind]??'')==='1')?null:$old);
                    if ($old && $old!==$next[$kind]) $retired[]=$old;
                }
                Settings::set('forum_appearance',json_encode($next,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE));
            });
        } catch (\Throwable $error) {
            foreach ($written as $id) self::remove($id);
            throw $error;
        }
        foreach ($retired as $id) self::remove($id);
    }

    private static function upload(string $kind,array &$written): ?string
    {
        $file=$_FILES[$kind]??null;
        if ($file===null || ($file['error']??null)===UPLOAD_ERR_NO_FILE) return null;
        $tmp=$file['tmp_name']??null;
        if (($file['error']??null)!==UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) || filesize($tmp)>4194304) throw new \InvalidArgumentException('Usa una imagen de hasta 4 MB.');
        $info=@getimagesize($tmp);
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!$info || $info['mime']!==$mime || $info[0]*$info[1]>8000000) throw new \InvalidArgumentException('La imagen no es válida o supera 8 megapíxeles.');
        $image=match($mime) {
            'image/png'=>@imagecreatefrompng($tmp),
            'image/jpeg'=>@imagecreatefromjpeg($tmp),
            'image/webp'=>@imagecreatefromwebp($tmp),
            default=>false,
        };
        if (!$image) throw new \InvalidArgumentException('Usa una imagen PNG, JPEG o WebP válida.');
        $max=$kind==='logo'?512:1920;
        $scale=min(1,$max/max($info[0],$info[1]));
        $width=max(1,(int)round($info[0]*$scale));
        $height=max(1,(int)round($info[1]*$scale));
        $normalized=imagecreatetruecolor($width,$height);
        imagealphablending($normalized,false);
        imagesavealpha($normalized,true);
        imagecopyresampled($normalized,$image,0,0,0,0,$width,$height,$info[0],$info[1]);
        $dir=\App\Core\ForumDistribution::projectRoot().'/storage/forum';
        if (is_link($dir) || (!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir))) throw new \RuntimeException('No se pudo preparar el almacén del foro.');
        $id=bin2hex(random_bytes(16));
        $path=self::path($id);
        $written[]=$id;
        if (!imagewebp($normalized,$path,86) || !is_file($path) || filesize($path)<1) throw new \RuntimeException('No se pudo generar la imagen del foro.');
        return $id;
    }

    private static function path(string $id): string { return \App\Core\ForumDistribution::projectRoot().'/storage/forum/brand-'.$id.'.webp'; }

    private static function remove(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D',$id) && is_file(self::path($id))) @unlink(self::path($id));
    }

    public function image(string $kind,string $id): void
    {
        if (!Forum::enabled()) return;
        $data=self::get();
        if (!in_array($kind,['logo','cover'],true) || !preg_match('/^[a-f0-9]{32}$/D',$id) || $id!==$data[$kind]
            || !is_file($path=self::path($id)) || is_link($path) || is_link(dirname($path))) { ErrorPage::notFound(); return; }
        header('Content-Type: image/webp');
        header('Content-Length: '.filesize($path));
        readfile($path);
    }
}

