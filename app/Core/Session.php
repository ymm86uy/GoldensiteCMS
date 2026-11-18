<?php

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.upload_progress.enabled', '0');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || str_starts_with(strtolower(Environment::get('APP_URL')), 'https://'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
