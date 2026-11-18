<?php

namespace App\Core;

class Csrf
{
    public static function generate(): string
    {
        Session::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function validate($token): bool
    {
        Session::start();
        if (!is_string($token) || $token === '' || !is_string($_SESSION['csrf_token'] ?? null)) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
