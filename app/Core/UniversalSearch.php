<?php
namespace App\Core;
final class UniversalSearch
{
    public static function query(mixed $q): string { if (!is_string($q)||!mb_check_encoding($q,'UTF-8')||mb_strlen($q)>120) throw new \InvalidArgumentException('Escribe una búsqueda de hasta 120 caracteres.'); return trim(preg_replace('/\s+/u',' ',$q)); }
    public static function find(string $q,int $page=1): array {
        if (mb_strlen($q)<2) return ['data'=>[],'total'=>0,'page'=>1,'pages'=>0]; $page=max(1,min(10000,$page));
        $pattern='%'.strtr($q,['='=>'==','%'=>'=%','_'=>'=_']).'%'; $parts=[]; $params=[];
        foreach (['goldensite_pages'=>['Página','/page/','title','content',"status='published' AND is_published=1"],'goldensite_posts'=>['Artículo','/blog/','title','content',"status='published'"],'posts'=>['Artículo','/blog/','title','content',"status='published'"],'goldensite_forms'=>['Formulario','/forms/','name','schema_json','is_active=1']] as $table=>[$type,$base,$title,$content,$where]) {
            if ($table==='goldensite_forms'&&!FrontendEngine::isModuleActive('form-builder')) continue;
            if ($table==='posts') $where.=" AND NOT EXISTS (SELECT 1 FROM goldensite_posts canonical WHERE canonical.slug=posts.slug)";
            // Public search never indexes protected blocks, scripts, style or markup attributes.
            $brand=LegacyFormat::BRAND_PATTERN;
            $body="REGEXP_REPLACE(REGEXP_REPLACE(REGEXP_REPLACE(COALESCE(`$content`,''), '(?s)\\\\[{$brand}_paywall\\\\].*?\\\\[/{$brand}_paywall\\\\]', ''), '(?is)<(?:script|style)\\\\b[^>]*>.*?</(?:script|style)>', ''), '<[^>]+>', ' ')";
            if ($table==='goldensite_forms') $body="REPLACE(REPLACE(REPLACE(JSON_EXTRACT(schema_json,'$[*].label'),'[',''),']',''),'\"','')";
            $parts[]="SELECT `$title` title,slug,'$type' type,CONCAT('$base',slug) url,SUBSTRING($body,GREATEST(1,LOCATE(?, $body)-70),280) excerpt,CASE WHEN `$title` LIKE ? ESCAPE '=' THEN 2 ELSE 1 END score FROM `$table` WHERE $where AND (`$title` LIKE ? ESCAPE '=' OR $body LIKE ? ESCAPE '=')";
            array_push($params,$q,$pattern,$pattern,$pattern);
        }
        if (FrontendEngine::isModuleActive('commerce-sales')) { $parts[]="SELECT name title,CAST(id AS CHAR) slug,'Producto' type,CONCAT('/producto/',id) url,LEFT(REGEXP_REPLACE(COALESCE(description,''),'<[^>]+>',' '),280) excerpt,1 score FROM goldensite_products WHERE status='active' AND (name LIKE ? ESCAPE '=' OR description LIKE ? ESCAPE '=')"; array_push($params,$pattern,$pattern); }
        foreach ([['Inicio','/',Settings::get('site_title','GoldenSiteCMS').' '.Settings::get('site_description')],['Contacto y licencias','/contacto','Empezar con GoldenSiteCMS. Comprar módulos y licencias. Consultas de soporte y proyectos.'],['Preguntas frecuentes','/faq','Activación de licencias, acceso a contenidos y descargas, seguridad y consultas a administración.']] as [$title,$url,$content]) {
            $normalized=static fn($s)=>mb_strtolower(iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$s)?:$s); if (!str_contains($normalized($title.' '.$content),$normalized($q))) continue;
            $parts[]='SELECT ? title,? slug,? type,? url,? excerpt,1 score'; array_push($params,Locale::text($title),trim($url,'/'),'Página',$url,Locale::text($content));
        }
        $union=implode(' UNION ALL ',$parts); $db=Database::getInstance();
        $total=(int)$db->fetch('SELECT COUNT(*) n FROM (SELECT url FROM ('.$union.') s GROUP BY url) distinct_results',$params)['n'];
            $rows=$db->fetchAll('SELECT MIN(title) title,MIN(slug) slug,MIN(type) type,url,MIN(excerpt) excerpt,MAX(score) relevance FROM ('.$union.') s GROUP BY url ORDER BY relevance DESC,title,url LIMIT 20 OFFSET '.(($page-1)*20),$params);
        $seen=[]; $out=[]; foreach ($rows as $row) { if (isset($seen[$row['url']])) continue; $seen[$row['url']]=true; unset($row['relevance']); $row['excerpt']=trim(html_entity_decode($row['excerpt'],ENT_QUOTES|ENT_HTML5,'UTF-8')); $out[]=$row; }
        return ['data'=>$out,'total'=>$total,'page'=>$page,'pages'=>(int)ceil($total/20)];
    }
}
