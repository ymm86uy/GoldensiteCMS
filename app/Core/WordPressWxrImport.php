<?php
namespace App\Core;

final class WordPressWxrImport
{
    private const MAX_BYTES=8388608;
    private const CONTENT_NS='http://purl.org/rss/1.0/modules/content/';

    public static function upload(array $file): array
    {
        $path=$file['tmp_name']??'';
        if (($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||!is_string($path)||!is_uploaded_file($path)||filesize($path)>self::MAX_BYTES) {
            throw new \InvalidArgumentException('Selecciona un archivo XML de WordPress de hasta 8 MiB.');
        }
        $xml=file_get_contents($path,false,null,0,self::MAX_BYTES+1);
        if ($xml===false) throw new \InvalidArgumentException('No se pudo leer la exportación.');
        return self::plan($xml);
    }

    public static function plan(string $xml): array
    {
        if ($xml===''||strlen($xml)>self::MAX_BYTES||str_contains($xml,"\0")||!mb_check_encoding($xml,'UTF-8')||
            preg_match('/<!\s*(?:DOCTYPE|ENTITY)\b/i',$xml)) {
            throw new \InvalidArgumentException('Usa un XML WXR de WordPress en UTF-8, de hasta 8 MiB y sin DTD ni entidades externas.');
        }
        $dom=new \DOMDocument();
        if (!@$dom->loadXML($xml,LIBXML_NONET)||$dom->doctype!==null) throw new \InvalidArgumentException('El XML de WordPress no es válido.');
        $xpath=new \DOMXPath($dom);
        $channel=$xpath->query('/rss/channel')->item(0);
        if (!$channel) throw new \InvalidArgumentException('No se encontró una exportación WXR de WordPress.');
        $version=$xpath->query('./*[local-name()="wxr_version"]',$channel)->item(0);
        if (!$version||!preg_match('~^https?://wordpress\.org/export/1\.[0-9]+/$~D',$version->namespaceURI??'')) {
            throw new \InvalidArgumentException('No se encontró una exportación WXR de WordPress.');
        }
        $xpath->registerNamespace('wp',$version->namespaceURI);
        $xpath->registerNamespace('body',self::CONTENT_NS);
        $items=[];
        $seen=[];
        $skipped=0;
        $attachments=0;
        foreach ($xpath->query('./item',$channel) as $record) {
            $kind=$xpath->evaluate('string(./wp:post_type)',$record);
            if ($kind==='attachment') { $attachments++; continue; }
            if (!in_array($kind,['post','page'],true) ||
                in_array($xpath->evaluate('string(./wp:status)',$record),['trash','auto-draft','inherit'],true)) {
                $skipped++;
                continue;
            }
            $id=filter_var($xpath->evaluate('string(./wp:post_id)',$record),FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            $title=trim(strip_tags($xpath->evaluate('string(./title)',$record)));
            if (!$id||isset($seen[$id])||$title==='') throw new \InvalidArgumentException('El XML contiene contenido sin título o con identificadores inválidos o duplicados.');
            $seen[$id]=true;
            $items[]=['kind'=>$kind,'source_id'=>$id,'title'=>mb_substr($title,0,255),
                'slug'=>$xpath->evaluate('string(./wp:post_name)',$record),
                'content'=>$xpath->evaluate('string(./body:encoded)',$record),'status'=>'draft'];
            if (count($items)>2000) throw new \InvalidArgumentException('El lote supera los 2.000 artículos y páginas admitidos.');
        }
        if (!$items) throw new \InvalidArgumentException('El XML no contiene páginas o artículos nativos para importar.');
        return CmsMigration::prepare($items,'wordpress-wxr')+['skipped'=>$skipped,'attachments'=>$attachments];
    }
}
