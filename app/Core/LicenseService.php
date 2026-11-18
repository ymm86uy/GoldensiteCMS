<?php
namespace App\Core;

final class LicenseService
{
    public static function field(array $data, string $key, int $max = 150): string
    {
        $value = $data[$key] ?? '';
        if (!is_string($value)) throw new \InvalidArgumentException('Campo inválido: ' . $key);
        if (!str_ends_with($key, 'password')) $value = trim($value);
        if (strlen($value) > $max || preg_match('/[\x00-\x1f]/', $value)) throw new \InvalidArgumentException('Campo inválido: ' . $key);
        return $value;
    }
    public static function audit(string $event, string $id): void
    {
        Database::getInstance()->execute('INSERT INTO goldensite_license_audit (actor_id, event, reference_id) VALUES (?, ?, ?)', [Auth::id(), $event, $id]);
    }
    public static function activate(string $module, string $code): void
    {
        $module = ModuleCatalog::canonical($module); $code = trim($code);
        if (!ModuleCatalog::issuable($module)) throw new \InvalidArgumentException('Este módulo está pendiente de implementación y no admite activaciones comerciales.');
        $claims = LicenseCode::verify($code, $module, LicenseCode::siteDomain());
        $db = Database::getInstance()->getConnection();
        if (self::revoked($claims['id'])) throw new \InvalidArgumentException('Licencia revocada.');
        $db->beginTransaction();
        try {
            $db->prepare('INSERT INTO goldensite_license_activations (module_id, license_id, signed_code, is_active) VALUES (?, ?, ?, 1) ON DUPLICATE KEY UPDATE license_id = VALUES(license_id), signed_code = VALUES(signed_code), is_active = 1, activated_at = NOW()')->execute([$module, $claims['id'], $code]);
            $db->prepare('INSERT INTO goldensite_active_modules (module_id, is_active) VALUES (?, 1) ON DUPLICATE KEY UPDATE is_active = 1')->execute([$module]);
            self::audit('license_activated', $claims['id']); $db->commit(); FrontendEngine::resetModuleCache();
        } catch (\Throwable $error) { $db->rollBack(); throw $error; }
    }
    public static function revoked(string $id): bool
    {
        return (bool)Database::getInstance()->fetch('SELECT license_id FROM goldensite_license_revocations WHERE license_id = ?', [$id]);
    }
    public static function importRevocations(string $code): void
    {
        Auth::requireAdmin();
        $claims = LicenseCode::open(trim($code), 'NFXR1', 1048576);
        if (($claims['v'] ?? null) !== 1 || ($claims['type'] ?? '') !== 'revocations' || !is_array($claims['ids'] ?? null) || count($claims['ids']) > 10000) throw new \InvalidArgumentException('Lista de revocación inválida.');
        foreach ($claims['ids'] as $id) if (!is_string($id) || !preg_match('/^[a-f0-9]{32}$/D', $id)) throw new \InvalidArgumentException('Lista de revocación inválida.');
        $db = Database::getInstance()->getConnection(); $db->beginTransaction();
        try {
            $stmt = $db->prepare('INSERT IGNORE INTO goldensite_license_revocations (license_id) VALUES (?)');
            foreach ($claims['ids'] as $id) $stmt->execute([$id]);
            $db->exec('UPDATE goldensite_license_activations SET is_active = 0 WHERE license_id IN (SELECT license_id FROM goldensite_license_revocations)');
            self::audit('revocations_imported', 'signed-list'); $db->commit();
        } catch (\Throwable $e) { $db->rollBack(); throw $e; }
    }
}
