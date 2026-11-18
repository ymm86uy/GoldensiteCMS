<?php
declare(strict_types=1);

namespace Modules\GoldensiteForum;

use App\Core\{Auth, ConsoleView, Csrf, Database, ErrorPage, FrontendEngine, RateLimiter, RequestGuard, Settings};
use PDO;

final class Forum
{
    public static function db(): Database { return Database::getInstance(); }

    public static function transaction(callable $work): mixed
    {
        $pdo = self::db()->getConnection();
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $error;
        }
    }

    public static function enabled(): bool
    {
        if (FrontendEngine::isModuleActive('forum')) {
            header('Cache-Control: no-store, private');
            return true;
        }
        ErrorPage::notFound();
        return false;
    }

    public static function canParticipate(): bool
    {
        return Auth::check() && (Appearance::get()['access']==='members'
            || \App\Core\IdentityAccess::allows('forum.participate')
            || \App\Core\IdentityAccess::allows('forum.moderate'));
    }

    public static function requireParticipant(): void
    {
        Auth::requireAuth();
        if (!self::canParticipate()) {
            http_response_code(403);
            self::render('error',['error'=>'Tu cuenta no tiene permiso para participar en el foro.','back'=>'/forum'],'Acceso al foro');
            exit;
        }
    }

    public static function writer(bool $publication = false, bool $participation = true): int
    {
        Auth::requireAuth();
        RequestGuard::post();
        if ($participation) self::requireParticipant();
        $id = (int) Auth::id();
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        if (!RateLimiter::consume('forum-write:'.$id, 30, 60)
            || ($publication && (!RateLimiter::consume('forum-publish:'.$id, 5, 60) || !RateLimiter::consume('forum-ip:'.$ip, 25, 60)))) {
            http_response_code(429);
            exit('Espera un minuto antes de volver a publicar.');
        }
        return $id;
    }

    public static function field(string $key, int $length, bool $required = true): string
    {
        $value = $_POST[$key] ?? '';
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $length
            || str_contains($value, "\0") || ($required && trim($value) === '')) {
            throw new \InvalidArgumentException('Revisa el campo '.$key.'.');
        }
        return trim($value);
    }

    public static function slug(string $title): string
    {
        $title = strtr(mb_strtolower($title), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n']);
        return trim(substr(preg_replace('/[^a-z0-9]+/', '-', $title), 0, 110), '-') ?: 'tema';
    }

    public static function validatePublication(string $body): void
    {
        foreach (self::lines(Settings::get('forum_blocked_words', '')) as $word) {
            if (preg_match('/(?<![\p{L}\p{N}])'.preg_quote($word, '/').'(?![\p{L}\p{N}])/iu', $body)) {
                throw new \InvalidArgumentException('El texto contiene un término restringido por la comunidad.');
            }
        }
    }

    public static function lines(string $text): array
    {
        return array_values(array_unique(array_filter(array_map('trim', preg_split('/\R/u', $text)), static fn($s) => $s !== '')));
    }

    public static function csrf(): string
    {
        return '<input type="hidden" name="csrf_token" value="'.ConsoleView::escape(Csrf::generate()).'">';
    }

    public static function render(string $view, array $data, string $siteTitle, bool $admin = false): void
    {
        $branding=Appearance::get();
        $participant=self::canParticipate();
        if (!$admin) $siteTitle=str_replace('Goldensite Forum',$branding['name'],$siteTitle);
        extract($data, EXTR_SKIP);
        $e = ConsoleView::escape(...);
        $csrf = Auth::check() ? self::csrf() : '';
        ob_start();
        require __DIR__.'/Views/'.$view.'.php';
        $content = ob_get_clean();
        $siteSettings = FrontendEngine::getSettings();
        $root = \App\Core\ForumDistribution::projectRoot();
        $forumAssets = __DIR__.'/Views/meta.php';
        $title = $siteTitle;
        require $root.'/views/layouts/'.($admin ? 'admin' : 'main').'.php';
    }

    public static function redirect(string $url): void { header('Location: '.$url, true, 303); }

    public static function invalid(\InvalidArgumentException $error, string $back): void
    {
        http_response_code(422);
        self::render('error', ['error'=>$error->getMessage(),'back'=>$back], 'Revisa la publicación');
    }
}

