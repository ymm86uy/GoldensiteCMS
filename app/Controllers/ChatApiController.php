<?php

namespace App\Controllers;

use App\Core\Database;

class ChatApiController
{
    public function getMessages(): void
    {
        \App\Core\Auth::requireAuth();
        if (!\App\Core\FrontendEngine::isModuleActive('live-chat')) { http_response_code(403); exit; }
        header('Cache-Control: no-store');
        header('Content-Type: application/json');
        $channel = is_string($_GET['channel'] ?? null) ? $_GET['channel'] : 'general';

        if (class_exists('\Plugins\GoldensiteChat\ChatEngine')) {
            if (!\Plugins\GoldensiteChat\ChatEngine::canAccess($channel)) { http_response_code(403); echo json_encode(['success' => false]); return; }
            $messages = \Plugins\GoldensiteChat\ChatEngine::getMessages($channel);
            echo json_encode(['success' => true, 'channel' => $channel, 'messages' => $messages]);
            exit;
        }

        echo json_encode(['success' => false, 'messages' => []]);
        exit;
    }

    public function sendMessage(): void
    {
        \App\Core\Auth::requireAuth();
        if (!\App\Core\FrontendEngine::isModuleActive('live-chat')) { http_response_code(403); exit; }
        \App\Core\RequestGuard::post();
        header('Content-Type: application/json');

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true) ?: $_POST;

        $sender  = (string) (\App\Core\Auth::user()['username'] ?? 'Usuario');
        $message = is_string($data['message'] ?? null) ? trim($data['message']) : '';
        $channel = is_string($data['channel'] ?? null) ? trim($data['channel']) : 'general';

        if (empty($message) || mb_strlen($message) > 2000 || !\App\Core\RateLimiter::consume('chat:' . \App\Core\Auth::id(), 20, 60)) {
            echo json_encode(['success' => false, 'msg' => 'El mensaje no puede estar vacío.']);
            exit;
        }

        if (class_exists('\Plugins\GoldensiteChat\ChatEngine')) {
            $res = \Plugins\GoldensiteChat\ChatEngine::inspectAndFilter($sender, $message, $channel);
            echo json_encode($res);
            exit;
        }

        echo json_encode(['success' => false, 'msg' => 'Motor de chat no activo.']);
        exit;
    }
}
