<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Settings;
use App\Core\Csrf;

class FtpController
{
    private static function sanitizeHost(string $host): string
    {
        $host = trim($host);
        if (strpos($host, '://') !== false) {
            $parsed = parse_url($host);
            $host = $parsed['host'] ?? $host;
        }
        return preg_replace('#/.*$#', '', $host);
    }

    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        $status = null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $status = ['type' => 'danger', 'msg' => 'Token CSRF inválido.'];
            } else {
                $action = $_POST['action'] ?? '';

                $rawHost = !empty($_POST['ftp_host']) ? $_POST['ftp_host'] : Settings::get('ftp_host', '');
                $host = self::sanitizeHost($rawHost);
                $port = (int)(!empty($_POST['ftp_port']) ? $_POST['ftp_port'] : Settings::get('ftp_port', '21'));
                $user = trim(!empty($_POST['ftp_user']) ? $_POST['ftp_user'] : Settings::get('ftp_user', ''));
                $pass = is_string($_POST['ftp_pass']??null)&&$_POST['ftp_pass']!==''?$_POST['ftp_pass']:\App\Core\SecretStore::open(Settings::get('ftp_pass',''));
                $root = trim($_POST['ftp_root'] ?? Settings::get('ftp_root', '/'));
                $passive = isset($_POST['ftp_passive']) ? '1' : Settings::get('ftp_passive', '1');
                $ssl = isset($_POST['ftp_ssl']) ? '1' : Settings::get('ftp_ssl', '0');

                if (!filter_var($host,FILTER_VALIDATE_DOMAIN,FILTER_FLAG_HOSTNAME)||filter_var($host,FILTER_VALIDATE_IP)||$port<1||$port>65535||strlen($user)>200||strlen($pass)>4096||preg_match('/[\r\n\x00]/',$host.$user.$pass)) { http_response_code(422); exit('Datos de conexión inválidos.'); }
                if ($ssl!=='1') { http_response_code(422); exit('Se requiere FTP con TLS y certificado verificado. No se permite enviar credenciales por FTP sin cifrar.'); }
                if ($action === 'save_ftp') {
                    Settings::set('ftp_host', $host);
                    Settings::set('ftp_port', (string)$port);
                    Settings::set('ftp_user', $user);
                    if (!empty($pass)) {
                        Settings::set('ftp_pass', \App\Core\SecretStore::seal($pass));
                    }
                    Settings::set('ftp_root', $root);
                    Settings::set('ftp_passive', (string)$passive);
                    Settings::set('ftp_ssl', (string)$ssl);

                    $status = ['type' => 'success', 'msg' => 'Configuración FTP guardada exitosamente.'];
                } elseif ($action === 'test_connection') {
                    if (empty($host) || empty($user)) {
                        $status = ['type' => 'danger', 'msg' => 'Debes ingresar un Host y Usuario FTP válidos.'];
                    } elseif (!\App\Core\RateLimiter::consume('ftps-test:'.Auth::id(),3,300)) { $status=['type'=>'danger','msg'=>'Espera antes de repetir la prueba.']; } else { $success=\App\Core\FtpsConnection::test($host,$port,$user,$pass); $status=['type'=>$success?'success':'danger','msg'=>$success?'Conexión FTPS autenticada con TLS verificado.':'No se confirmó una conexión FTPS pública con certificado válido.']; }
                }
            }
        }

        $ftpHost = Settings::get('ftp_host', '');
        $ftpPort = Settings::get('ftp_port', '21');
        $ftpUser = Settings::get('ftp_user', '');
        $ftpRoot = Settings::get('ftp_root', '/');
        $ftpPassive = Settings::get('ftp_passive', '1');
        $ftpSsl = '1';

        $csrfToken = Csrf::generate();
        $title = "Gestor FTP - Goldensite Admin";

        ob_start();
        require_once __DIR__ . '/../../../views/admin/ftp.php';
        $content = ob_get_clean();

        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }
}