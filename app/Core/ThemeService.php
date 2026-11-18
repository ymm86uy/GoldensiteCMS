<?php
namespace App\Core;
final class ThemeService
{
    public static function active(): string
    {
        $id = Settings::get('active_theme', 'dark-cyber');
        return ThemeCatalog::valid($id) ? $id : 'dark-cyber';
    }
    public static function profile(?string $id = null): array
    {
        $id ??= self::active();
        $palette = ThemeCatalog::palettes()[$id] ?? ThemeCatalog::palettes()['dark-cyber'];
        $profiles = json_decode(Settings::get('theme_profiles', '{}'), true);
        $values = is_array($profiles) && is_array($profiles[$id] ?? null) ? $profiles[$id] : [];
        $defaults=['primary_color' => $palette['accent'], 'bg_color' => $palette['bg'], 'border_radius' => '12px', 'effect_hover' => 'scale', 'effect_click' => 'push', 'effect_exit' => 'smooth-restore', 'custom_css' => ''];
        foreach ($values as $key=>$value) if (!is_string($value) || !array_key_exists($key,$defaults)) unset($values[$key]);
        foreach (['primary_color','bg_color'] as $key) if (isset($values[$key]) && !preg_match('/^#[a-f0-9]{6}$/iD',$values[$key])) unset($values[$key]);
        foreach (['border_radius'=>['0px','4px','6px','8px','12px','16px','24px','50px'],'effect_hover'=>['none','scale','glow','lift'],'effect_click'=>['none','push','ripple'],'effect_exit'=>['none','smooth-restore','fade']] as $key=>$allowed) if (isset($values[$key]) && !in_array($values[$key],$allowed,true)) unset($values[$key]);
        if (isset($values['custom_css']) && (strlen($values['custom_css'])>24000 || preg_match('/[<>\x00]|expression\s*\(|javascript\s*:/i',$values['custom_css']))) unset($values['custom_css']);
        return array_merge($defaults,$values);
    }
    public static function activate(string $id): void
    {
        if (!ThemeCatalog::valid($id)) throw new \InvalidArgumentException('El estilo seleccionado no pertenece al catálogo de Goldensite.');
        Settings::writeMany(['active_theme' => $id, 'use_ai_frontend' => '0']);
        CacheStore::clear();
    }
    public static function save(array $input): void
    {
        $id = LicenseService::field($input, 'theme_id', 50) ?: self::active();
        if ($id !== self::active()) throw new \InvalidArgumentException('El tema activo cambió en otra pestaña. Recarga el personalizador.');
        $profile = [];
        foreach (['primary_color', 'bg_color'] as $key) {
            $profile[$key] = LicenseService::field($input, $key, 7);
            if (!preg_match('/^#[a-f0-9]{6}$/iD', $profile[$key])) throw new \InvalidArgumentException('Introduce colores hexadecimales de seis cifras.');
        }
        foreach (['border_radius' => ['0px','4px','8px','12px','16px','24px','50px'], 'effect_hover' => ['none','scale','glow','lift'], 'effect_click' => ['none','push','ripple'], 'effect_exit' => ['none','smooth-restore','fade']] as $key => $allowed) {
            $value = LicenseService::field($input, $key, 30);
            if (!in_array($value, $allowed, true)) throw new \InvalidArgumentException('Opción de apariencia inválida: ' . $key);
            $profile[$key] = $value;
        }
        $css = $input['custom_css'] ?? '';
        if (!is_string($css) || strlen($css) > 24000 || preg_match('/[<>\x00]|expression\s*\(|javascript\s*:/i', $css)) throw new \InvalidArgumentException('El CSS debe contener solo estilos, hasta 24 KB y sin etiquetas HTML.');
        $profile['custom_css'] = $css;
        $db = Database::getInstance()->getConnection(); $db->beginTransaction();
        try {
            $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('theme_profiles', '{}')");
            $row = $db->query("SELECT setting_value FROM settings WHERE setting_key = 'theme_profiles' FOR UPDATE")->fetchColumn();
            $profiles = json_decode($row, true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($profiles)) throw new \RuntimeException('Perfiles de tema inválidos.');
            $profiles[$id] = $profile;
            $db->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'theme_profiles'")->execute([json_encode($profiles, JSON_THROW_ON_ERROR)]);
            $db->commit(); Settings::reset(); CacheStore::clear();
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
    }
    public static function context(): array
    {
        $activeTheme = self::active(); $profile = self::profile($activeTheme); $palette = ThemeCatalog::palettes()[$activeTheme];
        return ['activeTheme' => $activeTheme, 'primaryColor' => $profile['primary_color'], 'bgColor' => $profile['bg_color'], 'cardBg' => $palette['card'], 'textColor' => '#f8fafc', 'fontFamily' => "'Segoe UI', sans-serif", 'glowColor' => $palette['glow'] ?? '#38bdf84d'];
    }
    public static function resetProfile(string $id): void
    {
        if ($id!==self::active()) throw new \InvalidArgumentException('El estilo activo cambió; recarga la página.');
        $db=Database::getInstance()->getConnection(); $db->beginTransaction();
        try {
            $db->exec("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('theme_profiles','{}')");
            $profiles=json_decode($db->query("SELECT setting_value FROM settings WHERE setting_key='theme_profiles' FOR UPDATE")->fetchColumn(),true,32,JSON_THROW_ON_ERROR);
            unset($profiles[$id]);
            $db->prepare("UPDATE settings SET setting_value=? WHERE setting_key='theme_profiles'")->execute([json_encode($profiles,JSON_THROW_ON_ERROR)]);
            $db->commit(); Settings::reset(); CacheStore::clear();
        } catch (\Throwable $error) { if ($db->inTransaction()) $db->rollBack(); throw $error; }
    }
    public static function head(): string
    {
        $id = self::active(); $p = self::profile($id); $c = self::context();
        $hover = ['none'=>'none', 'scale'=>'scale(1.025)', 'glow'=>'none', 'lift'=>'translateY(-3px)'][$p['effect_hover']] ?? 'none';
        $click = $p['effect_click'] === 'push' ? 'scale(.97)' : 'none';
        $transition=['none'=>'none','fade'=>'background-color .3s,box-shadow .4s,transform .2s','smooth-restore'=>'transform .25s cubic-bezier(.2,.7,.3,1),box-shadow .25s'][$p['effect_exit']];
        $css = ':root{--primary:'.$p['primary_color'].';--bg-color:'.$p['bg_color'].';--card-bg:'.$c['cardBg'].';--text-color:#f8fafc;--border-radius:'.$p['border_radius'].';--glow-color:'.$c['glowColor'].'}body{background:var(--bg-color)!important;color:var(--text-color)}.btn-cta,.btn-hero-main,button[type=submit]{border-radius:var(--border-radius);transition:'.$transition.'}.btn-cta:hover,.btn-hero-main:hover,button[type=submit]:hover{transform:'.$hover.'}.btn-cta:active,.btn-hero-main:active,button[type=submit]:active{transform:'.$click.'}';
        if ($p['effect_hover'] === 'glow') $css .= '.btn-cta:hover,.btn-hero-main:hover,button[type=submit]:hover{box-shadow:0 0 24px var(--glow-color)}';
        if ($p['effect_click'] === 'ripple') $css .= '.btn-cta:active,.btn-hero-main:active,button[type=submit]:active{box-shadow:0 0 0 6px var(--glow-color)}';
        $css .= '@media(prefers-reduced-motion:reduce){*,*::before,*::after{transition:none!important;animation:none!important}}';
        return '<style id="goldensite-theme-style" data-theme="'.htmlspecialchars($id, ENT_QUOTES, 'UTF-8').'">'.$css.$p['custom_css'].'</style>';
    }
    public static function decorate(string $html): string
    {
        if (str_contains($html, 'id="goldensite-theme-style"')) return $html;
        $head = stripos($html, '</head>');
        return $head === false ? $html : substr_replace($html, self::head(), $head, 0);
    }
}
