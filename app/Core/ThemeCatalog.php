<?php
namespace App\Core;
final class ThemeCatalog
{
    public static function all(): array {
        return [
            ['folder' => 'cyberpunk-cyan', 'name' => 'Cyberpunk Cyan Matrix', 'description' => 'Interfaz oscura cibernética con bordes cian neón incandescentes y tarjetas traslúcidas.', 'bg_color' => '#07090e', 'accent_color' => '#00f3ff'],
            ['folder' => 'dark-glass-violet', 'name' => 'Dark Glassmorphism Violet', 'description' => 'Ecosistema de cristal flotante con efectos de desenfoque y magenta resplandeciente.', 'bg_color' => '#0a0518', 'accent_color' => '#ff007f'],
            ['folder' => 'neon-emerald', 'name' => 'High-Tech Neon Emerald', 'description' => 'Consola táctica de alto rendimiento con detalles en verde esmeralda neón.', 'bg_color' => '#050c08', 'accent_color' => '#00ff66'],
            
            // --- NUEVOS 10 TEMAS ESPECIALIZADOS ---
            ['folder' => 'sports-pro', 'name' => 'Sports & Athletics Pro', 'description' => 'Estética de alto impacto dinámico con neón naranja para portales deportivos.', 'bg_color' => '#0d1117', 'accent_color' => '#ff5500'],
            ['folder' => 'health-medical', 'name' => 'Health & Medical Care', 'description' => 'Diseño limpio y confiable en tono cian sanitario para clínicas y salud.', 'bg_color' => '#0a192f', 'accent_color' => '#00d2ff'],
            ['folder' => 'edu-academic', 'name' => 'Education & Campus', 'description' => 'Tema azul marino académico con acentos dorados para instituciones educativas.', 'bg_color' => '#0b132b', 'accent_color' => '#e0a96d'],
            ['folder' => 'culture-art', 'name' => 'Culture & Gallery Art', 'description' => 'Entorno artístico refinado en magenta profundo y acentos de oro rosa.', 'bg_color' => '#1a092b', 'accent_color' => '#f39c12'],
            ['folder' => 'gym-fitness', 'name' => 'Gym & Extreme Fitness', 'description' => 'Diseño industrial en negro y verde volt neón enfocado en rendimiento físico.', 'bg_color' => '#050505', 'accent_color' => '#ccff00'],
            ['folder' => 'transport-logistics', 'name' => 'Transport & Logistics', 'description' => 'Plantilla técnica y robusta para flotas, envíos y empresas de transporte.', 'bg_color' => '#0f172a', 'accent_color' => '#fbbf24'],
            ['folder' => 'sales-retail', 'name' => 'Sales & Retail Direct', 'description' => 'Enfocado en catálogos de venta con tonos esmeralda de alta conversión.', 'bg_color' => '#130026', 'accent_color' => '#10b981'],
            ['folder' => 'real-estate', 'name' => 'Real Estate & Properties', 'description' => 'Apariencia ejecutiva en bronce sobrio para inmobiliarias y arquitectura.', 'bg_color' => '#111827', 'accent_color' => '#d97706'],
            ['folder' => 'gourmet-food', 'name' => 'Gourmet & Restaurant', 'description' => 'Paleta borgoña y fuego cálido ideal para gastronomía y reservas.', 'bg_color' => '#1c0a00', 'accent_color' => '#ef4444'],
            ['folder' => 'tech-ai-future', 'name' => 'Tech & AI Future', 'description' => 'Línea cibernética con degradados púrpuras para startups y ciencia de datos.', 'bg_color' => '#020817', 'accent_color' => '#a855f7'],

            // --- TEMAS CLÁSICOS DE GOLDENSITE ---
            ['folder' => 'dark-cyber', 'name' => 'Dark Cyber (Default)', 'description' => 'Tema oscuro futurista nativo de GoldenSiteCMS.', 'bg_color' => '#070913', 'accent_color' => '#38bdf8'],
            ['folder' => 'agency-creative', 'name' => 'Agency Creative', 'description' => 'Diseño moderno y dinámico para agencias.', 'bg_color' => '#0f172a', 'accent_color' => '#6366f1'],
            ['folder' => 'cyber-sec', 'name' => 'Cyber Sec', 'description' => 'Interfaz enfocada en ciberseguridad.', 'bg_color' => '#020617', 'accent_color' => '#22c55e'],
            ['folder' => 'e-store', 'name' => 'E-Store Engine', 'description' => 'Maquetación optimizada para catálogo digital.', 'bg_color' => '#1e1b4b', 'accent_color' => '#f59e0b'],
            ['folder' => 'landing-lead', 'name' => 'Landing Lead', 'description' => 'Enfocado en alta conversión.', 'bg_color' => '#0f172a', 'accent_color' => '#ec4899'],
            ['folder' => 'magazine-news', 'name' => 'Magazine News', 'description' => 'Estilo periódico y comunidad.', 'bg_color' => '#18181b', 'accent_color' => '#ef4444'],
            ['folder' => 'minimal-blog', 'name' => 'Minimal Blog', 'description' => 'Lectura limpia y sobria.', 'bg_color' => '#0f172a', 'accent_color' => '#94a3b8'],
            ['folder' => 'saas-modern', 'name' => 'SaaS Modern', 'description' => 'Apariencia limpia para productos tecnológicos.', 'bg_color' => '#090d16', 'accent_color' => '#a855f7']
        ];

    }
    public static function palettes(): array {
return [
    'cyberpunk-cyan'    => ['bg' => '#07090e', 'card' => 'rgba(15, 23, 42, 0.85)', 'accent' => '#00f3ff', 'glow' => 'rgba(0, 243, 255, 0.4)'],
    'dark-glass-violet' => ['bg' => '#0a0518', 'card' => 'rgba(30, 15, 50, 0.8)', 'accent' => '#ff007f', 'glow' => 'rgba(255, 0, 127, 0.4)'],
    'neon-emerald'      => ['bg' => '#050c08', 'card' => 'rgba(10, 30, 20, 0.85)', 'accent' => '#00ff66', 'glow' => 'rgba(0, 255, 102, 0.4)'],
    'sports-pro'          => ['bg' => '#0d1117', 'card' => 'rgba(22, 27, 34, 0.85)', 'accent' => '#ff5500', 'glow' => 'rgba(255, 85, 0, 0.4)'],
    'health-medical'      => ['bg' => '#0a192f', 'card' => 'rgba(17, 34, 64, 0.85)', 'accent' => '#00d2ff', 'glow' => 'rgba(0, 210, 255, 0.4)'],
    'edu-academic'        => ['bg' => '#0b132b', 'card' => 'rgba(28, 37, 65, 0.85)', 'accent' => '#e0a96d', 'glow' => 'rgba(224, 169, 109, 0.4)'],
    'culture-art'         => ['bg' => '#1a092b', 'card' => 'rgba(38, 14, 61, 0.85)', 'accent' => '#f39c12', 'glow' => 'rgba(243, 156, 18, 0.4)'],
    'gym-fitness'         => ['bg' => '#050505', 'card' => 'rgba(20, 20, 20, 0.85)', 'accent' => '#ccff00', 'glow' => 'rgba(204, 255, 0, 0.4)'],
    'transport-logistics' => ['bg' => '#0f172a', 'card' => 'rgba(30, 41, 59, 0.85)', 'accent' => '#fbbf24', 'glow' => 'rgba(251, 191, 36, 0.4)'],
    'sales-retail'        => ['bg' => '#130026', 'card' => 'rgba(34, 4, 64, 0.85)', 'accent' => '#10b981', 'glow' => 'rgba(16, 185, 129, 0.4)'],
    'real-estate'         => ['bg' => '#111827', 'card' => 'rgba(31, 41, 55, 0.85)', 'accent' => '#d97706', 'glow' => 'rgba(217, 119, 6, 0.4)'],
    'gourmet-food'        => ['bg' => '#1c0a00', 'card' => 'rgba(46, 17, 0, 0.85)', 'accent' => '#ef4444', 'glow' => 'rgba(239, 68, 68, 0.4)'],
    'tech-ai-future'      => ['bg' => '#020817', 'card' => 'rgba(15, 23, 42, 0.85)', 'accent' => '#a855f7', 'glow' => 'rgba(168, 85, 247, 0.4)'],
    'dark-cyber'        => ['bg' => '#070913', 'card' => '#0f172a', 'accent' => '#38bdf8', 'glow' => 'rgba(56, 189, 248, 0.3)'],
    'agency-creative'   => ['bg' => '#0f172a', 'card' => '#1e293b', 'accent' => '#6366f1', 'glow' => 'rgba(99, 102, 241, 0.3)'],
    'cyber-sec'         => ['bg' => '#020617', 'card' => '#0f172a', 'accent' => '#22c55e', 'glow' => 'rgba(34, 197, 94, 0.3)'],
    'e-store'           => ['bg' => '#1e1b4b', 'card' => '#312e81', 'accent' => '#f59e0b', 'glow' => 'rgba(245, 158, 11, 0.3)'],
    'landing-lead'      => ['bg' => '#0f172a', 'card' => '#1e293b', 'accent' => '#ec4899', 'glow' => 'rgba(236, 72, 153, 0.3)'],
    'magazine-news'     => ['bg' => '#18181b', 'card' => '#27272a', 'accent' => '#ef4444', 'glow' => 'rgba(239, 68, 68, 0.3)'],
    'minimal-blog'      => ['bg' => '#0f172a', 'card' => '#1e293b', 'accent' => '#94a3b8', 'glow' => 'rgba(148, 163, 184, 0.3)'],
    'saas-modern'       => ['bg' => '#090d16', 'card' => '#151c2c', 'accent' => '#a855f7', 'glow' => 'rgba(168, 85, 247, 0.3)']
];
    }
    public static function valid(string $id): bool { return isset(self::palettes()[$id]); }
}
