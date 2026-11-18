<?php
namespace App\Core;
final class LicenseEngine
{
    public static function isLicenseActive(string $moduleKey): bool
    {
        $module = ModuleCatalog::canonical($moduleKey);
        $definition=ModuleCatalog::get($module);
        if (!$definition) return false;
        if (!ModuleCatalog::requiresLicenseHere($definition)) return true;
        try {
            $row = Database::getInstance()->fetch('SELECT signed_code FROM goldensite_license_activations WHERE module_id = ? AND is_active = 1', [$module]);
            if (!$row) return false;
            $claims = LicenseCode::verify($row['signed_code'], $module, LicenseCode::siteDomain());
            return !LicenseService::revoked($claims['id']);
        } catch (\InvalidArgumentException|\JsonException $error) { return false; }
        catch (\Throwable $error) { error_log('License validation unavailable: ' . $error->getMessage()); return false; }
    }
}
