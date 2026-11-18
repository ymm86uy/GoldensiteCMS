<?php
namespace App\Controllers;
use App\Core\{Auth,Csrf,FrontendEngine,LicenseService,RateLimiter,RequestGuard};
use Plugins\GoldensiteChat\ChatEngine;
final class ChatController
{
    public function index(): void
    {
        header('Cache-Control: no-store, private');
        if (!Auth::check()) {
            header('Location: /login?return_to=%2Fchat');
            return;
        }
        if (!FrontendEngine::isModuleActive('live-chat')) { $this->unavailable('El chat está desactivado.'); return; }
        $rooms = array_values(array_filter(ChatEngine::getRooms(), static fn($room) => ChatEngine::canAccess($room['slug'])));
        $roomBySlug = array_column($rooms, null, 'slug');
        $channel = $_GET['channel'] ?? (isset($roomBySlug['general']) ? 'general' : ($rooms[0]['slug'] ?? ''));
        if (!is_string($channel) || !isset($roomBySlug[$channel])) {
            $this->unavailable(!$rooms && !isset($_GET['channel']) ? 'Tu cuenta todavía no tiene salas disponibles.' : 'Esta sala no está disponible para tu cuenta.');
            return;
        }
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RequestGuard::post();
            if (!RateLimiter::consume('chat:' . Auth::id(), 20, 60)) { http_response_code(429); exit('Espera antes de enviar más mensajes.'); }
            $message = $_POST['message'] ?? '';
            $result = ChatEngine::processMessage('', is_string($message) ? $message : '', $channel);
            $notice = $result['msg'];
        }
        $messages = ChatEngine::getMessages($channel);
        $e = static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
        $currentRoom = $roomBySlug[$channel];
        ob_start(); require dirname(__DIR__,2).'/views/frontend/chat.php';
        $content = ob_get_clean(); $siteSettings = FrontendEngine::getSettings(); $siteTitle = 'Chat de la comunidad'; require dirname(__DIR__, 2) . '/views/layouts/main.php';
    }
    private function unavailable(string $message): void
    {
        http_response_code(403);
        $e=\App\Core\ConsoleView::escape(...);
        $siteTitle='Chat no disponible · Goldensite';
        ob_start(); require dirname(__DIR__,2).'/views/frontend/chat_unavailable.php';
        $content=ob_get_clean(); require dirname(__DIR__,2).'/views/layouts/main.php';
    }
}
