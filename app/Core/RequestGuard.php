<?php

namespace App\Core;

final class RequestGuard
{
    public static function csrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? null;
        if (!Csrf::validate($token)) {
            http_response_code(403);
            exit('Token de seguridad inválido o expirado.');
        }
    }

    public static function post(): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
            http_response_code(405);
            header('Allow: POST');
            exit('Método no permitido.');
        }
        self::csrf();
    }
}
