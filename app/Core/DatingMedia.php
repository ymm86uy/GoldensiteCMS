<?php
namespace App\Core;
final class DatingMedia
{
    public static function path(string $id): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new \InvalidArgumentException('Imagen inválida.');
        return dirname(__DIR__,2).'/storage/dating/media/'.$id.'.jpg';
    }
    public static function upload(array $file,int $profile): string
    {
        $me=DatingService::mine(); if (!$me||(int)$me['id']!==$profile||!in_array($me['account_status'],['active','paused'],true)||!DatingService::verified($me)) throw new \InvalidArgumentException('Confirma tu correo antes de subir fotos.');
        if (!RateLimiter::consume('dating-photo:'.Auth::id(),10,86400)) throw new \InvalidArgumentException('Has alcanzado el límite diario de fotos.');
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($file['tmp_name']??null)||!is_uploaded_file($file['tmp_name'])||filesize($file['tmp_name'])>5242880) throw new \InvalidArgumentException('Sube una imagen JPEG, PNG o WebP de hasta 5 MB.');
        $info=@getimagesize($file['tmp_name']);
        if (!$info||!in_array($info[2],[IMAGETYPE_JPEG,IMAGETYPE_PNG,IMAGETYPE_WEBP],true)||$info[0]*$info[1]>12000000) throw new \InvalidArgumentException('Imagen no admitida o superior a 12 millones de píxeles.');
        $image=match($info[2]) { IMAGETYPE_JPEG=>@imagecreatefromjpeg($file['tmp_name']),IMAGETYPE_PNG=>@imagecreatefrompng($file['tmp_name']),default=>@imagecreatefromwebp($file['tmp_name']) };
        if (!$image) throw new \InvalidArgumentException('No se pudo procesar la imagen.');
        $id=bin2hex(random_bytes(16)); $path=self::path($id); $dir=dirname($path); if (!is_dir($dir)&&!mkdir($dir,0700,true)) { imagedestroy($image); throw new \RuntimeException('Almacenamiento no disponible.'); }
        $ratio=min(1,1600/max($info[0],$info[1])); $w=max(1,(int)round($info[0]*$ratio)); $h=max(1,(int)round($info[1]*$ratio)); $clean=imagecreatetruecolor($w,$h); imagefill($clean,0,0,imagecolorallocate($clean,255,255,255)); imagecopyresampled($clean,$image,0,0,0,0,$w,$h,$info[0],$info[1]); imagedestroy($image);
        try { $stream=fopen($path,'xb'); if (!$stream) throw new \RuntimeException('No se pudo guardar la imagen.'); try { if (!imagejpeg($clean,$stream,86)) throw new \RuntimeException('No se pudo codificar la imagen.'); } finally { fclose($stream); } @chmod($path,0600); }
        finally { imagedestroy($clean); }
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$profile]);
            if ((int)$db->fetch("SELECT COUNT(*) n FROM goldensite_dating_photos WHERE profile_id=? AND status IN ('pending','approved')",[$profile])['n']>=6) throw new \InvalidArgumentException('El álbum admite hasta seis fotos. Retira una antes de subir otra.');
            $db->execute('INSERT INTO goldensite_dating_photos (id,profile_id) VALUES (?,?)',[$id,$profile]); $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); @unlink($path); throw $e; }
        return $id;
    }
    public static function remove(string $id,int $profile): void
    {
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->fetch('SELECT id FROM goldensite_dating_profiles WHERE id=? FOR UPDATE',[$profile]); $row=$db->fetch('SELECT id FROM goldensite_dating_photos WHERE id=? AND profile_id=?',[$id,$profile]); if (!$row) throw new \InvalidArgumentException('Foto no disponible.');
            $db->execute("UPDATE goldensite_dating_photos SET status='deleted' WHERE id=? AND profile_id=?",[$id,$profile]);
            $db->execute('UPDATE goldensite_dating_accounts SET avatar_photo_id=NULL WHERE profile_id=? AND avatar_photo_id=?',[$profile,$id]); $db->execute('UPDATE goldensite_dating_stories SET is_hidden=1 WHERE photo_id=?',[$id]); $pdo->commit();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
        $path=self::path($id); if (is_file($path)) @unlink($path);
    }
    public static function allowed(string $id,bool $review=false): ?array
    {
        if (!Auth::check()) return null; $row=Database::getInstance()->fetch("SELECT * FROM goldensite_dating_photos WHERE id=? AND status<>'deleted'",[$id]); if (!$row) return null;
        $me=DatingService::mine(); if ($me&&(int)$me['id']===(int)$row['profile_id']&&$me['account_status']!=='deleted') return $row;
        if ($review&&IdentityAccess::superuser()) { ConsoleView::audit('dating_private_photo_reviewed',$id); return $row; }
        if (!$me||!DatingService::active($me)||$row['status']!=='approved'||DatingService::blocked($me['id'],$row['profile_id'])) return null;
        $peer=DatingService::member((int)$row['profile_id']); if (!$peer||!DatingService::active($peer)) return null;
        $low=min((int)$me['id'],(int)$peer['id']); $high=max((int)$me['id'],(int)$peer['id']);
        if (Database::getInstance()->fetch("SELECT id FROM goldensite_dating_relationships WHERE low_profile=? AND high_profile=? AND status='active'",[$low,$high])) return $row;
        return DatingService::active($me,true)&&DatingService::active($peer,true)?$row:null;
    }
}
