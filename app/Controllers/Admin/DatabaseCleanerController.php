<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Database;

class DatabaseCleanerController
{
    public function index(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        $db = Database::getInstance()->getConnection();
        $msg = $_SESSION['db_cleaner_msg'] ?? null;
        unset($_SESSION['db_cleaner_msg']);

        // Diagnóstico preliminar de espacio
        $tablesStats = [];
        $totalOverhead = 0;
        try {
            $stmt = $db->query("SHOW TABLE STATUS");
            if ($stmt) {
                while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
                    $overhead = (int)($row['Data_free'] ?? 0);
                    $totalOverhead += $overhead;
                    $tablesStats[] = [
                        'name' => $row['Name'],
                        'rows' => $row['Rows'],
                        'size' => round(($row['Data_length'] + $row['Index_length']) / 1024, 2),
                        'overhead' => round($overhead / 1024, 2)
                    ];
                }
                $stmt->closeCursor();
            }
        } catch (\Throwable $e) {}

        $title = "Goldensite Database Cleaner PRO";
        $csrfToken = Csrf::generate();

        ob_start();
        ?>
        <div class="container-fluid" style="padding: 1rem 0;">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: bold; color: #fff; display: flex; align-items: center; gap: 0.75rem;">
                    🧹 Goldensite Database Cleaner
                    <span style="background: #a855f7; color: #fff; font-size: 0.75rem; padding: 0.2rem 0.6rem; border-radius: 4px; font-weight: bold;">PRO MODULE</span>
                </h1>
                <p style="color: #94a3b8; font-size: 0.95rem; margin-top: 0.25rem;">Depuración guiada en 4 pasos para mantener la base de datos liviana y optimizada.</p>
            </div>

            <?php if ($msg): ?>
                <div style="background: rgba(34, 197, 94, 0.15); border: 1px solid #22c55e; color: #4ade80; padding: 1rem 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; font-weight: 500;">
                    <?= htmlspecialchars($msg) ?>
                </div>
            <?php endif; ?>

            <!-- Flujo de 4 Pasos -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
                <div style="background: #1e293b; padding: 1.25rem; border-radius: 10px; border-left: 4px solid #38bdf8;">
                    <span style="color: #38bdf8; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">Paso 1</span>
                    <h4 style="color: #fff; margin-top: 0.25rem;">Review</h4>
                    <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.25rem;">Análisis de tablas y registros no esenciales.</p>
                </div>
                <div style="background: #1e293b; padding: 1.25rem; border-radius: 10px; border-left: 4px solid #f59e0b;">
                    <span style="color: #f59e0b; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">Paso 2</span>
                    <h4 style="color: #fff; margin-top: 0.25rem;">Select</h4>
                    <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.25rem;">Selección de elementos a depurar.</p>
                </div>
                <div style="background: #1e293b; padding: 1.25rem; border-radius: 10px; border-left: 4px solid #a855f7;">
                    <span style="color: #a855f7; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">Paso 3</span>
                    <h4 style="color: #fff; margin-top: 0.25rem;">Backup</h4>
                    <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.25rem;">Copia de seguridad de salvaguarda pre-limpieza.</p>
                </div>
                <div style="background: #1e293b; padding: 1.25rem; border-radius: 10px; border-left: 4px solid #22c55e;">
                    <span style="color: #22c55e; font-size: 0.75rem; font-weight: bold; text-transform: uppercase;">Paso 4</span>
                    <h4 style="color: #fff; margin-top: 0.25rem;">Clean</h4>
                    <p style="color: #94a3b8; font-size: 0.8rem; margin-top: 0.25rem;">Ejecución de OPTIMIZE TABLE y purge.</p>
                </div>
            </div>

            <div style="background: #1e293b; border: 1px solid #334155; border-radius: 12px; padding: 1.5rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 1rem;">
                    <h3 style="color: #fff; font-size: 1.1rem; font-weight: bold;">📊 Estado de Tablas MySQL</h3>
                    
                    <div style="display: flex; gap: 0.75rem;">
                        <!-- Botón de Salvaguarda / Backup -->
                        <a href="/admin/db-cleaner/backup" style="background: #a855f7; color: #fff; text-decoration: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: bold; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem;">
                            📥 Descargar Backup SQL
                        </a>

                        <!-- Botón de Limpieza -->
                        <form action="/admin/db-cleaner/clean" method="POST" style="margin: 0;"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars(\App\Core\Csrf::generate(), ENT_QUOTES, 'UTF-8') ?>">
                            
