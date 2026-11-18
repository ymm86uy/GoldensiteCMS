<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

use App\Core\{Auth,ConsoleView,Csrf,ErrorPage,FrontendEngine,Settings};

final class Controller
{
    public function page(): void
    {
        if (!FrontendEngine::isModuleActive('social-network')) { ErrorPage::notFound(); return; }
        if (!Auth::check()) { header('Location: /login?return_to=%2Fsocial'); return; }
        header('X-Frame-Options: DENY');
        header('Cache-Control: no-store, private');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
        header("Content-Security-Policy: frame-ancestors 'none'; base-uri 'self'; object-src 'none'; form-action 'self'; media-src 'self'; connect-src 'self'");
        $siteTitle='GoldensiteCMS Social Network';
        $csrf=Csrf::generate();
        $e=ConsoleView::escape(...);
        $regions=array_keys(Access::regions());
        sort($regions);
        ob_start();
        require __DIR__.'/view.php';
        $content=ob_get_clean();
        require dirname(__DIR__,2).'/views/layouts/main.php';
    }

    public function feed(): void
    {
        $viewer=Access::enter();
        if (!$viewer) return;
        $before=filter_var($_GET['before']??0,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if ($before===false) { Access::json(['error'=>'Cursor inválido.'],422); return; }
        Access::json(Store::feed($before,$viewer));
    }

    public function map(): void
    {
        if (!Access::enter()) return;
        Access::json(['regions'=>Store::db()->fetchAll('SELECT region,COUNT(*) count FROM goldensite_social_presence
            WHERE expires_at>UTC_TIMESTAMP() GROUP BY region HAVING COUNT(*)>=3 ORDER BY region'),'echoes'=>Store::map()]);
    }

    public function presence(): void
    {
        $user=Access::enter(true);
        if (!$user) return;
        $this->mutate(static function () use ($user): array {
            $region=Access::field('region',2,false);
            if ($region==='') Store::db()->execute('DELETE FROM goldensite_social_presence WHERE user_id=?',[$user]);
            else Store::db()->execute('INSERT INTO goldensite_social_presence (user_id,region,expires_at)
                VALUES (?,?,UTC_TIMESTAMP()+INTERVAL 15 MINUTE) ON DUPLICATE KEY UPDATE region=VALUES(region),expires_at=VALUES(expires_at)',[$user,Access::region($region)]);
            return ['active'=>$region!==''];
        });
    }

    public function publish(): void
    {
        $user=Access::enter(true);
        if (!$user) return;
        $this->mutate(static function () use ($user): array {
            $title=Access::field('title',100);
            $body=Access::field('body',2000);
            [$region,$row,$col]=Location::fromPublication();
            $alt=Access::field('image_alt',160,false);
            $key=$image=null;
            try {
                [$key,$mime]=Audio::store();
                $image=Image::store();
                Store::db()->execute('INSERT INTO goldensite_social_echoes (user_id,title,body,region,zone_row,zone_col,audio_mime,audio_key,image_key,image_alt)
                    VALUES (?,?,?,?,?,?,?,?,?,?)',[$user,$title,$body,$region,$row,$col,$mime,$key,$image,$image===null?null:$alt]);
            } catch (\Throwable $error) {
                Store::removeMedia($key);
                Store::removeMedia($image);
                throw $error;
            }
            return ['id'=>(int)Store::db()->getConnection()->lastInsertId()];
        },201);
    }

    public function reduceLocation(string $id): void
    {
        $user=Access::enter(true,false,false);
        if (!$user) return;
        $this->mutate(static function () use ($id,$user): array {
            $id=self::id($id);
            $mode=Access::field('location_mode',7);
            if (!in_array($mode,['country','none'],true)) throw new \InvalidArgumentException('Elige la privacidad del eco.');
            $statement=Store::db()->getConnection()->prepare('UPDATE goldensite_social_echoes SET
                region=IF(?=1,NULL,region),zone_row=NULL,zone_col=NULL
                WHERE id=? AND user_id=? AND hidden_at IS NULL');
            $statement->execute([(int)($mode==='none'),$id,$user]);
            if (!$statement->rowCount() && !Store::db()->fetch('SELECT id FROM goldensite_social_echoes
                WHERE id=? AND user_id=? AND hidden_at IS NULL',[$id,$user])) throw new \OutOfBoundsException('Eco no disponible.');
            return ['saved'=>true];
        });
    }

    public function echoDetail(string $id): void
    {
        $viewer=Access::enter();
        if (!$viewer) return;
        $this->mutate(static fn(): array => ['echo'=>Store::detail(self::id($id),$viewer)]);
    }

    public function waves(string $id): void
    {
        if (!Access::enter()) return;
        $this->mutate(static function () use ($id): array {
            $id=self::id($id);
            Store::visible($id);
            return ['waves'=>Store::db()->fetchAll('SELECT w.id,w.body,w.created_at,u.username FROM goldensite_social_waves w
                LEFT JOIN users u ON u.id=w.user_id JOIN goldensite_social_echoes e ON e.id=w.echo_id
                WHERE w.echo_id=? AND w.hidden_at IS NULL AND e.hidden_at IS NULL ORDER BY w.id DESC LIMIT 30',[$id])];
        });
    }

    public function wave(string $id): void { $this->interact($id,'wave'); }
    public function resonate(string $id): void { $this->interact($id,'resonate'); }

    private function interact(string $id,string $action): void
    {
        $user=Access::enter(true);
        if (!$user) return;
        $this->mutate(static function () use ($user,$id,$action): array {
            $value=$action==='wave'?Access::field('body',500):filter_var($_POST['seconds']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>5,'max_range'=>120]]);
            if ($value===false) throw new \InvalidArgumentException('La resonancia admite entre 5 y 120 segundos.');
            Store::interaction(self::id($id),$user,$action,$value);
            return ['saved'=>true];
        });
    }

    public function settings(): void
    {
        if (!Access::enter(($_SERVER['REQUEST_METHOD']??'GET')==='POST',true)) return;
        if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
            $this->mutate(static function (): array {
                $action=Access::field('action',18);
                if ($action==='policy') {
                    $policy=Access::field('policy',10);
                    if (!in_array($policy,['members','roles'],true)) throw new \InvalidArgumentException('Política inválida.');
                    Settings::set('social_writers',$policy);
                } elseif (in_array($action,['hide','restore','hide-wave','restore-wave'],true)) {
                    $table=str_ends_with($action,'-wave')?'goldensite_social_waves':'goldensite_social_echoes';
                    Store::db()->execute("UPDATE $table SET hidden_at=IF(?=1,UTC_TIMESTAMP(),NULL) WHERE id=?",[(int)str_starts_with($action,'hide'),self::id(Access::field('id',18))]);
                } else throw new \InvalidArgumentException('Acción inválida.');
                ConsoleView::audit('social_moderation',$action);
                return ['saved'=>true];
            });
            return;
        }
        $rows=Store::db()->fetchAll('SELECT id,title,hidden_at FROM goldensite_social_echoes ORDER BY id DESC LIMIT 30');
        $waves=Store::db()->fetchAll('SELECT id,echo_id,body,hidden_at FROM goldensite_social_waves ORDER BY id DESC LIMIT 30');
        ConsoleView::render('social_network',['rows'=>$rows,'waves'=>$waves,'policy'=>Settings::get('social_writers','members')],'GoldensiteCMS Social Network');
    }

    private static function id(string $id): int
    {
        if (!preg_match('/^[1-9][0-9]{0,17}$/D',$id)) throw new \OutOfBoundsException('Eco no disponible.');
        return (int)$id;
    }

    private function mutate(callable $operation,int $status=200): void
    {
        try { Access::json($operation(),$status); }
        catch (\InvalidArgumentException $error) { Access::json(['error'=>$error->getMessage()],422); }
        catch (\OutOfBoundsException $error) { Access::json(['error'=>$error->getMessage()],404); }
    }
}

