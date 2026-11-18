<?php
namespace App\Core;

class QrEncoder
{
    public static function getSvg(string $data, int $size = 250): string
    {
        if ($data === '' || strlen($data) > 2000) throw new \InvalidArgumentException('Contenido QR vacío o demasiado largo.');
        $size = max(100, min($size, 1200));
        $options = new \chillerlan\QRCode\QROptions(['outputBase64' => false, 'svgAddXmlHeader' => false, 'scale' => 5]);
        $svg = (new \chillerlan\QRCode\QRCode($options))->render($data);
        return preg_replace('/<svg\b/', '<svg width="' . $size . '" height="' . $size . '"', $svg, 1);
    }
}
