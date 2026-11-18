<?php
namespace App\Controllers\Admin;
final class WebhooksController
{
    public static function dispatch(string $event,string $message): void { try { \App\Core\WebhookEngine::enqueue($event,$message); } catch (\Throwable $e) { error_log('Goldensite webhook event could not be queued.'); } }
    public function index(): void { (new WebhookConsoleController())->index(); }
}
