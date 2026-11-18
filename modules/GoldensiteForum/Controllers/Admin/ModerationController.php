<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Controllers\Admin;

use App\Core\{AdminPath,ConsoleView,IdentityAccess,Settings};
use Modules\GoldensiteForum\Forum;
use Modules\GoldensiteForum\Models\{CategoryModel,ReplyModel,TopicModel};

final class ModerationController
{
    public function index(): void
    {
        if (!Forum::enabled()) return;
        if (!IdentityAccess::allows('forum.moderate')) { http_response_code(403); return; }
        $db=Forum::db();
        $url=AdminPath::url('/admin/forum');
        $error='';
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            Forum::writer(false,false);
            try {
                $action=Forum::field('action',24);
                if ($action==='board') CategoryModel::add(Forum::field('category',100),Forum::field('board',100));
                elseif ($action==='filter') Settings::set('forum_blocked_words',implode("\n",Forum::lines(Forum::field('words',2000,false))));
                elseif ($action==='appearance') {
                    if (!IdentityAccess::superuser() && !IdentityAccess::allows('appearance.write')) { http_response_code(403); return; }
                    \Modules\GoldensiteForum\Appearance::save();
                } else $this->moderate($action);
                ConsoleView::audit('forum_moderation','Acción de foro: '.$action);
                Forum::redirect($url);
                return;
            } catch (\InvalidArgumentException $e) { http_response_code(422); $error=$e->getMessage(); }
        }
        $after=max(0,(int)filter_var($_GET['before']??0,FILTER_VALIDATE_INT));
        $topics=$db->fetchAll('SELECT t.id,t.title,t.slug,t.deleted_at,t.merged_into,t.closed,t.pinned,b.name board FROM goldensite_forum_topics t
            JOIN goldensite_forum_boards b ON b.id=t.board_id WHERE (?=0 OR t.id<?) ORDER BY t.id DESC LIMIT 51',[$after,$after]);
        $next=count($topics)>50 ? (int)$topics[49]['id'] : 0;
        $topics=array_slice($topics,0,50);
        $boards=CategoryModel::all();
        $topicId=max(0,(int)filter_var($_GET['replies']??0,FILTER_VALIDATE_INT));
        $replyAfter=max(0,(int)filter_var($_GET['reply_before']??0,FILTER_VALIDATE_INT));
        $replies=$topicId ? $db->fetchAll('SELECT id,body,deleted_at FROM goldensite_forum_replies WHERE topic_id=? AND (?=0 OR id<?) ORDER BY id DESC LIMIT 51',[$topicId,$replyAfter,$replyAfter]) : [];
        $replyNext=count($replies)>50 ? (int)$replies[49]['id'] : 0;
        $replies=array_slice($replies,0,50);
        $words=Settings::get('forum_blocked_words','');
        Forum::render('moderation',compact('url','error','topics','boards','next','replies','topicId','replyNext','words'),'Moderación · Goldensite Forum',true);
    }

