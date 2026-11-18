<?php

namespace App\Core;

use App\Core\Database;

class AnalyticsTracker
{
    public static function track(): void
    {
        try {
            $uri = substr((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), 0, 255);
            
            // No registrar peticiones del panel admin o recursos estáticos para no ensuciar la métrica
            if (str_starts_with($uri, '/admin') || preg_match('/\.(js|css|jpg|jpeg|png|gif|ico|svg|woff2?)$/i', $uri)) {
                return;
            }

            // Aggregate page counts only; no raw IP, query string or user agent.
            $ip = ''; $ua = '';

            $db = Database::getInstance()->getConnection();
            
            // Asegurar que la tabla exista
            $db->exec("CREATE TABLE IF NOT EXISTS goldensite_analytics_hits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                page_url VARCHAR(255) NOT NULL,
                ip_address VARCHAR(45) NOT NULL,
                user_agent VARCHAR(255) DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

            $stmt = $db->prepare("INSERT INTO goldensite_analytics_hits (page_url, ip_address, user_agent) VALUES (?, ?, ?)");
            $stmt->execute([$uri, $ip, $ua]);
        } catch (\Throwable $e) {
            // Silencioso para evitar romper la navegación en caso de error de BD
        }
    }
}
