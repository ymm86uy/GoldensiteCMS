<?php
namespace App\Controllers;

use App\Core\{Database,EnterpriseSaml,FrontendEngine,RateLimiter,Settings};

final class ScimController
{
    private const USER = 'urn:ietf:params:scim:schemas:core:2.0:User';
    private const ERROR = 'urn:ietf:params:scim:api:messages:2.0:Error';
    private const LIST = 'urn:ietf:params:scim:api:messages:2.0:ListResponse';

    public static function isWriteRoute(string $method,string $path): bool
    {
        return (strtoupper($method)==='POST' && $path==='/api/scim/v2/Users')
            || (in_array(strtoupper($method),['PUT','PATCH','DELETE'],true) && preg_match('~^/api/scim/v2/Users/[a-f0-9]{32}$~D',$path));
    }

    public function discovery(): void
    {
        $this->handle(function (): void {
            $this->json(200,[
                'schemas'=>['urn:ietf:params:scim:schemas:core:2.0:ServiceProviderConfig'],
                'patch'=>['supported'=>true], 'bulk'=>['supported'=>false,'maxOperations'=>0,'maxPayloadSize'=>0],
                'filter'=>['supported'=>true,'maxResults'=>100], 'changePassword'=>['supported'=>false],
                'sort'=>['supported'=>false], 'etag'=>['supported'=>false],
                'authenticationSchemes'=>[['type'=>'oauthbearertoken','name'=>'Bearer token','description'=>'Token de aprovisionamiento de Goldensite','primary'=>true]],
            ]);
        });
    }

