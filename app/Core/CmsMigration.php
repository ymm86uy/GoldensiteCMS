<?php
namespace App\Core;
final class CmsMigration
{
    public static function clean(string $html,bool $allowImages=true): string
    {
        static $purifiers=[];
        if (!isset($purifiers[$allowImages])) {
            $config=\HTMLPurifier_Config::createDefault();
            $config->set('Cache.DefinitionImpl',null);
            $config->set('HTML.Allowed','p,br,strong,b,em,i,u,ul,ol,li,h1,h2,h3,h4,h5,h6,blockquote,pre,code,a[href|title],table,thead,tbody,tr,th,td,div,span,hr'
                .($allowImages?',img[src|alt|width|height]':''));
            $config->set('URI.AllowedSchemes',['http'=>true,'https'=>true,'mailto'=>true]);
            $config->set('HTML.Nofollow',true);
            $purifiers[$allowImages]=new \HTMLPurifier($config);
        }
        return $purifiers[$allowImages]->purify($html);
    }
    public static function plan(string $sql,string $cms,bool $users=false): array {
        if (!in_array($cms,['wordpress','joomla'],true)) throw new \InvalidArgumentException('Selecciona WordPress o Joomla.'); $parsed=CmsDumpReader::read($sql,$cms,$users); $tables=$parsed['tables']; $items=[]; $accounts=[]; $unsupported=0;
        foreach ($tables as $table=>$rows) foreach ($rows as $row) {
            if ($cms==='wordpress'&&isset($row['post_type'],$row['post_title'],$row['post_content'])) {
                if (!in_array($row['post_type'],['post','page'],true)) { $unsupported++; continue; } if (in_array($row['post_status']??'',['trash','auto-draft','inherit'],true)) continue;
                $items[]=['kind'=>$row['post_type'],'source_id'=>(int)($row['ID']??0),'title'=>mb_substr(strip_tags($row['post_title']),0,255),'slug'=>$row['post_name']??'','content'=>$row['post_content'],'status'=>($row['post_status']??'')==='publish'&&empty($row['post_password'])?'published':'draft'];
            } elseif ($cms==='joomla'&&isset($row['introtext'],$row['title'])) $items[]=['kind'=>'post','source_id'=>(int)($row['id']??0),'title'=>mb_substr(strip_tags($row['title']),0,255),'slug'=>$row['alias']??'','content'=>$row['introtext']."\n".($row['fulltext']??''),'status'=>(int)($row['state']??0)===1&&(int)($row['access']??1)===1?'published':'draft'];
            elseif ($users&&(($cms==='wordpress'&&isset($row['user_login'],$row['user_email']))||($cms==='joomla'&&isset($row['username'],$row['email'])))) { $email=$row['user_email']??$row['email']; if (filter_var($email,FILTER_VALIDATE_EMAIL)) $accounts[]=['username'=>$row['user_login']??$row['username'],'email'=>$email,'name'=>$row['display_name']??$row['name']??'']; }
        }
        if (!$items||count($items)>2000) throw new \InvalidArgumentException('El archivo no contiene artículos o páginas nativas compatibles, o supera 2.000 por lote. Una exportación exclusiva de jDownloads necesita un adaptador y los archivos originales.');
        return self::prepare($items,$cms,$accounts,$unsupported) + [
            'ignored'=>$parsed['ignored'],'excluded_tables'=>$parsed['excluded_tables'],
            'warnings'=>['Se convierten páginas y artículos nativos. Las plantillas, plugins, shortcodes específicos, tiendas y extensiones necesitan adaptación.','Los archivos multimedia no están dentro de una base SQL. Debes trasladarlos y revisar sus rutas.','Las cuentas importadas quedan bloqueadas y sin privilegios administrativos; asigna una contraseña nueva antes de habilitarlas.','No se ejecuta ningún comando del SQL externo. La importación añade contenido y rechaza colisiones, sin sobrescribir registros existentes.']
        ];
    }

