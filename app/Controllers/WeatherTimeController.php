<?php
namespace App\Controllers;

use App\Core\{Auth,CacheStore,ConsoleView,Environment,FrontendEngine,MetNorwayForecast,RateLimiter,RequestGuard,Settings,WeatherTime};

final class WeatherTimeController
{
    public function settings(): void
    {
        Auth::requireAdmin();
        $active = FrontendEngine::isModuleActive(WeatherTime::ID);
        $contact = Settings::get('weather_met_contact', rtrim(Environment::get('APP_URL'),'/').'/contacto');
        $notice = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RequestGuard::post();
            if (!$active) { http_response_code(409); return; }
            $position = $_POST['position'] ?? null;
            $unit = $_POST['unit'] ?? null;
            $met = ($_POST['met_enabled'] ?? '') === '1';
            $submittedContact = $_POST['met_contact'] ?? '';
            if (!is_string($position) || !isset(WeatherTime::POSITIONS[$position]) || !in_array($unit, ['c','f'], true)) {
                http_response_code(422);
                $notice = 'Revisa la posición y la unidad de temperatura.';
            } elseif ($met && (!is_string($submittedContact) || !WeatherTime::validContact(trim($submittedContact)))) {
                http_response_code(422);
                $notice = 'Introduce una URL HTTPS pública de contacto, sin parámetros ni credenciales.';
            } else {
                $settings = ['weather_time_position'=>$position,'weather_time_unit'=>$unit];
                if (isset($_POST['provider_settings'])) {
                    $settings['weather_met_enabled'] = $met?'1':'0';
                    if ($met) $settings['weather_met_contact'] = $contact = trim($submittedContact);
                }
                Settings::writeMany($settings);
                CacheStore::clear();
                ConsoleView::audit('weather_time_settings','Configuración del módulo actualizada.');
                $notice = 'Configuración guardada.';
            }
        }
        ConsoleView::render('weather_time', [
            'active'=>$active, 'notice'=>$notice, 'provider'=>WeatherTime::provider(), 'contact'=>$contact,
            'position'=>Settings::get('weather_time_position','shortcode'), 'unit'=>Settings::get('weather_time_unit','c'),
        ], 'Goldensite Weather & Time');
    }

    public function current(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, private');
        if (!FrontendEngine::isModuleActive(WeatherTime::ID)) { $this->respond(404, 'Módulo desactivado.'); return; }
        $provider = WeatherTime::provider();
        if ($provider === null) { $this->respond(503, 'El sitio aún no ha configurado el servicio meteorológico.'); return; }
        $coordinates = WeatherTime::coordinates($_POST);
        if (!$coordinates) { $this->respond(422, 'Ubicación inválida.'); return; }
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        try {
            if (!RateLimiter::consume('weather-time:ip:'.$ip, 6, 60) || !RateLimiter::consume('weather-time:global', 50, 60)) {
                header('Retry-After: 60');
                $this->respond(429, 'Espera un minuto antes de volver a consultar.');
                return;
            }
            session_write_close();
            $forecast = $provider==='met' ? MetNorwayForecast::current($coordinates, Settings::get('weather_met_contact'))
                : WeatherTime::current($coordinates, WeatherTime::providerKey());
            echo json_encode($forecast, JSON_THROW_ON_ERROR);
        } catch (\Throwable $error) {
            $this->respond(503, 'El clima no está disponible en este momento.');
        }
    }

    private function respond(int $status, string $message): void
    {
        http_response_code($status);
        echo json_encode(['message'=>$message], JSON_THROW_ON_ERROR);
    }
}