    private function moderate(string $action): void
    {
        $raw=$_POST['topics']??[];
        if (!is_array($raw) || count($raw)>25) throw new \InvalidArgumentException('Selecciona hasta 25 hilos.');
        $ids=[];
        foreach ($raw as $value) {
            if (!is_string($value) || !preg_match('/^[1-9][0-9]{0,17}$/D',$value)) throw new \InvalidArgumentException('Hilo inválido.');
            $ids[]=(int)$value;
        }
        $ids=array_values(array_unique($ids));
        $target=(int)Forum::field('target',18,false);
        $reply=(int)Forum::field('reply',18,false);
        if (!$ids) throw new \InvalidArgumentException('Selecciona un hilo.');
        Forum::transaction(function () use ($action,$ids,$target,$reply): void {
            if ($target<0 || $reply<0) throw new \InvalidArgumentException('Identificador inválido.');
            $locked=$ids;
            if ($action==='merge' && $target>0) $locked[]=$target;
            $locked=array_values(array_unique($locked));
            sort($locked,SORT_NUMERIC);
            $topics=[];
            foreach ($locked as $id) {
                $topics[$id]=TopicModel::find($id,true);
                if (!$topics[$id]) throw new \InvalidArgumentException('Un hilo ya no existe.');
            }
            if ($action==='merge' && ($target<1 || in_array($target,$ids,true) || !$topics[$target] || $topics[$target]['deleted_at'])) throw new \InvalidArgumentException('Selecciona un hilo de destino activo, distinto de los seleccionados.');
            if (in_array($action,['split','delete_reply','restore_reply'],true) && count($ids)!==1) throw new \InvalidArgumentException('La acción requiere un solo hilo.');
            $db=Forum::db();
            foreach ($ids as $id) {
                $topic=$topics[$id];
                if ($topic['merged_into'] || ($topic['deleted_at'] && $action!=='restore')) throw new \InvalidArgumentException('Solo se puede restaurar un hilo en papelera que no haya sido fusionado.');
                switch ($action) {
                    case 'close': case 'open':
                        $db->execute('UPDATE goldensite_forum_topics SET closed=? WHERE id=?',[(int)($action==='close'),$id]);
                        break;
                    case 'pin': case 'unpin':
                        $db->execute('UPDATE goldensite_forum_topics SET pinned=? WHERE id=?',[(int)($action==='pin'),$id]);
                        break;
                    case 'move':
                        if (!$db->fetch('SELECT id FROM goldensite_forum_boards WHERE id=?',[$target])) throw new \InvalidArgumentException('Indica un subforo válido.');
                        $db->execute('UPDATE goldensite_forum_topics SET board_id=? WHERE id=?',[$target,$id]);
                        break;
                    case 'delete': case 'restore':
                        $db->execute('UPDATE goldensite_forum_topics SET deleted_at=IF(?=1,NOW(),NULL) WHERE id=?',[(int)($action==='delete'),$id]);
                        break;
                    case 'delete_reply': case 'restore_reply':
                        if (!$db->fetch('SELECT id FROM goldensite_forum_replies WHERE id=? AND topic_id=?',[$reply,$id])) throw new \InvalidArgumentException('Respuesta inválida.');
                        $db->execute('UPDATE goldensite_forum_replies SET deleted_at=IF(?=1,NOW(),NULL) WHERE id=?',[(int)($action==='delete_reply'),$reply]);
                        if ($action==='delete_reply') $db->execute('UPDATE goldensite_forum_topics SET solution_id=NULL WHERE id=? AND solution_id=?',[$id,$reply]);
                        break;
                    case 'merge':
                        if ($topic['kind']==='poll') throw new \InvalidArgumentException('Las encuestas conservan sus resultados en su propio hilo.');
                        $db->execute('INSERT INTO goldensite_forum_replies (topic_id,author_id,body,created_at) VALUES (?,?,?,?)',[$target,$topic['author_id'],$topic['body'],$topic['created_at']]);
                        $opening=(int)$db->getConnection()->lastInsertId();
                        $db->execute('UPDATE goldensite_forum_attachments SET reply_id=? WHERE topic_id=? AND reply_id IS NULL',[$opening,$id]);
                        $this->moveReplies($id,$target,0);
                        $db->execute('INSERT IGNORE INTO goldensite_forum_topic_tags (topic_id,tag_id) SELECT ?,tag_id FROM goldensite_forum_topic_tags WHERE topic_id=?',[$target,$id]);
                        $db->execute('UPDATE goldensite_forum_topics SET deleted_at=NOW(),closed=1,solution_id=NULL,merged_into=? WHERE id=?',[$target,$id]);
                        $db->execute('UPDATE goldensite_forum_topics SET merged_into=? WHERE merged_into=?',[$target,$id]);
                        break;
                    case 'split':
                        $start=ReplyModel::visible($id,$reply);
                        if (!$start) throw new \InvalidArgumentException('Selecciona una respuesta visible como inicio de la división.');
                        $title=Forum::field('title',160);
                        $db->execute("INSERT INTO goldensite_forum_topics (board_id,author_id,title,slug,body,kind,created_at) VALUES (?,?,?,?,?,'discussion',?)",
                            [$topic['board_id'],$start['author_id'],$title,Forum::slug($title),'Conversación separada por moderación.',$start['created_at']]);
                        $new=(int)$db->getConnection()->lastInsertId();
                        $this->moveReplies($id,$new,$reply);
                        $db->execute('UPDATE goldensite_forum_topics SET solution_id=NULL WHERE id=? AND solution_id>=?',[$id,$reply]);
                        break;
                    default:
                        throw new \InvalidArgumentException('Acción de moderación desconocida.');
                }
            }
        });
    }

    private function moveReplies(int $source, int $target, int $from): void
    {
        $db=Forum::db();
        $db->execute('UPDATE goldensite_forum_attachments SET topic_id=? WHERE topic_id=? AND reply_id>=?',[$target,$source,$from]);
        $db->execute('UPDATE goldensite_forum_notifications SET topic_id=? WHERE topic_id=? AND reply_id>=?',[$target,$source,$from]);
        $db->execute('UPDATE goldensite_forum_replies SET topic_id=? WHERE topic_id=? AND id>=?',[$target,$source,$from]);

        $db->execute('UPDATE goldensite_forum_topics SET active_at=NOW() WHERE id=?',[$target]);
    }
}

