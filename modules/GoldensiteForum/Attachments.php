<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum;

use App\Core\{ErrorPage,UploadGuard};

final class Attachments
{
    private const TYPES = ['image/jpeg','image/png','image/webp','application/pdf'];

    private static function directory(): string { return \App\Core\ForumDistribution::projectRoot().'/storage/forum'; }

    public static function store(int $topic, ?int $reply, array &$written): void
    {
        $file = $_FILES['attachment'] ?? null;
        if ($file === null || ($file['error']??null) === UPLOAD_ERR_NO_FILE) return;
        if (!is_array($file) || !is_string($file['name']??null)) throw new \InvalidArgumentException('Selecciona un solo adjunto.');
        $tmp = $file['tmp_name']??null;
        if (($file['error']??null)!==UPLOAD_ERR_OK || !is_string($tmp) || !is_uploaded_file($tmp) || filesize($tmp)>5242880) {
            throw new \InvalidArgumentException('El adjunto debe pesar como máximo 5 MB.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if (!in_array($mime,self::TYPES,true)) throw new \InvalidArgumentException('Se admiten imágenes JPEG, PNG y WebP, y archivos PDF.');
        if ($mime !== 'application/pdf') {
            $image = @getimagesize($tmp);
            if (!$image || $image['mime']!==$mime || $image[0]*$image[1]>16000000) throw new \InvalidArgumentException('La imagen es inválida o supera los 16 megapíxeles.');
        }
        $dir = self::directory();
        if (!is_dir($dir) && !mkdir($dir,0700) && !is_dir($dir)) throw new \RuntimeException('No se pudo preparar el almacén.');
        $id = bin2hex(random_bytes(16));
        $path = $dir.'/'.$id;
        if (!UploadGuard::move($tmp,$path)) throw new \InvalidArgumentException('No se pudo guardar el adjunto.');
        $written[] = $path;
        $label = basename(str_replace('\\','/',(string)($file['name']??'')));
        $label = mb_check_encoding($label,'UTF-8') ? mb_substr(preg_replace('/[\x00-\x1f\x7f]/','',$label),0,120) : 'Adjunto';
        Forum::db()->execute('INSERT INTO goldensite_forum_attachments (id,topic_id,reply_id,mime,label) VALUES (?,?,?,?,?)', [$id,$topic,$reply,$mime,$label]);
    }

    public static function listing(int $topic, array $replies): array
    {
        $ids = array_column($replies,'id');
        $filter = $ids ? ' OR reply_id IN ('.implode(',',array_fill(0,count($ids),'?')).')' : '';
        $rows = Forum::db()->fetchAll('SELECT id,reply_id,label,mime FROM goldensite_forum_attachments WHERE topic_id=? AND (reply_id IS NULL'.$filter.')', [$topic,...$ids]);
        $result = [];
        foreach ($rows as $row) $result[(int)$row['reply_id']][] = $row;
        return $result;
    }

    public function download(string $id): void
    {
        if (!Forum::enabled()) return;
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) { ErrorPage::notFound(); return; }
        $file = Forum::db()->fetch('SELECT a.mime FROM goldensite_forum_attachments a
            JOIN goldensite_forum_topics t ON t.id=a.topic_id AND t.deleted_at IS NULL
            LEFT JOIN goldensite_forum_replies r ON r.id=a.reply_id
            WHERE a.id=? AND (a.reply_id IS NULL OR (r.topic_id=t.id AND r.deleted_at IS NULL))', [$id]);
        $path = self::directory().'/'.$id;
        if (!$file || !in_array($file['mime'],self::TYPES,true) || !is_file($path) || is_link($path) || is_link(self::directory())) {
            ErrorPage::notFound(); return;
        }
        $extension = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp','application/pdf'=>'pdf'][$file['mime']];
        header('Content-Type: '.$file['mime']);
        header('Content-Disposition: '.($extension==='pdf'?'attachment':'inline').'; filename="adjunto.'.$extension.'"');
        header('X-Content-Type-Options: nosniff');
        header("Content-Security-Policy: default-src 'none'; sandbox");
        header('Content-Length: '.filesize($path));
        readfile($path);
    }
}

