<?php
namespace App\Core;
final class RedirectRules
{
    public static function path(string $url,bool $source=false): bool { $decoded=\App\Security\WafPolicy::decode($url); if (!Navigation::safeUrl($url)||!str_starts_with($decoded,'/')||str_starts_with($decoded,'//')||preg_match('~[\x00-\x20\\\\]|(?:^|/)\.\.(?:/|$)~',$decoded)) return false; $path=parse_url($decoded,PHP_URL_PATH); if (!$path) return false; if ($source && ($path!==$decoded||$path==='/'||preg_match('~^/(?:admin|api|login|logout|register|registro|language|account|install\.php|restore\.php)(?:/|$)~',AdminPath::normalize($path)))) return false; return true; }
    public static function save(string $source,string $target,int $type): void {
        if (!self::path($source,true)||!self::path($target)||!in_array($type,[301,302,307,308],true)||parse_url($target,PHP_URL_PATH)===$source) throw new \InvalidArgumentException('Usa rutas internas diferentes y código 301, 302, 307 o 308. Las rutas administrativas y de acceso están protegidas.');
        $db=Database::getInstance()->getConnection(); $db->beginTransaction(); try { $db->exec("INSERT IGNORE INTO settings (setting_key,setting_value) VALUES ('redirect_rules_lock','1')"); $db->query("SELECT setting_value FROM settings WHERE setting_key='redirect_rules_lock' FOR UPDATE")->fetchColumn();
            $next=parse_url($target,PHP_URL_PATH); $seen=[$source=>true]; for ($i=0;$i<16;$i++) { if (isset($seen[$next])) throw new \InvalidArgumentException('La regla crearía un bucle de redirecciones.'); $seen[$next]=true; $stmt=$db->prepare('SELECT target_url FROM goldensite_redirects WHERE source_url=?'); $stmt->execute([$next]); $value=$stmt->fetchColumn(); if (!$value) break; $next=parse_url($value,PHP_URL_PATH); if ($i===15) throw new \InvalidArgumentException('La cadena de redirecciones es demasiado larga.'); }
            $db->prepare('INSERT INTO goldensite_redirects (source_url,target_url,redirect_type) VALUES (?,?,?) ON DUPLICATE KEY UPDATE target_url=VALUES(target_url),redirect_type=VALUES(redirect_type)')->execute([$source,$target,$type]); $db->commit();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
}
