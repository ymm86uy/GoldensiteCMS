<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Controllers;

use App\Core\{Auth,ErrorPage};
use Modules\GoldensiteForum\{Forum,Notifications};
use Modules\GoldensiteForum\Models\{CategoryModel,TopicModel};

final class ForumController
{
    public function index(): void
    {
        if (Forum::enabled()) Forum::render('index',['boards'=>CategoryModel::all()],'Goldensite Forum');
    }

    public function board(string $slug): void
    {
        if (!Forum::enabled()) return;
        $board=CategoryModel::board($slug);
        if (!$board) { ErrorPage::notFound(); return; }
        $tag=is_string($_GET['tag']??null) ? mb_substr($_GET['tag'],0,40) : '';
        $cursor=[];
        if (is_string($_GET['before']??null) && preg_match('/^([01]),([0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}),([0-9]{1,18})$/D',$_GET['before'],$m)) $cursor=array_slice($m,1);
        if ($tag!=='' || $cursor) header('X-Robots-Tag: noindex');
        $topics=TopicModel::listing((int)$board['id'],$cursor,$tag);
        $next='';
        if (count($topics)>25) {
            array_pop($topics);
            $last=end($topics);
            $next=http_build_query(['tag'=>$tag,'before'=>implode(',',[$last['pinned'],$last['active_at'],$last['id']])]);
        }
        Forum::render('board',compact('board','tag','topics','next'),$board['name'].' · Goldensite Forum');
    }

    public function tags(): void
    {
        if (!Forum::enabled()) return;
        $term=$_GET['q']??'';
        $result=[];
        if (!\App\Core\RateLimiter::consume('forum-tags:'.($_SERVER['REMOTE_ADDR']??''),60,60)) { http_response_code(429); self::json($result); return; }
        if (is_string($term) && preg_match('/^[\p{L}\p{N} -]{1,40}$/uD',$term)) {
            $result=array_column(Forum::db()->fetchAll('SELECT g.name FROM goldensite_forum_tags g WHERE g.name LIKE ?
                AND EXISTS (SELECT 1 FROM goldensite_forum_topic_tags x JOIN goldensite_forum_topics t ON t.id=x.topic_id AND t.deleted_at IS NULL WHERE x.tag_id=g.id) ORDER BY g.name LIMIT 12',[$term.'%']),'name');
        }
        self::json($result);
    }

    public function preview(): void
    {
        if (!Forum::enabled()) return;
        Forum::writer();
        try { self::json(['html'=>\Modules\GoldensiteForum\Markdown::render(Forum::field('body',12000,false))]); }
        catch (\InvalidArgumentException) { http_response_code(422); self::json(['error'=>'Texto demasiado largo.']); }
    }

    public function notifications(): void
    {
        if (!Forum::enabled()) return;
        Auth::requireAuth();
        header('X-Robots-Tag: noindex');
        $user=(int)Auth::id();
        $preference=Forum::db()->fetch('SELECT direct_email FROM goldensite_forum_preferences WHERE user_id=?',[$user]);
        Forum::render('notifications',['rows'=>Notifications::inbox($user),'directEmail'=>(bool)($preference['direct_email']??false)],'Notificaciones del foro');
    }

    public function unread(): void
    {
        if (!Forum::enabled()) return;
        if (!Auth::check()) { http_response_code(401); self::json(['unread'=>0]); return; }
        $row=Forum::db()->fetch('SELECT COUNT(*) unread FROM goldensite_forum_notifications n
            JOIN goldensite_forum_topics t ON t.id=n.topic_id AND t.deleted_at IS NULL
            LEFT JOIN goldensite_forum_replies r ON r.id=n.reply_id
            WHERE n.user_id=? AND n.read_at IS NULL AND (n.reply_id IS NULL OR (r.topic_id=t.id AND r.deleted_at IS NULL))',[(int)Auth::id()]);
        self::json(['unread'=>(int)$row['unread']]);
    }

    public function read(): void
    {
        if (!Forum::enabled()) return;
        $id=Forum::writer(false,false);
        Forum::db()->execute('UPDATE goldensite_forum_notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL',[$id]);
        Forum::redirect('/forum/notifications');
    }

    public function preferences(): void
    {
        if (!Forum::enabled()) return;
        $user=Forum::writer(false,false);
        Forum::db()->execute('INSERT INTO goldensite_forum_preferences (user_id,direct_email) VALUES (?,?) ON DUPLICATE KEY UPDATE direct_email=VALUES(direct_email)',
            [$user,(int)(($_POST['direct_email']??'')==='1')]);
        Forum::redirect('/forum/notifications');
    }

    public function assets(string $type): void
    {
        if (!Forum::enabled()) return;
        $file=match($type) {'style'=>'forum.css','script'=>'forum.js',default=>null};
        if (!$file) { ErrorPage::notFound(); return; }
        header('Content-Type: '.($type==='style'?'text/css':'text/javascript').'; charset=utf-8');
        readfile(dirname(__DIR__).'/Assets/'.$file);
    }

    private static function json(array $value): void
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($value,JSON_THROW_ON_ERROR);
    }
}

