<?php
namespace App\Core;
final class LoginAppearance
{
    public const FREE_STYLES = ['glass_neon', 'split_national'];
    public const MOTION = [
        'kinetic_gallery' => ['Galería cinética', 'Tres paneles en perspectiva con una entrada escalonada.', '#6ee7c1'],
        'orbital_glass' => ['Vidrio orbital', 'Un disco de vidrio gira y revela un halo de luz.', '#84ccff'],
        'aurora_mesh' => ['Aurora prismática', 'Capas cromáticas que se abren sobre una retícula.', '#e3b6ff'],
        'owner_atlas' => ['Atlas Owner', 'Estudio oscuro con un relieve dorado y líneas de contorno.', '#f6d78e'],
    ];
    public const STYLES = ['glass_neon', 'split_national', 'nature_mist', 'cyberpunk_glow', 'minimal_clean', 'dark_wallet', 'gradient_sunset', 'auth_centered_badge',
        'kinetic_gallery', 'orbital_glass', 'aurora_mesh', 'owner_atlas'];

    public static function defaultStyle(): string
    {
        return ProductEdition::current() === 'owner' ? 'owner_atlas' : 'glass_neon';
    }

    public static function available(string $style): bool
    {
        return in_array($style, self::STYLES, true)
            && ($style !== 'owner_atlas' || ProductEdition::current() === 'owner');
    }

    public static function media(string $url): bool
    {
        if ($url === '') return true;
        return strlen($url) <= 2048 && !preg_match('/[\x00-\x20<>"\'\\\\]/', $url)
            && (str_starts_with($url, '/uploads/') || (str_starts_with($url, 'https://') && filter_var($url, FILTER_VALIDATE_URL)));
    }
}
