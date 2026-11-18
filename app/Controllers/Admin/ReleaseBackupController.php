<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\CommercialUpdateChannel;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\IdentityAccess;
use App\Core\LicenseMiddleware;
use App\Core\ProductEdition;
use App\Core\ReleaseUpdate;

class ReleaseBackupController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        if (class_exists('\App\Core\LicenseMiddleware')) {
            LicenseMiddleware::enforce('goldensite-release-backup', 'Goldensite Release Manager & Advanced Backup PRO', '$350/licencia');
        }

        $msg = $_SESSION['release_msg'] ?? null;
        $errorMessage = $_SESSION['release_error'] ?? null;
        unset($_SESSION['release_msg'], $_SESSION['release_error']);
        $canInstall = IdentityAccess::superuser();

        $title = "Goldensite Release Manager & Advanced Backup PRO";
        $csrfToken = Csrf::generate();
        $commercial = ProductEdition::current() === 'commercial';
        $offer = null;
        $channelMessage = '';
        if ($commercial && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['check_updates'])) {
            if (!\App\Core\RateLimiter::consume('release-check:'.Auth::id(), 5, 60)) {
                $channelMessage = 'Espera un minuto antes de volver a consultar.';
            } else {
                try {
                    $offer = CommercialUpdateChannel::check();
                    if ($offer === null) $channelMessage = 'No hay un paquete publicado para esta versión.';
                } catch (\InvalidArgumentException | \JsonException) {
                    $channelMessage = 'No se pudo verificar el aviso del canal oficial.';
                } catch (\RuntimeException) {
                    $channelMessage = 'El canal oficial no responde. Inténtalo más tarde.';
                }
            }
        }

        $backupDir = dirname(__DIR__, 3) . '/storage/backups';
        $backups = [];
        if (is_dir($backupDir)) {
            $files = array_diff(scandir($backupDir), ['.', '..']);
            foreach ($files as $file) {
                if (str_ends_with($file, '.zip') || str_ends_with($file, '.sql')) {
                    $filePath = $backupDir . '/' . $file;
                    $backups[] = [
                        'name' => $file,
                        'size' => round(filesize($filePath) / 1024, 2) . ' KB',
                        'date' => date('Y-m-d H:i:s', filemtime($filePath))
                    ];
                }
            }
        }

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0;">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; display: flex; align-items: center; gap: 0.75rem;">
                    📦 Goldensite Release Manager & Advanced Backup
                    <span style="background: #22c55e; color: #0f172a; font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: bold;">PRO LICENCIADO</span>
                </h1>
                <p style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Control de versiones de despliegues, respaldos de código y base de datos, y preparación de actualizaciones oficiales.</p>
            </div>

            <?php if ($msg): ?>
                <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #4ade80; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>
            <?php if ($errorMessage): ?>
                <p class="nx-note" role="alert"><?= htmlspecialchars($errorMessage) ?></p>
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                <!-- Crear Snapshot -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="color: #38bdf8; font-size: 1.1rem; font-weight: bold; margin-bottom: 0.5rem;">
                        ⚡ Generar Snapshot del CMS
                    </h3>
                    <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.25rem;">Crea un paquete comprimido (.ZIP) con todos los archivos del CMS, dependencias Vendor, configuraciones y dump completo de MySQL.</p>

                    <form action="/admin/release-backup/create" method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                        
                        <div style="margin-bottom: 1rem;">
                            <label style="color: #cbd5e1; font-size: 0.85rem; display: block; margin-bottom: 0.4rem;">Etiqueta de la Versión / Release:</label>
                            <input type="text" name="release_tag" placeholder="Ej. v1.2.0-stable" required style="width: 100%; background: #0f172a; border: 1px solid #475569; color: #fff; padding: 0.65rem; border-radius: 6px; font-size: 0.9rem;">
                        </div>
                        <button type="submit" style="width: 100%; background: #38bdf8; color: #0f172a; border: none; padding: 0.75rem; font-weight: bold; border-radius: 6px; cursor: pointer;">
                            📦 Crear Backup Completo (.ZIP + SQL)
                        </button>
                    </form>
                </div>

                <!-- Desplegar Parche de Actualización del Core -->
                <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                    <h3 style="margin-bottom:.5rem">Actualizar GoldenSiteCMS</h3>
                    <p style="margin-bottom:1rem">Versión instalada: <strong><?= htmlspecialchars(\App\Core\ReleaseUpdate::version()) ?></strong>. El ZIP firmado puede incluir el núcleo, módulos, temas, dependencias y cambios aditivos de base de datos.</p>
                    <?php if ($commercial): ?>
                    <p><a href="<?= htmlspecialchars(CommercialUpdateChannel::BASE, ENT_QUOTES, 'UTF-8') ?>">Canal oficial de GoldenSiteCMS ↗</a></p>
                    <form action="<?= htmlspecialchars(\App\Core\AdminPath::url('/admin/backups')) ?>" method="post" style="margin-bottom:1rem">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <button class="nx-btn" name="check_updates" value="1" type="submit">Buscar actualizaciones oficiales</button>
                    </form>
                    <?php if ($channelMessage !== ''): ?>
                    <p role="status"><?= htmlspecialchars($channelMessage) ?></p>
                    <?php endif; ?>
                    <?php if ($offer !== null): ?>
                    <p role="status">Disponible la versión <strong><?= htmlspecialchars($offer['version']) ?></strong>.
                        <a href="<?= htmlspecialchars($offer['url']) ?>">Descargar ZIP oficial</a>.
                        Adjunta el ZIP descargado al formulario siguiente.</p>
                    <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($canInstall): ?>
                    <form action="<?= htmlspecialchars(\App\Core\AdminPath::url('/admin/release-backup/apply-update')) ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken,ENT_QUOTES,'UTF-8') ?>">
                        <div style="display:grid;gap:.75rem">
                            <label>Paquete de actualización (ZIP, hasta 20 MB)<input type="file" name="update_file" accept=".zip" required></label>
                            <label>Firma oficial<textarea name="update_signature" required maxlength="2048" rows="3" style="width:100%"><?= htmlspecialchars($offer['signature'] ?? '') ?></textarea></label>
                            <label>Contraseña actual<input type="password" name="current_password" required maxlength="72" autocomplete="current-password"></label>
                            <button class="nx-btn primary" type="submit">Verificar y preparar ZIP</button>
                        </div>
                    </form>
                    <?php $prepared = $_SESSION['release_prepared'] ?? null; ?>
                    <?php if (is_array($prepared) && $prepared['from'] === ReleaseUpdate::version()): ?>
                    <div style="margin-top:1rem;overflow-wrap:anywhere">
                        <h4>Instalar la versión <?= htmlspecialchars($prepared['version']) ?></h4>
                        <p>GoldenSiteCMS guardará un respaldo de la base de datos y del código afectado. El sitio entrará en mantenimiento durante la instalación.</p>
                        <form action="<?= htmlspecialchars(\App\Core\AdminPath::url('/admin/release-backup/install-prepared')) ?>" method="post" style="display:grid;gap:.75rem">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="prepared_id" value="<?= htmlspecialchars($prepared['id'], ENT_QUOTES, 'UTF-8') ?>">
                            <label>Contraseña actual<input type="password" name="current_password" required maxlength="72" autocomplete="current-password"></label>
                            <button class="nx-btn primary" type="submit">Instalar actualización</button>
                        </form>
                        <details style="margin-top:.75rem">
                            <summary>Instalación por consola para administradores del servidor</summary>
                            <code>php tools/release-update.php apply <?= htmlspecialchars($prepared['id']) ?></code>
                        </details>
                    </div>
                    <?php endif; ?>
                    <?php endif; ?>

                </div>
            </div>

            <!-- Historial de Backups -->
            <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem; margin-top: 1.5rem;">
                <h3 style="color: #a855f7; font-size: 1.1rem; font-weight: bold; margin-bottom: 0.5rem;">
                    🔄 Historial de Versiones Generadas
                </h3>
                <p style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 1.25rem;">Directorio seguro: <code>/storage/backups/</code></p>

                <?php if (empty($backups)): ?>
                    <div style="background: #0f172a; padding: 1rem; border-radius: 8px; border: 1px solid #334155; text-align: center;">
                        <span style="color: #22c55e; font-size: 0.85rem; font-weight: bold;">Aún no hay respaldos disponibles</span>
                        <p style="color: #64748b; font-size: 0.8rem; margin-top: 0.5rem;">Aún no hay archivos registrados en /storage/backups/.</p>
                    </div>
                <?php else: ?>
                    <div style="display: flex; flex-direction: column; gap: 0.5rem; max-height: 250px; overflow-y: auto;">
                        <?php foreach ($backups as $b): ?>
                            <div style="background: #0f172a; border: 1px solid #334155; padding: 0.75rem; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="color: #38bdf8; font-weight: bold; font-size: 0.85rem;"><?= htmlspecialchars($b['name']) ?></div>
                                    <div style="color: #64748b; font-size: 0.75rem;"><?= $b['date'] ?> (<?= $b['size'] ?>)</div>
                                </div>
                                <a href="<?= str_ends_with($b['name'], '.sql') ? '/admin/release-backup/sql/'.rawurlencode(substr($b['name'],0,-4)) : '/admin/release-backup/download/'.rawurlencode($b['name']) ?>" style="color:#38bdf8">Descargar respaldo privado</a>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }

    public function applyUpdate(): void
    {
        Auth::requireAdmin();
        \App\Core\RequestGuard::post();
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }
        try {
            if (!\App\Core\RateLimiter::consume('release-stage:'.Auth::id(),10,300)) {
                throw new \InvalidArgumentException('Espera unos minutos antes de volver a intentar.');
            }
            \App\Core\ConsoleView::password();
            $file = $_FILES['update_file'] ?? [];
            if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null)) {
                throw new \InvalidArgumentException('El ZIP no llegó completo. Revisa los límites de carga del servidor.');
            }
            $signature = \App\Core\ConsoleView::field($_POST,'update_signature',2048);
            $_SESSION['release_prepared'] = \App\Core\ReleaseUpdate::stage($file['tmp_name'],$signature);
            $_SESSION['release_msg'] = 'Firma, versión y archivos verificados. Ya puedes instalar la actualización.';
            \App\Core\ConsoleView::audit('release_update_prepared',$_SESSION['release_prepared']['id']);
        } catch (\InvalidArgumentException $error) {
            $_SESSION['release_error'] = $error->getMessage();
        } catch (\Throwable $error) {
            error_log('Preparación de actualización: '.$error->getMessage());
            $_SESSION['release_error'] = 'El paquete no pudo verificarse o guardarse. Consulta el registro del servidor.';
        }
        header('Location: '.\App\Core\AdminPath::url('/admin/backups'),true,303);
    }

    public function installPrepared(): void
    {
        Auth::requireAdmin();
        \App\Core\RequestGuard::post();
        if (!IdentityAccess::superuser()) { http_response_code(403); return; }

        try {
            if (!\App\Core\RateLimiter::consume('release-install:'.Auth::id(), 5, 300)) {
                throw new \InvalidArgumentException('Espera unos minutos antes de volver a intentar.');
            }
            $prepared = $_SESSION['release_prepared'] ?? null;
            $id = $_POST['prepared_id'] ?? null;
            if (!is_string($id) || $id !== ($prepared['id'] ?? null) || $prepared['from'] !== ReleaseUpdate::version()) {
                throw new \InvalidArgumentException('Verifica de nuevo el ZIP que quieres instalar.');
            }
            \App\Core\ConsoleView::password();
            $result = ReleaseUpdate::applyFromPanel($id);
            unset($_SESSION['release_prepared']);
            $_SESSION['release_msg'] = 'Versión '.$result['version'].' instalada. Respaldo SQL: '.$result['database_backup'].'.';
            error_log('Recuperación de actualización: php .goldensite-release/'.$result['rollback'].'/recover.php');
            \App\Core\ConsoleView::audit('release_update_installed', $result['version']);
        } catch (\InvalidArgumentException $error) {
            $_SESSION['release_error'] = $error->getMessage();
        } catch (\Throwable $error) {
            error_log('Instalación de actualización: '.$error->getMessage());
            $_SESSION['release_error'] = 'No se pudo completar la instalación. Comprueba la versión instalada y consulta el registro del servidor.';
        }
        header('Location: '.\App\Core\AdminPath::url('/admin/backups'), true, 303);
    }

    public function create(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['release_msg'] = 'Token CSRF inválido.';
                header('Location: /admin/release-backup');
                exit;
            }

            $tag = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', trim($_POST['release_tag'] ?? 'v1.0.0'));
            $rootPath = realpath(dirname(__DIR__, 3));
            $backupDir = $rootPath . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'backups';

            if (!file_exists($backupDir)) {
                mkdir($backupDir, 0700, true);
            }

            $timestamp = date('Ymd_His') . '_' . bin2hex(random_bytes(4));
            $zipFilename = "release_{$tag}_{$timestamp}.zip";
            $zipPath = $backupDir . DIRECTORY_SEPARATOR . $zipFilename;

            // 1. Exportar Dump SQL
            $sqlContent = "-- Dump de Base de Datos - Release {$tag}\n-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";
            try {
                $db = Database::getInstance()->getConnection();
                $tables = $db->query("SHOW TABLES")->fetchAll(\PDO::FETCH_COLUMN);
                foreach ($tables as $table) {
                    $sqlContent .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    $createTable = $db->query("SHOW CREATE TABLE `{$table}`")->fetch();
                    $sqlContent .= $createTable['Create Table'] . ";\n\n";

                    $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
                    foreach ($rows as $row) {
                        $cols = array_map(static fn ($value) => $value === null ? 'NULL' : $db->quote((string) $value), array_values($row));
                        $sqlContent .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $cols) . ");\n";
                    }
                    $sqlContent .= "\n\n";
                }
            } catch (\Throwable $e) {
                error_log('Backup failed: ' . $e->getMessage());
                $_SESSION['release_msg'] = 'No se pudo generar un respaldo completo de la base. No se creó un ZIP parcial.';
                header('Location: /admin/backups'); return;
            }

            // 2. Empaquetar el ZIP completo incluyendo Vendor, Raíz y Estructura
            if (extension_loaded('zip') && class_exists('ZipArchive')) {
                $zip = new \ZipArchive();
                if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::EXCL) === true) {
                    $zip->addFromString('database_dump.sql', $sqlContent);

                    $foldersToBackup = ['app', 'views', 'routes', 'config', 'public', 'vendor', 'modules', 'storage/downloads', 'storage/forum', 'storage/social', 'storage/extension-packages'];
                    foreach ($foldersToBackup as $f) {
                        $dirToScan = $rootPath . DIRECTORY_SEPARATOR . $f;
                        if (is_dir($dirToScan)) {
                            $files = new \RecursiveIteratorIterator(
                                new \RecursiveDirectoryIterator($dirToScan, \RecursiveDirectoryIterator::SKIP_DOTS),
                                \RecursiveIteratorIterator::LEAVES_ONLY
                            );
                            foreach ($files as $file) {
                                if (!$file->isDir() && !$file->isLink() && !preg_match('/(?:\.bak(?:\.php)?| - copia\.php)$/i', $file->getFilename())) {
                                    $filePath = $file->getRealPath();
                                    $relativePath = str_replace('\\', '/', substr($filePath, strlen($rootPath) + 1));
                                    if ($relativePath !== 'config/env.php') $zip->addFile($filePath, $relativePath);
                                }
                            }
                        }
                    }

                    $rootFiles = ['composer.json', 'composer.lock', '.env.example', 'index.php', 'release-edition.txt', 'release-version.txt'];
                    foreach ($rootFiles as $rf) {
                        $rPath = $rootPath . DIRECTORY_SEPARATOR . $rf;
                        if (file_exists($rPath)) {
                            $zip->addFile($rPath, $rf);
                        }
                    }

                    if (!$zip->close()) throw new \RuntimeException('No se pudo finalizar el respaldo ZIP.');
                    $_SESSION['release_msg'] = "🎉 Backup '{$zipFilename}' creado con éxito (incluye Vendor, SQL y archivos de configuración).";
                } else {
                    file_put_contents($backupDir . DIRECTORY_SEPARATOR . "release_{$tag}_{$timestamp}.sql", $sqlContent);
                    $_SESSION['release_msg'] = "⚠️ No se pudo crear el archivo ZIP. Se generó únicamente el archivo SQL.";
                }
            } else {
                file_put_contents($backupDir . DIRECTORY_SEPARATOR . "release_{$tag}_{$timestamp}.sql", $sqlContent);
                $_SESSION['release_msg'] = "⚠️ La extensión 'zip' no está habilitada en php.ini.";
            }
        }

        header('Location: /admin/release-backup');
        exit;
    }

    public function downloadSql(string $name): void
    {
        $this->download($name . '.sql');
    }

    public function download(string $name): void
    {
        Auth::requireAdmin();
        if (!\App\Core\IdentityAccess::superuser()) { http_response_code(403); return; }
        if (!preg_match('/^(?:release_[A-Za-z0-9_.-]+\.(?:zip|sql)|before-(?:joomla-catalog|catalog|migration)-[0-9]{8}-[0-9]{6}-[a-f0-9]{10,12}\.sql|pre-update-[a-f0-9]{32}\.sql)$/D', $name)) { http_response_code(404); return; }
        $root = realpath(dirname(__DIR__, 3) . '/storage/backups');
        $path = $root ? realpath($root . '/' . $name) : false;
        if (!$path || is_link($root . '/' . $name) || str_replace('\\', '/', dirname($path)) !== str_replace('\\', '/', $root)) { http_response_code(404); return; }
        header('Content-Type: application/octet-stream'); header('Cache-Control: no-store'); header('Content-Disposition: attachment; filename="' . $name . '"'); header('Content-Length: ' . filesize($path)); readfile($path);
    }
}