    public static function prepare(array $items,string $cms,array $accounts=[],int $unsupported=0): array {
        $slugs=[]; $links=[]; foreach ($items as &$item) { $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$item['slug']?:$item['title'])?:''),'-')); $slug=substr($slug?:'contenido',0,180); $base=$slug; $suffix=1; while (isset($slugs[$slug])) $slug=$base.'-'.(++$suffix); $slugs[$slug]=true; $item['slug']=$slug; $links[$item['source_id']]=($item['kind']==='page'?'/page/':'/blog/').$slug; } unset($item);
        foreach ($items as &$item) { if (strlen($item['content'])>1048576||!mb_check_encoding($item['content'],'UTF-8')) throw new \InvalidArgumentException('Un contenido supera 1 MB o no está codificado en UTF-8.'); if (preg_match('/\[(?:[a-z_]+)\b[^\]]*\]|\{load(?:module|position)|<!--\s*wp:[^>]*\/-->/i',$item['content'])) { $item['status']='draft'; $unsupported++; } $html=self::clean($item['content'],!in_array($cms,['joomla-online','wordpress-wxr'],true)); $item['content']=preg_replace_callback('~href="(?:\./)?(?:\?p=|index\.php\?option=com_content(?:&amp;|&)view=article(?:&amp;|&)id=)(\d+)[^"]*"~i',static fn($m)=>isset($links[(int)$m[1]])?'href="'.$links[(int)$m[1]].'"':$m[0],$html); } unset($item);
        return ['cms'=>$cms,'items'=>$items,'accounts'=>$accounts,'unsupported'=>$unsupported,'created'=>time(),'user'=>(int)Auth::id()];
    }
    public static function save(array $plan): string { $dir=dirname(__DIR__,2).'/storage/import-plans'; if (!is_dir($dir)&&!mkdir($dir,0700,true)) throw new \RuntimeException('No se pudo preparar la migración.'); $token=bin2hex(random_bytes(32)); $path=$dir.'/'.$token.'.json'; $json=json_encode($plan,JSON_THROW_ON_ERROR); if (file_put_contents($path,$json,LOCK_EX)!==strlen($json)) throw new \RuntimeException('No se pudo guardar el análisis.'); chmod($path,0600); return $token; }
    public static function load(string $token): array { if (!preg_match('/^[a-f0-9]{64}$/D',$token)) throw new \InvalidArgumentException('Análisis inválido.'); $path=dirname(__DIR__,2).'/storage/import-plans/'.$token.'.json'; if (!is_file($path)) throw new \InvalidArgumentException('El análisis ya no está disponible.'); $data=json_decode(file_get_contents($path),true,64,JSON_THROW_ON_ERROR); if (($data['user']??0)!==(int)Auth::id()||time()-($data['created']??0)>3600) throw new \InvalidArgumentException('El análisis venció o pertenece a otra cuenta.'); return $data; }
    public static function apply(string $token): array {
        $db=Database::getInstance()->getConnection(); $stmt=$db->prepare('SELECT summary_json,created_by FROM goldensite_import_runs WHERE token_hash=?'); $stmt->execute([hash('sha256',$token)]); if ($existing=$stmt->fetch(\PDO::FETCH_ASSOC)) { if ((int)$existing['created_by']!==(int)Auth::id()) throw new \InvalidArgumentException('La importación pertenece a otra cuenta.'); return json_decode($existing['summary_json'],true); }
        $plan=self::load($token); $backup=DatabaseBackup::directory().'/before-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(5)).'.sql'; DatabaseBackup::write($backup);
        $summary=['pages'=>0,'posts'=>0,'users'=>0,'backup'=>basename($backup)]; $db->beginTransaction(); try {
            $db->prepare('INSERT INTO goldensite_import_runs (token_hash,summary_json,created_by) VALUES (?,?,?)')->execute([hash('sha256',$token),'{}',Auth::id()]);
            $legacyPostSlug=in_array($plan['cms'],['joomla-online','wordpress-wxr'],true)?$db->prepare('SELECT id FROM posts WHERE slug=?'):null;
            foreach ($plan['items'] as $item) {
                $isPage=$item['kind']==='page';
                $table=$isPage?'goldensite_pages':'goldensite_posts';
                $check=$db->prepare('SELECT id FROM `'.$table.'` WHERE slug=?');
                $check->execute([$item['slug']]);
                if ($check->fetchColumn()) throw new \InvalidArgumentException('Ya existe la dirección '.$item['slug'].'. No se importó ningún registro.');
                if ($legacyPostSlug) {
                    $legacyPostSlug->execute([$item['slug']]);
                    if ($legacyPostSlug->fetchColumn()) throw new \InvalidArgumentException('Ya existe la dirección '.$item['slug'].'. No se importó ningún registro.');
                }
                $values=[$item['title'],$item['slug'],$item['content'],$item['status']];
                if (!$isPage && $legacyPostSlug) {
                    $db->prepare('INSERT INTO goldensite_posts (title,slug,content,status,is_published,category,meta_description,source_platform) VALUES (?,?,?,?,0,?,?,?)')
                        ->execute([...$values,$item['category']??null,$item['meta_description']??null,$plan['cms']]);
                } else {
                    $db->prepare('INSERT INTO `'.$table.'` (title,slug,content,status) VALUES (?,?,?,?)')->execute($values);
                }
                $summary[$isPage?'pages':'posts']++;
            }
            $names = array_fill_keys(array_map('strtolower', $db->query('SELECT username FROM users')->fetchAll(\PDO::FETCH_COLUMN)), true);
            $insertAccount = $db->prepare("INSERT INTO users (username,name,email,password,role,role_id,status) VALUES (?,?,?,?,'user',2,'blocked')");
            foreach ($plan['accounts'] as $account) {
                $base = preg_replace('/[^a-z0-9_.-]/i', '_', substr($account['username'], 0, 70)) ?: 'imported_user';
                $name = $base;
                $suffix = 1;
                while (isset($names[strtolower($name)])) $name = $base . '-' . (++$suffix);
                $names[strtolower($name)] = true;
                // An administrator must set a new password before this account can authenticate.
                $insertAccount->execute([$name, mb_substr($account['name'], 0, 100), $account['email'], '!migration-locked']);
                $summary['users']++;
            }
            $db->prepare('UPDATE goldensite_import_runs SET summary_json=? WHERE token_hash=?')->execute([json_encode($summary,JSON_THROW_ON_ERROR),hash('sha256',$token)]); $db->commit(); CacheStore::clear(); return $summary;
        } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); if ($e instanceof \PDOException) throw new \InvalidArgumentException('Hay un usuario, correo o dirección duplicada. No se importó ningún registro.'); throw $e; }
    }
}