    public function users(string $id = ''): void
    {
        $this->handle(function (string $issuer) use ($id): void {
            $method = $_SERVER['REQUEST_METHOD'];
            $db = Database::getInstance();
            if ($method==='GET') {
                if ($id==='') { $this->listUsers($db,$issuer); return; }
                $row = $this->find($db,$issuer,$id);
                if (!$row) { $this->error(404,'Cuenta no encontrada.'); return; }
                $this->json(200,$this->resource($row));
                return;
            }

            $pdo = $db->getConnection();
            $pdo->beginTransaction();
            try {
                $row = $method==='POST' ? null : $this->find($db,$issuer,$id,true);
                if ($method!=='POST' && !$row) {
                    $pdo->rollBack();
                    $this->error(404,'Cuenta no encontrada.');
                    return;
                }
                if ($method==='DELETE') {
                    $this->save($db,$issuer,$row,['active'=>false],true);
                    $pdo->commit();
                    http_response_code(204);
                    return;
                }
                $data = $this->input();
                if ($method==='PATCH') $data = $this->patch($this->resource($row),$data);
                $resultId = $this->save($db,$issuer,$row,$data);
                $result = $this->find($db,$issuer,$resultId);
                if (!$result) throw new \RuntimeException('Cuenta no disponible tras guardar.');
                $pdo->commit();
                if ($method==='POST') header('Location: '.$this->location($resultId));
                $this->json($method==='POST'?201:200,$this->resource($result));
            } catch (\Throwable $error) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                throw $error;
            }
        });
    }

    private function handle(callable $action): void
    {
        header('Content-Type: application/scim+json; charset=utf-8');
        header('Cache-Control: no-store');
        try {
            if (!FrontendEngine::isModuleActive('sso') || Settings::get('enterprise_scim_enabled')!=='1') {
                $this->error(404,'Aprovisionamiento no disponible.');
                return;
            }
            EnterpriseSaml::transport();
            if (!RateLimiter::consume('scim:'.($_SERVER['REMOTE_ADDR']??''),240,60)) {
                header('Retry-After: 60');
                $this->error(429,'Límite temporal de solicitudes.');
                return;
            }
            $hash = Settings::get('enterprise_scim_hash');
            $issuer = EnterpriseSaml::issuer(EnterpriseSaml::config());
            $auth = $_SERVER['HTTP_AUTHORIZATION']??$_SERVER['REDIRECT_HTTP_AUTHORIZATION']??'';
            if (!preg_match('/^Bearer ([a-f0-9]{64})$/iD',$auth,$token) || !$hash || !hash_equals($hash,hash('sha256',$issuer."\0".$token[1]))) {
                header('WWW-Authenticate: Bearer');
                $this->error(401,'Token de aprovisionamiento inválido.');
                return;
            }
            $action($issuer);
        } catch (\JsonException|\InvalidArgumentException $error) {
            $this->error(400,$error instanceof \JsonException?'JSON inválido.':$error->getMessage(),'invalidValue');
        } catch (\PDOException $error) {
            if ((int)($error->errorInfo[1]??0)===1062) $this->error(409,'El usuario, correo o identificador ya está en uso.','uniqueness');
            else $this->error(500,'No se pudo completar la operación.');
        } catch (\Throwable $error) {
            $this->error(503,'No se pudo completar la operación. Revisa la configuración del servidor.');
        }
    }

    private function listUsers(Database $db,string $issuer): void
    {
        $filter = $_GET['filter']??'';
        $where = "s.issuer_hash=? AND s.deleted=0 AND u.role='user' AND u.role_id=2
            AND NOT EXISTS (SELECT 1 FROM goldensite_access_members m WHERE m.user_id=u.id)";
        $parameters = [$issuer];
        if ($filter!=='') {
            if (!is_string($filter) || strlen($filter)>512
                || !preg_match('/^(userName|externalId|id) eq ("(?:[^"\\\\\\\\]|\\\\\\\\.)*")$/iD',$filter,$parts)) {
                $this->error(400,'Filtros admitidos: userName, externalId e id con eq.','invalidFilter');
                return;
            }
            $column = ['username'=>'login_name','externalid'=>'external_id','id'=>'id'][strtolower($parts[1])];
            $where .= " AND s.$column=?";
            $parameters[] = json_decode($parts[2],true,4,JSON_THROW_ON_ERROR);
        }
        $start = max(1,min(1000000,filter_var($_GET['startIndex']??1,FILTER_VALIDATE_INT)?:1));
        $count = filter_var($_GET['count']??100,FILTER_VALIDATE_INT);
        $count = $count===false ? 100 : max(0,min(100,$count));
        $offset = $start-1;
        $join = ' FROM goldensite_scim_users s JOIN users u ON s.user_id=u.id WHERE '.$where;
        $total = (int)$db->fetch('SELECT COUNT(*) total'.$join,$parameters)['total'];
        $rows = $count ? $db->fetchAll('SELECT s.*,u.email,u.name,u.status'.$join." ORDER BY s.id LIMIT $count OFFSET $offset",$parameters) : [];
        $this->json(200,['schemas'=>[self::LIST],'totalResults'=>$total,'startIndex'=>$start,'itemsPerPage'=>count($rows),'Resources'=>array_map($this->resource(...),$rows)]);
    }

    private function find(Database $db,string $issuer,string $id,bool $lock=false): ?array
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) return null;
        return $db->fetch("SELECT s.*,u.email,u.name,u.status,u.role,u.role_id FROM goldensite_scim_users s JOIN users u ON u.id=s.user_id
            WHERE s.id=? AND s.issuer_hash=? AND s.deleted=0 AND u.role='user' AND u.role_id=2
            AND NOT EXISTS (SELECT 1 FROM goldensite_access_members m WHERE m.user_id=u.id)".($lock?' FOR UPDATE':''),[$id,$issuer]);
    }

    private function save(Database $db,string $issuer,?array $row,array $data,bool $deleted=false): string
    {
        $active = $data['active']??true;
        if (is_string($active) && in_array(strtolower($active),['true','false'],true)) $active = strtolower($active)==='true';
        if (!is_bool($active)) throw new \InvalidArgumentException('active debe ser booleano.');
        if ($deleted) {
            $db->execute('UPDATE goldensite_scim_users SET deleted=1,external_id=NULL,login_name=? WHERE id=?',['deleted-'.$row['id'],$row['id']]);
            $db->execute("UPDATE users SET email=?,status='blocked',password=? WHERE id=?",[
                'deleted-'.$row['id'].'@scim.invalid',self::randomPassword(),$row['user_id'],
            ]);
            $db->execute('DELETE FROM active_sessions WHERE user_id=?',[$row['user_id']]);
            return $row['id'];
        }
        $name = self::field($data['userName']??null,150);
        $external = self::field($data['externalId']??'',150);
        $display = self::field($data['displayName']??($data['name']['formatted']??''),100);
        $email = $name;
        if (isset($data['emails'])) {
            if (!is_array($data['emails']) || !array_is_list($data['emails'])) throw new \InvalidArgumentException('emails debe ser una lista.');
            foreach ($data['emails'] as $item) {
                if (!is_array($item)) throw new \InvalidArgumentException('Dirección de correo inválida.');
                if ($email===$name || ($item['primary']??false)===true) $email = self::field($item['value']??null,150);
                if (($item['primary']??false)===true) break;
            }
        }
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Se requieren userName y un correo válido.');
        $display = $display!=='' ? $display : mb_substr($name,0,100);
        $status = $active?'active':'blocked';
        $subject = hash('sha256',$name);
        if (!$row) {
            $id = bin2hex(random_bytes(16));
            $db->execute("INSERT INTO users (username,name,email,password,role,role_id,status) VALUES (?,?,?,?,'user',2,?)",
                ['enterprise_'.$id,$display,$email,self::randomPassword(),$status]);
            $db->execute('INSERT INTO goldensite_scim_users (id,user_id,issuer_hash,login_name,subject_hash,external_id) VALUES (?,?,?,?,?,?)',
                [$id,$db->lastInsertId(),$issuer,$name,$subject,$external?:null]);
            return $id;
        }
        $db->execute('UPDATE goldensite_scim_users SET login_name=?,subject_hash=?,external_id=? WHERE id=?',[$name,$subject,$external?:null,$row['id']]);
        $db->execute('UPDATE users SET name=?,email=?,status=? WHERE id=?',[$display,$email,$status,$row['user_id']]);
        if (!$active || $row['login_name']!==$name) {
            $db->execute('UPDATE users SET password=? WHERE id=?',[self::randomPassword(),$row['user_id']]);
            $db->execute('DELETE FROM active_sessions WHERE user_id=?',[$row['user_id']]);
        }
        return $row['id'];
    }

    private function patch(array $current,array $patch): array
    {
        if (!is_array($patch['schemas']??null) || !in_array('urn:ietf:params:scim:api:messages:2.0:PatchOp',$patch['schemas'],true)) {
            throw new \InvalidArgumentException('Falta el esquema PatchOp.');
        }
        $ops = $patch['Operations']??null;
        if (!is_array($ops) || !array_is_list($ops) || !$ops || count($ops)>30) throw new \InvalidArgumentException('Operaciones PATCH inválidas.');
        $paths = ['username'=>'userName','externalid'=>'externalId','displayname'=>'displayName','active'=>'active','emails'=>'emails','name'=>'name'];
        foreach ($ops as $operation) {
            if (!is_array($operation) || !is_string($operation['op']??null) || !is_string($operation['path']??'')) throw new \InvalidArgumentException('Operación PATCH inválida.');
            $verb = strtolower($operation['op']);
            $path = strtolower($operation['path']??'');
            $value = $operation['value']??null;
            if (in_array($verb,['add','replace'],true)) {
                if ($path==='' && is_array($value) && !array_is_list($value)) {
                    foreach ($value as $key=>$item) {
                        if (!isset($paths[strtolower((string)$key)])) throw new \InvalidArgumentException('Atributo PATCH no admitido.');
                        $current[$paths[strtolower((string)$key)]] = $item;
                    }
                } elseif (isset($paths[$path])) {
                    $current[$paths[$path]] = $value;
                } else {
                    throw new \InvalidArgumentException('Ruta PATCH no admitida.');
                }
            } elseif ($verb==='remove' && in_array($path,['externalid','displayname'],true)) {
                unset($current[$paths[$path]]);
            } else {
                throw new \InvalidArgumentException('Operación PATCH no admitida.');
            }
        }
        return $current;
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input',false,null,0,65537);
        if ($raw===false || strlen($raw)>65536) throw new \InvalidArgumentException('Solicitud demasiado grande.');
        $body = json_decode($raw,true,16,JSON_THROW_ON_ERROR);
        if (!is_array($body) || array_is_list($body)) throw new \InvalidArgumentException('Se esperaba un objeto JSON.');
        return $body;
    }

    private function resource(array $row): array
    {
        $data = ['schemas'=>[self::USER],'id'=>$row['id'],'userName'=>$row['login_name'],'active'=>$row['status']==='active',
            'displayName'=>$row['name']??'','emails'=>[['value'=>$row['email'],'primary'=>true,'type'=>'work']],
            'meta'=>['resourceType'=>'User','location'=>$this->location($row['id'])]];
        if ($row['external_id']!==null) $data['externalId'] = $row['external_id'];
        return $data;
    }

    private function location(string $id): string { return EnterpriseSaml::base().'/api/scim/v2/Users/'.$id; }

    private static function field(mixed $value,int $length): string
    {
        if (!is_string($value) || strlen($value)>$length || preg_match('/[\x00-\x1f\x7f]/',$value)) throw new \InvalidArgumentException('Atributo de usuario inválido.');
        return trim($value);
    }

    private static function randomPassword(): string { return password_hash(bin2hex(random_bytes(32)),PASSWORD_BCRYPT); }

    private function json(int $status,array $data): void
    {
        http_response_code($status);
        echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
    }

    private function error(int $status,string $detail,?string $type=null): void
    {
        $data = ['schemas'=>[self::ERROR],'status'=>(string)$status,'detail'=>$detail];
        if ($type!==null) $data['scimType'] = $type;
        $this->json($status,$data);
    }
}
