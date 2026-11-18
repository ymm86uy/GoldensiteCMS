<?php
namespace App\Core;
/** Reads literal MySQL dump data. No source statement is ever sent to MariaDB. */
final class CmsDumpReader
{
    public static function split(string $text,string $delimiter=','): array {
        $items=[]; $start=0; $quote=null; $depth=0; $length=strlen($text);
        for ($i=0;$i<$length;$i++) { $c=$text[$i]; if ($quote!==null) { if ($c==='\\') { $i++; continue; } if ($c===$quote) { if (($text[$i+1]??'')===$quote) $i++; else $quote=null; } continue; } if (in_array($c,["'",'"','`'],true)) { $quote=$c; continue; } if ($c==='(') $depth++; if ($c===')') $depth--; if ($c===$delimiter&&$depth===0) { $items[]=trim(substr($text,$start,$i-$start)); $start=$i+1; } }
        if ($quote!==null||$depth!==0) throw new \InvalidArgumentException('El SQL contiene una cadena o grupo incompleto.'); $items[]=trim(substr($text,$start)); return $items;
    }
    private static function statements(string $sql): \Generator {
        $buffer=''; $quote=null; $line=false; $comment=false; $length=strlen($sql);
        for ($i=0;$i<$length;$i++) { $c=$sql[$i]; $next=$sql[$i+1]??''; if ($line) { if ($c==="\n") { $line=false; $buffer.=' '; } continue; } if ($comment) { if ($c==='*'&&$next==='/') { $comment=false; $i++; $buffer.=' '; } continue; }
            if ($quote!==null) { $buffer.=$c; if ($c==='\\'&&$i+1<$length) $buffer.=$sql[++$i]; elseif ($c===$quote) { if ($next===$quote) $buffer.=$sql[++$i]; else $quote=null; } }
            elseif ($c==='/'&&$next==='*') { $comment=true; $i++; } elseif ($c==='#'||($c==='-'&&$next==='-'&&ctype_space($sql[$i+2]??' '))) $line=true;
            elseif (in_array($c,["'",'"','`'],true)) { $quote=$c; $buffer.=$c; } elseif ($c===';') { if (trim($buffer)!=='') yield trim($buffer); $buffer=''; } else $buffer.=$c;
            if (strlen($buffer)>12582912) throw new \InvalidArgumentException('Una sentencia del volcado supera 12 MB. Exporta con INSERT más pequeños.');
        } if ($quote!==null||$comment) throw new \InvalidArgumentException('Volcado SQL incompleto.'); if (trim($buffer)!=='') yield trim($buffer);
    }
    private static function literal(string $value): ?string {
        $value=trim($value); if (strcasecmp($value,'NULL')===0) return null; if (preg_match('/^-?\d+(?:\.\d+)?(?:e[+-]?\d+)?$/iD',$value)) return $value;
        $q=$value[0]??''; if (($q==="'"||$q==='"')&&str_ends_with($value,$q)) { $inner=substr($value,1,-1); return preg_replace_callback('/\\\\(.)/s',static fn($m)=>match($m[1]) {'n'=>"\n",'r'=>"\r",'t'=>"\t",'0'=>"\0",'Z'=>"\x1a",default=>$m[1]},str_replace($q.$q,$q,$inner)); }
        if (preg_match('/^0x([a-f0-9]+)$/iD',$value)) return hex2bin(substr($value,2)); throw new \InvalidArgumentException('El volcado usa expresiones en los datos. Exporta valores SQL literales.');
    }
    public const MAX_BYTES = 64 * 1024 * 1024;

