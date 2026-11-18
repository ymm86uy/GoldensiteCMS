<?php
declare(strict_types=1);
namespace Modules\GoldensiteForum\Controllers;

use App\Core\ErrorPage;
use Modules\GoldensiteForum\{Attachments,Forum};
use Modules\GoldensiteForum\Models\{TopicModel,ReplyModel};

final class ReplyController
{
    public function store(string $id, string $slug): void
    {
        if (!Forum::enabled()) return;
        $actor=Forum::writer(true);
        $topic=TopicModel::find((int)$id);
        if (!$topic || $id!==(string)$topic['id'] || $topic['deleted_at']) { ErrorPage::notFound(); return; }
        $url=TopicModel::url($topic);
        $written=[];
        try {
            $body=Forum::field('body',12000);
            Forum::validatePublication($body);
            $reply=Forum::transaction(static function () use ($id,$actor,$body,&$written): int {
                $topic=TopicModel::live((int)$id,true);
                $reply=ReplyModel::create($topic,$actor,$body);
                Attachments::store((int)$id,$reply,$written);
                return $reply;
            });
            Forum::redirect($url.'?after='.($reply-1).'#reply-'.$reply);
        } catch (\Throwable $error) {
            foreach ($written as $path) @unlink($path);
            if (!$error instanceof \InvalidArgumentException) throw $error;
            Forum::invalid($error,$url);
        }
    }

    public function react(string $id, string $slug): void
    {
        if (!Forum::enabled()) return;
        $actor=Forum::writer();
        $topic=TopicModel::find((int)$id);
        if (!$topic || $id!==(string)$topic['id'] || $topic['deleted_at']) { ErrorPage::notFound(); return; }
        $url=TopicModel::url($topic);
        try {
            $reply=(int)Forum::field('reply',18);
            ReplyModel::react((int)$id,$reply,$actor,Forum::field('reaction',12));
            Forum::redirect($url.'?after='.($reply-1).'#reply-'.$reply);
        } catch (\InvalidArgumentException $error) { Forum::invalid($error,$url); }
    }
}

