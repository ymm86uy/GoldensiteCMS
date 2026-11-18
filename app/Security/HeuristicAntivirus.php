<?php

namespace App\Security;

class HeuristicAntivirus
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    public static function scanFile(string $filePath, string $originalName = ''): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            return ['status' => 'error', 'score' => 0, 'reasons' => ['Archivo no encontrado']];
        }

        $fileSize = filesize($filePath);
        if ($fileSize === false) return ['status'=>'error','score'=>0,'reasons'=>['No se pudo leer el tamaño del archivo.']];
        if ($fileSize > self::MAX_BYTES) return ['status'=>'error','score'=>0,'reasons'=>['El archivo supera el límite de análisis de 20 MB.']];
        $content = file_get_contents($filePath, false, null, 0, self::MAX_BYTES + 1);
        if ($content === false || strlen($content) !== $fileSize) {
            return ['status'=>'error','score'=>0,'reasons'=>['No se pudo leer el archivo completo.']];
        }
        if (!class_exists(\finfo::class)) return ['status'=>'error','score'=>0,'reasons'=>['El servidor necesita la extensión Fileinfo de PHP para analizar archivos.']];
        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if ($mimeType === false) return ['status'=>'error','score'=>0,'reasons'=>['No se pudo determinar el tipo del archivo.']];
        $hash = hash('sha256', $content);
        $reasons = [];
        $score = 0;

        // 1. Detección de Funciones Peligrosas y Webshells
        $dangerousPatterns = [
            '/\beval\s*\(/i'         => 'Uso sospechoso de eval() [Score +35]',
            '/\bsystem\s*\(/i'       => 'Llamada directa al sistema system() [Score +30]',
            '/\bshell_exec\s*\(/i'   => 'Ejecución de comandos shell_exec() [Score +30]',
            '/\bpassthru\s*\(/i'     => 'Ejecución passthru() [Score +25]',
            '/\bbase64_decode\s*\(/i'=> 'Decodificación Base64 en memoria [Score +20]',
            '/\bassert\s*\(/i'       => 'Evaluación dinámica assert() [Score +20]',
        ];

        foreach ($dangerousPatterns as $pattern => $description) {
            if (preg_match($pattern, $content)) {
                $reasons[] = $description;
                preg_match('/Score \+(\d+)/', $description, $weight);
                $score += (int)($weight[1] ?? 25);
            }
        }
        if (self::hasDirectCommandCall($content)) {
            $reasons[] = 'Llamada directa compatible con Webshell PHP [Score +40]';
            $score += 40;
        }

        // 2. Validación de Magic Bytes vs Extensión
        $ext = strtolower(pathinfo($originalName ?: $filePath, PATHINFO_EXTENSION));
        $imageTypes = ['jpg'=>'image/jpeg', 'jpeg'=>'image/jpeg', 'png'=>'image/png'];
        if (isset($imageTypes[$ext]) && $imageTypes[$ext] !== $mimeType) {
            $reasons[] = "Discrepancia crítica: Extensión .$ext pero MIME real es $mimeType [Score +50]";
            $score += 50;
        }

        if (strpos(strtolower($originalName), '.php.') !== false) {
            $reasons[] = 'Intento de doble extensión detectado (ej. file.php.jpg) [Score +45]';
            $score += 45;
        }

        // Clasiﬁcación de Riesgo
        $level = 'SAFE';
        if ($score >= 70) {
            $level = 'CRITICAL';
        } elseif ($score >= 40) {
            $level = 'HIGH';
        } elseif ($score >= 20) {
            $level = 'MEDIUM';
        }

        return [
            'status'        => 'success',
            'file_name'     => $originalName ?: basename($filePath),
            'hash'          => $hash,
            'size_bytes'    => $fileSize,
            'mime_detected' => $mimeType,
            'score'         => min($score, 100),
            'risk_level'    => $level,
            'reasons'       => $reasons
        ];
    }

    private static function hasDirectCommandCall(string $source): bool
    {
        if (!preg_match('/<\?php\b[\s\S]*?\b(?:exec|system|eval)\b/i', $source)) return false;
        // Preserve conservative detection on large uploads without tokenizing megabytes in the web process.
        if (strlen($source) > 524288) return true;

        $tokens = \PhpToken::tokenize($source);
        $previous = null;
        foreach ($tokens as $index => $token) {
            if ($token->isIgnorable()) continue;
            if ($token->is(T_EVAL)) return true;
            if ($token->is([T_STRING, T_NAME_FULLY_QUALIFIED])
                && in_array(strtolower(ltrim($token->text, '\\')), ['exec', 'system'], true)
                && !in_array($previous, [T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                $next = $index + 1;
                while (isset($tokens[$next]) && $tokens[$next]->isIgnorable()) $next++;
                if (($tokens[$next]->text ?? '') === '(') return true;
            }
            $previous = $token->id;
        }
        return false;
    }
}
