<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Controllers;

use App\Core\{Auth,Environment,ErrorPage,IdentityAccess};
use Modules\GoldensiteForum\{Attachments,Forum};
use Modules\GoldensiteForum\Models\{CategoryModel,TopicModel,ReplyModel};

final class TopicController
{
    public function show(string $id, string $slug): void
    {
        if (!Forum::enabled()) return;
        $topic=TopicModel::find((int)$id);
        if (!$topic || $id!==(string)$topic['id']) { ErrorPage::notFound(); return; }
        if ($topic['deleted_at']) {
            if ($topic['merged_into']) {
                $target=TopicModel::find((int)$topic['merged_into']);
                if ($target && !$target['deleted_at']) { header('Location: '.TopicModel::url($target),true,301); return; }
            }
            ErrorPage::notFound(); return;
        }
        if ($slug!==$topic['slug']) { header('Location: '.TopicModel::url($topic),true,301); return; }
        $db=Forum::db();
        $after=filter_var($_GET['after']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]])?:0;
        if ($after) header('X-Robots-Tag: noindex, follow');
        $replies=ReplyModel::listing((int)$id,$after);
        $next=0;
        if (count($replies)>25) { array_pop($replies); $next=(int)end($replies)['id']; }
        $solution=$topic['solution_id'] ? ReplyModel::visible((int)$id,(int)$topic['solution_id']) : null;
        $attachments=Attachments::listing((int)$id,$replies);
        $tags=$db->fetchAll('SELECT g.name FROM goldensite_forum_topic_tags x JOIN goldensite_forum_tags g ON g.id=x.tag_id WHERE x.topic_id=? ORDER BY g.name',[$id]);
        $options=$topic['kind']==='poll' ? $db->fetchAll('SELECT o.id,o.label,COUNT(v.option_id) votes FROM goldensite_forum_poll_options o LEFT JOIN goldensite_forum_votes v ON v.topic_id=o.topic_id AND v.option_id=o.id WHERE o.topic_id=? GROUP BY o.id,o.label ORDER BY o.id',[$id]) : [];
        $user=(int)Auth::id();
        $follow=$user ? $db->fetch('SELECT email_enabled FROM goldensite_forum_follows WHERE topic_id=? AND user_id=?',[$id,$user]) : null;
        $voted=$options && $user && $db->fetch('SELECT user_id FROM goldensite_forum_votes WHERE topic_id=? AND user_id=?',[$id,$user]);
        $board=$db->fetch('SELECT name,slug FROM goldensite_forum_boards WHERE id=?',[$topic['board_id']]);
        $canSolve=$user && ($user===(int)$topic['author_id'] || IdentityAccess::allows('forum.moderate'));
        $meta=['description'=>mb_substr(preg_replace('/\s+/u',' ',$topic['body']),0,160)];
        $base=rtrim(Environment::get('APP_URL'),'/');
        if (filter_var($base,FILTER_VALIDATE_URL) && preg_match('~^https?://[^/?#@]+$~D',$base)) {
            $meta['url']=$base.TopicModel::url($topic);
            $meta['structured']=['@context'=>'https://schema.org','@type'=>'DiscussionForumPosting','headline'=>$topic['title'],
                'articleBody'=>$topic['body'],'datePublished'=>gmdate(DATE_ATOM,strtotime($topic['created_at'].' UTC')),'url'=>$meta['url']];
            if ($topic['username']) $meta['structured']['author']=['@type'=>'Person','name'=>$topic['username']];
        }
        Forum::render('topic',compact('topic','replies','next','solution','attachments','tags','options','follow','voted','board','canSolve','user','meta'),$topic['title'].' · Goldensite Forum');
    }

    public function create(string $slug): void
    {
        if (!Forum::enabled()) return;
        Forum::requireParticipant();
        $board=CategoryModel::board($slug);
        if (!$board) { ErrorPage::notFound(); return; }
        header('X-Robots-Tag: noindex');
        Forum::render('create',compact('board'),'Crear hilo · Goldensite Forum');
    }

    public function store(string $slug): void
    {
        if (!Forum::enabled()) return;
        $id=Forum::writer(true);
        $board=CategoryModel::board($slug);
        if (!$board) { ErrorPage::notFound(); return; }
        $written=[];
        try {
            $title=Forum::field('title',160);
            $body=Forum::field('body',12000);
            $kind=Forum::field('kind',16);
            if (!isset(TopicModel::KINDS[$kind]) || ($kind==='announcement' && !IdentityAccess::allows('forum.moderate'))) throw new \InvalidArgumentException('Selecciona un tipo de hilo permitido.');
            $tags=array_values(array_unique(array_filter(array_map(static fn($tag)=>mb_strtolower(trim($tag)),explode(',',Forum::field('tags',205,false))),static fn($tag)=>$tag!=='')));
            if (count($tags)>5) throw new \InvalidArgumentException('Usa como máximo cinco etiquetas.');
            foreach ($tags as $tag) if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} -]{0,39}$/uD',$tag)) throw new \InvalidArgumentException('Una etiqueta contiene caracteres inválidos.');
            $options=$kind==='poll' ? Forum::lines(Forum::field('options',1210)) : [];
            if ($kind==='poll' && (count($options)<2 || count($options)>10)) throw new \InvalidArgumentException('Añade entre dos y diez opciones distintas.');
            foreach ($options as $option) if (mb_strlen($option)>120) throw new \InvalidArgumentException('Cada opción admite 120 caracteres.');
            Forum::validatePublication(implode("\n",[$title,$body,...$options,...$tags]));
            $topic=Forum::transaction(static function () use ($board,$id,$title,$body,$kind,$tags,$options,&$written): array {
                $topic=TopicModel::create((int)$board['id'],$id,$title,$body,$kind,$tags,$options);
                Attachments::store($topic['id'],null,$written);
                return $topic;
            });
            Forum::redirect(TopicModel::url($topic));
        } catch (\Throwable $error) {
            foreach ($written as $path) @unlink($path);
            if (!$error instanceof \InvalidArgumentException) throw $error;
            Forum::invalid($error,'/forum/board/'.$board['slug'].'/new');
        }
    }

    public function action(string $id, string $slug): void
    {
        if (!Forum::enabled()) return;
        $actor=Forum::writer(false,($_POST['action']??'')!=='follow');
        $topic=TopicModel::find((int)$id);
        if (!$topic || $id!==(string)$topic['id'] || $topic['deleted_at']) { ErrorPage::notFound(); return; }
        $url=TopicModel::url($topic);
        try {
            TopicModel::interaction((int)$id,$actor,Forum::field('action',16),max(0,(int)Forum::field('target',18)));
            Forum::redirect($url);
        } catch (\InvalidArgumentException $error) { Forum::invalid($error,$url); }
    }
}

