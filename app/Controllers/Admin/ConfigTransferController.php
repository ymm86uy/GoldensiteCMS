<?php
namespace App\Controllers\Admin;

use App\Core\{Auth, CacheStore, CmsMigration, ConsoleView, IdentityAccess, RequestGuard, Settings, WordPressWxrImport};

final class ConfigTransferController
{
    private const LIMITS = ['site_title'=>150,'site_description'=>1000,'site_keywords'=>500,'contact_email'=>150,'contact_phone'=>50];

    public function index(): void
    {
        Auth::requireAdmin();
        $notice = '';
        $wxrPlan = null;
        $wxrToken = '';
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            RequestGuard::post();
            try {
                $action = $_POST['action'] ?? '';
                if ($action==='wxr_scan' || $action==='wxr_import') {
                    if (!IdentityAccess::superuser()) { http_response_code(403); return; }
                    if ($action==='wxr_scan') {
                        unset($_SESSION['wordpress_wxr_plan']);
                        $wxrPlan=WordPressWxrImport::upload($_FILES['wxr_file']??[]);
                        $wxrToken=CmsMigration::save($wxrPlan);
                        $_SESSION['wordpress_wxr_plan']=$wxrToken;
                    } else {
                        $token=ConsoleView::field($_POST,'plan_id',64);
                        if ($token==='' || !hash_equals($_SESSION['wordpress_wxr_plan']??'',$token) ||
                            ($_POST['confirm']??'')!=='IMPORTAR') {
                            throw new \InvalidArgumentException('Analiza el XML y confirma IMPORTAR desde la vista previa.');
                        }
                        $wxrPlan=CmsMigration::load($token);
                        if ($wxrPlan['cms']!=='wordpress-wxr') throw new \InvalidArgumentException('Plan de WordPress inválido.');
                        $wxrToken=$token;
                        ConsoleView::password();
                        $summary=CmsMigration::apply($token);
                        unset($_SESSION['wordpress_wxr_plan']);
                        $wxrPlan=null;
                        $wxrToken='';
                        ConsoleView::audit('wordpress_wxr_import','Contenido importado: '.($summary['pages']+$summary['posts']));
                        $notice='Importación completada. Páginas: '.$summary['pages'].'. Artículos: '.$summary['posts'].'. Respaldo: '.$summary['backup'];
                    }
                    $this->render($notice,$wxrPlan,$wxrToken);
                    return;
                }
                if ($action === 'export') {
                    $values = array_intersect_key(Settings::all(), self::LIMITS);
                    $json = json_encode(['format'=>'goldensite-settings-v1','settings'=>(object)$values], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
                    while (ob_get_level()>0) ob_end_clean();
                    header('Content-Type: application/json; charset=utf-8');
                    header('Content-Disposition: attachment; filename="goldensite-settings.json"');
                    header('Cache-Control: no-store');
                    echo $json;
                    return;
                }
                if ($action !== 'import') throw new \InvalidArgumentException('Operación desconocida.');
                $json = $_POST['config_json'] ?? '';
                if (!is_string($json) || strlen($json)>64000) throw new \InvalidArgumentException('El JSON debe tener hasta 64 KB.');
                $document = json_decode($json, false, 16, JSON_THROW_ON_ERROR);
                if (!is_object($document) || !in_array($document->format??null,['goldensite-settings-v1',\App\Core\LegacyFormat::PREFIX.'-settings-v1'],true) || !is_object($document->settings??null)) {
                    throw new \InvalidArgumentException('Formato de configuración inválido.');
                }
                $values = get_object_vars($document->settings);
                if (!$values) throw new \InvalidArgumentException('El archivo no contiene ajustes.');
                foreach ($values as $key=>$value) {
                    if (!isset(self::LIMITS[$key]) || !is_string($value) || mb_strlen($value)>self::LIMITS[$key] ||
                        preg_match('/[\x00-\x08\x0b\x0c\x0e-\x1f<>]/',$value) ||
                        ($key==='site_title' && trim($value)==='') ||
                        ($key==='contact_email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL))) {
                        throw new \InvalidArgumentException('El documento contiene un ajuste no permitido o inválido.');
                    }
                }
                Settings::writeMany($values);
                CacheStore::clear();
                ConsoleView::audit('basic_configuration_imported','Ajustes importados: '.count($values));
                $notice = 'Configuración básica importada.';
            } catch (\InvalidArgumentException|\JsonException $e) {
                http_response_code(422);
                $notice = $e instanceof \JsonException ? 'JSON inválido.' : $e->getMessage();
            }
        }
        $this->render($notice,$wxrPlan,$wxrToken);
    }

    private function render(string $notice,?array $wxrPlan,string $wxrToken): void
    {
        $catalogReady=Settings::get('joomla_catalog_enabled')==='1';
        ConsoleView::render('config_transfer',compact('notice','catalogReady','wxrPlan','wxrToken'),'Goldensite Import / Export');
    }
}
