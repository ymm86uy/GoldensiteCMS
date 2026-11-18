<?php

namespace App\Core;

class View {
    /**
     * Renderiza una plantilla de vista inyectándola dentro de un layout base.
     *
     * @param string $view Ruta relativa de la vista dentro de /views/ (ej. 'admin/dashboard')
     * @param array $data Datos pasados a la vista
     * @param string|null $layout Nombre del layout dentro de /views/layouts/ o null para renderizado sin plantilla
     */
    public static function render(string $view, array $data = [], ?string $layout = 'main'): void {
        if (!preg_match('~^[A-Za-z0-9_-]+(?:[/.][A-Za-z0-9_-]+)*$~D', $view) || ($layout !== null && !preg_match('/^[A-Za-z0-9_-]+$/D', $layout))) {
            throw new \InvalidArgumentException('Plantilla inválida.');
        }
        if ($view==='errors/404') { ErrorPage::notFound(); return; }
        // Extrae el arreglo $data en variables individuales
        extract($data, EXTR_SKIP);

        $viewFile = __DIR__ . "/../../views/" . str_replace('.', '/', $view) . ".php";
        $layoutFile = $layout ? __DIR__ . "/../../views/layouts/{$layout}.php" : null;

        if (!file_exists($viewFile)) {
            http_response_code(500);
            throw new \RuntimeException('No se encontró la plantilla solicitada.');
        }

        // Captura el contenido HTML renderizado de la vista
        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        // Si existe un layout, renderiza envolviendo el $content
        if ($layoutFile && file_exists($layoutFile)) {
            require $layoutFile;
        } else {
            echo $content;
        }
    }

    /**
     * Helper de sanitización rápida para prevenir ataques XSS al imprimir variables.
     */
    public static function e(mixed $value): string {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
    }
}
