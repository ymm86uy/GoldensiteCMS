<?php
namespace App\Core;

final class JoomlaOnlineImport
{
    private const PAGE_SIZE = 50;
    private const MAX_ARTICLES = 2000;

    public static function analyze(array $input, ?callable $request=null): array
    {
        $url=ConsoleView::field($input,'joomla_url',512);
        $parts=EnterpriseOidc::url($url);
        if (!preg_match('/\.[a-z][a-z0-9-]*$/iD',$parts['host'])) {
            throw new \InvalidArgumentException('Usa el nombre público de dominio del sitio Joomla.');
        }
        $path=rtrim($parts['path']??'','/');
        if (isset($parts['query']) || !preg_match('~^(?:/[A-Za-z0-9_.\~-]+)*$~D',$path)
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)~',$path)) {
            throw new \InvalidArgumentException('Indica la URL base de Joomla, sin parámetros, /administrator ni /api.');
        }
        if (preg_match('~/(?:administrator|api)(?:/|$)~i',$path)) {
            throw new \InvalidArgumentException('Indica la URL base de Joomla, sin /administrator ni /api.');
        }
        $origin='https://'.strtolower($parts['host']).$path;
        $headers=['Accept: application/vnd.api+json'];
        $method=$input['joomla_auth']??'token';
        if ($method==='basic') {
            $user=ConsoleView::field($input,'joomla_user',150);
            $password=$input['joomla_password']??'';
            if ($user==='' || str_contains($user,':') || !is_string($password) || $password==='' ||
                strlen($password)>1024 || preg_match('/[\x00-\x1f\x7f]/',$password)) {
                throw new \InvalidArgumentException('Introduce un usuario y una contraseña válidos de Joomla.');
            }
            $headers[]='Authorization: Basic '.base64_encode($user.':'.$password);
        } elseif ($method==='token') {
            $token=ConsoleView::field($input,'joomla_token',512);
            if (!preg_match('~^[A-Za-z0-9+/_=-]+$~D',$token)) throw new \InvalidArgumentException('Introduce el token de la API de Joomla.');
            $headers[]='X-Joomla-Token: '.$token;
        } else {
            throw new \InvalidArgumentException('Selecciona un método de autenticación.');
        }

        $request??=SafeHttp::getJsonResponse(...);
        $deadline=microtime(true)+45;
        $items=[];
        $seen=[];
        $bytes=0;
        $offset=0;
        $page=0;
        do {
            $remaining=(int)ceil($deadline-microtime(true));
            if ($remaining<1) throw new \InvalidArgumentException('Joomla tardó demasiado en completar el análisis.');
            $query=http_build_query(['page'=>['limit'=>self::PAGE_SIZE,'offset'=>$offset]],'','&',PHP_QUERY_RFC3986);
            $response=$request($origin.'/api/index.php/v1/content/articles?'.$query,$headers,min(10,$remaining));
            $status=(int)$response['status'];
            if ($status!==200) throw new \InvalidArgumentException(self::error($status,$method));
            try {
                $document=json_decode($response['body'],true,32,JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new \InvalidArgumentException('Joomla no devolvió JSON de la API de artículos.');
            }
            $rows=$document['data']??null;
            if (!is_array($rows)||!array_is_list($rows)||count($rows)>self::PAGE_SIZE) {
                throw new \InvalidArgumentException('La API de Joomla devolvió un formato de artículos inesperado.');
            }
            $categories=[];
            foreach (is_array($document['included']??null)?$document['included']:[] as $record) {
                if (is_array($record) && in_array($record['type']??null,['categories','category'],true)
                    && is_scalar($record['id']??null) && is_string($record['attributes']['title']??null)) {
                    $categories[(string)$record['id']]=mb_substr(strip_tags($record['attributes']['title']),0,100);
                }
            }
            foreach ($rows as $row) {
                $attributes=is_array($row)?($row['attributes']??null):null;
                if (!is_array($attributes)) throw new \InvalidArgumentException('La API devolvió un artículo inválido.');
                $id=filter_var($row['id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
                $title=$attributes['title']??null;
                $html=$attributes['text']??null;
                if (!$id || isset($seen[$id]) || !is_string($title) || trim(strip_tags($title))==='' || !is_string($html)) {
                    throw new \InvalidArgumentException('La API devolvió artículos incompletos o duplicados. Revisa la paginación del origen.');
                }
                $seen[$id]=true;
                $bytes+=strlen($html);
                if (strlen($html)>1048576 || $bytes>8388608 || count($seen)>self::MAX_ARTICLES) {
                    throw new \InvalidArgumentException('El sitio supera el límite de 2.000 artículos, 8 MiB por análisis o 1 MiB por artículo.');
                }
                $category=$row['relationships']['category']['data']['id']??null;
                $items[]=[
                    'kind'=>'post','source_id'=>$id,'title'=>mb_substr(trim(strip_tags($title)),0,255),
                    'slug'=>is_string($attributes['alias']??null)?$attributes['alias']:'',
                    'content'=>$html,'status'=>'draft',
                    'category'=>is_scalar($category)?($categories[(string)$category]??null):null,
                    'meta_description'=>is_string($attributes['metadesc']??null)?mb_substr(strip_tags($attributes['metadesc']),0,1000):null
                ];
            }
            // Use Joomla's pagination signal, never the URL returned in the response.
            $page++;
            $hasNext=!empty($document['links']['next']) || (int)($document['meta']['total-pages']??0)>$page;
            if ($hasNext && (!$rows || count($seen)>=self::MAX_ARTICLES)) {
                throw new \InvalidArgumentException('La API no terminó la paginación dentro del límite permitido. Usa el respaldo SQL para este lote.');
            }
            $offset+=count($rows);
        } while ($hasNext);

        if (!$items) throw new \InvalidArgumentException('La cuenta se conectó, pero la API no devolvió artículos para importar.');
        return CmsMigration::prepare($items,'joomla-online') + ['origin'=>$origin];
    }

    private static function error(int $status,string $method): string
    {
        return match (true) {
            $status===401||$status===403 => $method==='basic'
                ? 'Joomla rechazó el usuario o la contraseña, o no admite autenticación básica en su API. Comprueba los permisos o usa un token.'
                : 'Joomla rechazó el token o la cuenta no tiene permisos de API para leer artículos.',
            $status===404 => 'No se encontró la API de artículos. Comprueba la URL base y los servicios web de contenido de Joomla.',
            $status>=300 && $status<400 => 'Joomla redirigió la solicitud. Introduce la URL HTTPS canónica del sitio, incluido www si corresponde.',
            $status===429 => 'Joomla limitó las solicitudes. Espera antes de volver a analizar.',
            default => 'El servicio de artículos de Joomla no respondió correctamente.'
        };
    }
}
