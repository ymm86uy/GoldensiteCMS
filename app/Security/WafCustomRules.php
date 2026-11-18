<?php
namespace App\Security;

use App\Core\{AdminPath,Database,Settings};

final class WafCustomRules
{
    private const KEY = 'waf_custom_rules';
    private const LIMIT = 24;
    public const METHODS = ['*'=>'Cualquier método','GET'=>'GET','POST'=>'POST','HEAD'=>'HEAD','OPTIONS'=>'OPTIONS'];

    public static function all(): array
    {
        return self::decode(Settings::get(self::KEY, '[]'));
    }

    private static function decode(string $value): array
    {
        $rules = json_decode($value, true, 8);
        return is_array($rules) ? array_slice($rules, 0, self::LIMIT) : [];
    }

    private static function protectedPath(string $path): bool
    {
        return preg_match('~^/(?:admin|login|logout|account)(?:/|$)~i', AdminPath::normalize($path)) === 1;
    }

    public static function hit(string $method, string $uri): ?array
    {
        $rules = self::all();
        if (!$rules) return null;
        $path = rtrim(WafPolicy::decode(explode('?', $uri, 2)[0]), '/');
        if ($path === '' || self::protectedPath($path)) return null;
        $hit = null;
        foreach ($rules as $rule) {
            if (!$rule['enabled'] || ($rule['method'] !== '*' && $rule['method'] !== $method)) continue;
            if ($path !== $rule['path'] && ($rule['scope'] !== 'prefix' || !str_starts_with($path, $rule['path'].'/'))) continue;
            $block = $rule['mode'] === 'block';
            $hit = ['id'=>$rule['id'],'field'=>'path','category'=>'Regla manual','score'=>$block?10:1,'custom_block'=>$block];
            if ($block) return $hit;
        }
        return $hit;
    }

    public static function save(string $id, string $label, string $path, string $scope, string $method, string $mode): string
    {
        if ($label === '' || strlen($label)>80 || preg_match('/[\x00-\x1f\x7f]/', $label)) throw new \InvalidArgumentException('Introduce un nombre de hasta 80 caracteres.');
        $path = rtrim($path, '/');
        if (strlen($path)>160 || !preg_match('~^/[A-Za-z0-9_-][A-Za-z0-9._-]*(?:/[A-Za-z0-9_-][A-Za-z0-9._-]*)*$~D', $path)
            || self::protectedPath($path) || !in_array($scope, ['exact','prefix'], true)
            || !isset(self::METHODS[$method]) || !in_array($mode, ['monitor','block'], true)) {
            throw new \InvalidArgumentException('Usa una ruta pública literal, sin parámetros, y selecciona un método y una acción válidos.');
        }
        $new = $id === '';
        if ($new) $id = 'NX-M'.strtoupper(bin2hex(random_bytes(5)));
        self::change($id, static function (array $rules, ?int $index) use ($new,$id,$label,$path,$scope,$method,$mode): array {
            if (!$new && $index === null) throw new \InvalidArgumentException('La regla ya no existe.');
            if ($new && count($rules) >= self::LIMIT) throw new \InvalidArgumentException('Se alcanzó el límite de 24 reglas manuales.');
            $enabled = $new || $rules[$index]['enabled'];
            $rules[$new?count($rules):$index] = compact('id','label','path','scope','method','mode','enabled');
            return $rules;
        });
        return $id;
    }

    public static function toggle(string $id): void
    {
        self::change($id, static function (array $rules, ?int $index): array {
            if ($index === null) throw new \InvalidArgumentException('La regla ya no existe.');
            $rules[$index]['enabled'] = !$rules[$index]['enabled'];
            return $rules;
        });
    }

    public static function remove(string $id): void
    {
        self::change($id, static function (array $rules, ?int $index): array {
            if ($index === null) throw new \InvalidArgumentException('La regla ya no existe.');
            array_splice($rules,$index,1);
            return $rules;
        });
    }

    private static function change(string $id, callable $update): void
    {
        if (!preg_match('/^NX-M[0-9A-F]{10}$/D', $id)) throw new \InvalidArgumentException('Identificador de regla inválido.');
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $pdo->beginTransaction();
        try {
            $db->execute('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)', [self::KEY,'[]']);
            $row = $db->fetch('SELECT setting_value FROM settings WHERE setting_key=? FOR UPDATE', [self::KEY]);
            $rules = self::decode($row['setting_value']);
            $index = array_search($id, array_column($rules, 'id'), true);
            Settings::set(self::KEY, json_encode($update($rules, $index===false?null:$index), JSON_THROW_ON_ERROR));
            $pdo->commit();
        } catch (\Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }
}
