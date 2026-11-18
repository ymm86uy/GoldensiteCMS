<?php

namespace App\Core;

class Uploader
{
    private string $uploadDir;
    private string $urlPrefix;
    private const TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];

    public function __construct(string $targetSubfolder = 'uploads')
    {
        $folder = trim(str_replace('\\', '/', $targetSubfolder), '/');
        if (!preg_match('~^uploads(?:/[A-Za-z0-9_-]+)*$~D', $folder)) {
            throw new \InvalidArgumentException('Carpeta de carga inválida.');
        }
        $public = realpath(dirname(__DIR__, 2) . '/public');
        $this->uploadDir = $public . '/' . $folder;
        // Reject symlinks/junctions leading outside public/uploads.
        $current = $public;
        foreach (explode('/', $folder) as $segment) {
            $current .= '/' . $segment;
            if (is_link($current)) throw new \InvalidArgumentException('Carpeta de carga inválida.');
            if (!is_dir($current) && !mkdir($current, 0755) && !is_dir($current)) throw new \RuntimeException('No se pudo crear la carpeta de carga.');
            $resolved = str_replace('\\', '/', realpath($current));
            if (!str_starts_with($resolved . '/', str_replace('\\', '/', $public) . '/')) throw new \InvalidArgumentException('Carpeta fuera del almacenamiento.');
        }
        $this->urlPrefix = '/' . $folder . '/';
    }

    public function upload(array $file): ?string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null) || !is_uploaded_file($file['tmp_name'])) return null;
        $size = filesize($file['tmp_name']);
        if ($size === false || $size < 1 || $size > 5242880) return null;
        $extension = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$extension])) return null;
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if ($mime !== self::TYPES[$extension]) return null;
        if (str_starts_with($mime, 'image/')) {
            $image = @getimagesize($file['tmp_name']);
            if (!$image || $image[0] > 12000 || $image[1] > 12000 || $image[0] * $image[1] > 40000000) return null;
        }
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        return \App\Core\UploadGuard::move($file['tmp_name'], $this->uploadDir . '/' . $name) ? $this->urlPrefix . $name : null;
    }
}
