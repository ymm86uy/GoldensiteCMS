<?php
namespace App\Middleware;
final class WafMiddleware
{
    private static bool $inspected = false;
    public static function inspect(): void
    {
        if (PHP_SAPI === 'cli' || self::$inspected) return;
        self::$inspected = true;
        \App\Security\WafGateway::handle();
    }
}