                            <button type="submit" style="background: #22c55e; color: #0f172a; border: none; padding: 0.6rem 1.25rem; border-radius: 6px; font-weight: bold; cursor: pointer; font-size: 0.9rem;">
                                🚀 Ejecutar Limpieza y Optimización
                            </button>
                        </form>
                    </div>
                </div>

                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.9rem;">
                        <thead>
                            <tr style="border-bottom: 1px solid #334155; color: #94a3b8;">
                                <th style="padding: 0.75rem;">Tabla</th>
                                <th style="padding: 0.75rem;">Filas</th>
                                <th style="padding: 0.75rem;">Tamaño (KB)</th>
                                <th style="padding: 0.75rem;">Espacio Desperdiciado (KB)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tablesStats as $t): ?>
                                <tr style="border-bottom: 1px solid #1e293b;">
                                    <td style="padding: 0.75rem; color: #fff; font-weight: 500;"><?= htmlspecialchars($t['name']) ?></td>
                                    <td style="padding: 0.75rem; color: #cbd5e1;"><?= number_format($t['rows']) ?></td>
                                    <td style="padding: 0.75rem; color: #cbd5e1;"><?= number_format($t['size'], 2) ?> KB</td>
                                    <td style="padding: 0.75rem; color: <?= $t['overhead'] > 0 ? '#f59e0b' : '#64748b' ?>;">
                                        <?= number_format($t['overhead'], 2) ?> KB
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require_once __DIR__ . '/../../../views/layouts/admin.php';
    }

    /**
     * Genera y descarga un dump SQL completo de la base de datos
     */
    public function backup(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();

        try {
            $db = Database::getInstance()->getConnection();
            
            $stmt = $db->query("SHOW TABLES");
            $tables = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            if ($stmt) $stmt->closeCursor();

            $sqlDump = "-- GoldenSiteCMS - Backup de Salvaguarda de Base de Datos\n";
            $sqlDump .= "-- Fecha: " . date('Y-m-d H:i:s') . "\n\n";
            $sqlDump .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

            foreach ($tables as $table) {
                // Estructura
                $createStmt = $db->query("SHOW CREATE TABLE `$table`");
                $createRow = $createStmt ? $createStmt->fetch(\PDO::FETCH_ASSOC) : null;
                if ($createStmt) $createStmt->closeCursor();

                if (isset($createRow['Create Table'])) {
                    $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n";
                    $sqlDump .= $createRow['Create Table'] . ";\n\n";
                }

                // Datos
                $dataStmt = $db->query("SELECT * FROM `$table`");
                if ($dataStmt) {
                    while ($row = $dataStmt->fetch(\PDO::FETCH_ASSOC)) {
                        $keys = array_map(function($k) { return "`$k`"; }, array_keys($row));
                        $vals = array_map(function($v) use ($db) {
                            return $v === null ? 'NULL' : $db->quote($v);
                        }, array_values($row));

                        $sqlDump .= "INSERT INTO `$table` (" . implode(', ', $keys) . ") VALUES (" . implode(', ', $vals) . ");\n";
                    }
                    $dataStmt->closeCursor();
                }
                $sqlDump .= "\n";
            }

            $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

            $filename = 'goldensite_db_backup_' . date('Y_m_d_H_i_s') . '.sql';

            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($sqlDump));
            echo $sqlDump;
            exit;
        } catch (\Throwable $e) {
            if (session_status() === PHP_SESSION_NONE) session_start();
            $_SESSION['db_cleaner_msg'] = 'Error al generar la salva: ' . $e->getMessage();
            header('Location: /admin/db-cleaner');
            exit;
        }
    }

    public function clean(): void
    {
        Auth::requireAdmin();
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') \App\Core\RequestGuard::csrf();
        if (session_status() === PHP_SESSION_NONE) session_start();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::validate($_POST['csrf_token'] ?? '')) {
                $_SESSION['db_cleaner_msg'] = 'Token CSRF inválido.';
                header('Location: /admin/db-cleaner');
                exit;
            }

            try {
                $db = Database::getInstance()->getConnection();
                
                // 1. Limpieza de sesiones caducadas
                $db->exec("DELETE FROM active_sessions WHERE last_activity < NOW() - INTERVAL 1 DAY");

                // 2. Traer nombres de todas las tablas y cerrar cursor
                $stmt = $db->query("SHOW TABLES");
                $tables = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
                if ($stmt) $stmt->closeCursor();

                // 3. Ejecutar OPTIMIZE TABLE unificado
                if (!empty($tables)) {
                    $escapedTables = array_map(function($t) { return "`$t`"; }, $tables);
                    $sql = "OPTIMIZE TABLE " . implode(', ', $escapedTables);
                    $db->exec($sql);
                }

                $_SESSION['db_cleaner_msg'] = '🎉 Limpieza finalizada: tablas optimizadas y registros temporales eliminados con éxito.';
            } catch (\Throwable $e) {
                $_SESSION['db_cleaner_msg'] = 'Error al ejecutar limpieza: ' . $e->getMessage();
            }
        }

        header('Location: /admin/db-cleaner');
        exit;
    }
}