    public static function readUpload(array $file): string
    {
        $path = $file['tmp_name'] ?? '';
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($path) || !is_uploaded_file($path) ||
            !is_string($file['name']??null) || filesize($path) > self::MAX_BYTES) {
            throw new \InvalidArgumentException('Selecciona un SQL, SQL.GZ o ZIP recibido correctamente, de hasta 64 MiB. Si la carga falla, revisa el límite del servidor.');
        }
        $name=strtolower($file['name']??'');
        if (str_ends_with($name,'.sql.gz')) {
            if (file_get_contents($path,false,null,0,3)!=="\x1f\x8b\x08") {
                throw new \InvalidArgumentException('El archivo no contiene un SQL comprimido con GZIP.');
            }
            $stream=gzopen($path,'rb');
            if (!$stream) throw new \InvalidArgumentException('No se pudo abrir el GZIP.');
            try {
                $sql='';
                while (!gzeof($stream)) {
                    $chunk=@gzread($stream,65536);
                    if ($chunk===false || ($chunk===''&&!gzeof($stream))) throw new \InvalidArgumentException('El SQL comprimido está dañado o incompleto.');
                    $sql.=$chunk;
                    if (strlen($sql)>self::MAX_BYTES) throw new \InvalidArgumentException('El SQL descomprimido supera los 64 MiB.');
                }
                return $sql;
            } finally {
                gzclose($stream);
            }
        }
        if (!str_ends_with($name,'.zip')) {
            $sql = file_get_contents($path, false, null, 0, self::MAX_BYTES + 1);
            if ($sql === false) throw new \InvalidArgumentException('No se pudo leer el SQL.');
            return $sql;
        }

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::RDONLY) !== true) throw new \InvalidArgumentException('El ZIP no se pudo abrir.');
        try {
            $entry = $zip->statIndex(0);
            if ($zip->numFiles !== 1 || !$entry || !str_ends_with(strtolower($entry['name']), '.sql') ||
                $entry['size'] < 1 || $entry['size'] > self::MAX_BYTES || $entry['encryption_method'] !== \ZipArchive::EM_NONE) {
                throw new \InvalidArgumentException('El ZIP debe contener un único SQL sin cifrar de hasta 64 MiB descomprimido.');
            }
            $sql = $zip->getFromIndex(0, self::MAX_BYTES + 1);
            if ($sql === false || strlen($sql) !== $entry['size']) throw new \InvalidArgumentException('El SQL comprimido está incompleto.');
            return $sql;
        } finally {
            $zip->close();
        }
    }

    public static function read(string $sql, string $cms = '', bool $users = true, array $onlyTables = []): array {
        if ($sql===''||strlen($sql)>self::MAX_BYTES||str_contains($sql,"\0")) throw new \InvalidArgumentException('Selecciona un SQL MySQL/MariaDB de hasta 64 MiB.');
        $columns=[]; $tables=[]; $total=0; $ignored=0; $excluded=[];
        foreach (self::statements($sql) as $statement) {
            if (preg_match('/^CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`?([a-z0-9_]+)`?\s*\((.*)\)\s*(?:ENGINE|TYPE|DEFAULT|;|$)/is',$statement,$m)) { $names=[]; foreach (self::split($m[2]) as $definition) if (preg_match('/^`?([a-z0-9_]+)`?\s+(?:tinyint|smallint|mediumint|bigint|int|integer|varchar|char|text|longtext|mediumtext|tinytext|datetime|timestamp|date|time|decimal|float|double|enum|set|blob|longblob|binary|varbinary|json|bit)\b/i',$definition,$col)) $names[]=$col[1]; $columns[$m[1]]=$names; continue; }
            if (!preg_match('/^INSERT\s+(?:IGNORE\s+)?INTO\s+`?([a-z0-9_]+)`?\s*(?:\(([^)]*)\))?\s*VALUES\s*(.*)$/is',$statement,$m)) { $ignored++; continue; }
            $table = $m[1];
            $suffix = match ($cms) {
                'joomla' => $users ? 'content|users' : 'content',
                'wordpress' => $users ? 'posts|users' : 'posts',
                default => 'posts|content|users|menu|postmeta',
            };
            if ($onlyTables ? !in_array($table, $onlyTables, true) : (!preg_match('/(?:^|_)(?:'.$suffix.')$/iD', $table) ||
                ($cms === 'joomla' && preg_match('/(?:^|_)(?:ucm_content|action_logs_users)$/iD', $table)))) {
                $excluded[$table] = true;
                continue;
            }
            $keys=($m[2]??'')!==''?array_map(static fn($v)=>trim($v," \t\r\n`"),explode(',',$m[2])):($columns[$table]??[]); if (!$keys) throw new \InvalidArgumentException('Faltan las columnas de '.$table.'. Incluye CREATE TABLE o nombres de columnas en el volcado.');
            foreach (self::split($m[3]) as $tuple) { if (++$total>($onlyTables ? 100000 : 20000)) throw new \InvalidArgumentException('El volcado supera 20.000 registros de contenido y usuarios.'); if (!str_starts_with($tuple,'(')||!str_ends_with($tuple,')')) throw new \InvalidArgumentException('Registro SQL incompleto.'); $values=array_map(self::literal(...),self::split(substr($tuple,1,-1))); if (count($values)!==count($keys)) throw new \InvalidArgumentException('Las columnas no coinciden en '.$table.'.'); $tables[$table][]=array_combine($keys,$values); }
        } return ['tables'=>$tables,'ignored'=>$ignored,'excluded_tables'=>array_keys($excluded)];
    }
}
