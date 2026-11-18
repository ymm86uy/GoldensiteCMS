<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

use App\Core\UploadGuard;

final class Audio
{
    private const FORMATS=['audio/mpeg'=>'mp3','audio/ogg'=>'ogg','audio/wav'=>'wav','audio/x-wav'=>'wav'];

    public static function store(): array
    {
        $file=$_FILES['audio']??null;
        if ($file===null || ($file['error']??null)===UPLOAD_ERR_NO_FILE) return [null,null];
        $temp=$file['tmp_name']??null;
        if (($file['error']??null)!==UPLOAD_ERR_OK || !is_string($temp) || !is_uploaded_file($temp) || filesize($temp)>8388608)
            throw new \InvalidArgumentException('El audio admite hasta 8 MB.');
        $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($temp);
        if (!isset(self::FORMATS[$mime])) throw new \InvalidArgumentException('Usa MP3, Ogg o WAV.');
        $key=bin2hex(random_bytes(16));
        if (!UploadGuard::move($temp,Store::newMediaPath($key))) throw new \InvalidArgumentException('No se pudo guardar el audio.');
        return [$key,$mime];
    }

    public function stream(string $id): void
    {
        if (!Access::enter()) return;
        if (!preg_match('/^[1-9][0-9]{0,17}$/D',$id)) { Access::json(['error'=>'Audio no disponible.'],404); return; }
        try { $echo=Store::visible((int)$id); }
        catch (\OutOfBoundsException $error) { Access::json(['error'=>$error->getMessage()],404); return; }
        $key=$echo['audio_key'];
        $mime=$echo['audio_mime'];
        if (!$key || !isset(self::FORMATS[$mime]) || ($path=Store::mediaPath($key))===null) { Access::json(['error'=>'Audio no disponible.'],404); return; }
        header('Content-Type: '.$mime);
        header('Content-Disposition: inline; filename="eco.'.self::FORMATS[$mime].'"');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Length: '.filesize($path));
        readfile($path);
    }
}

