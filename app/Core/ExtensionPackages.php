<?php
declare(strict_types=1);
namespace App\Core;

final class ExtensionPackages
{
    public const PREFIX='NFXP1';
    public const MAX_BYTES=2097152;
    public const MAX_ITEMS=32;
    private const CHANNEL='https://goldensitecms.com/extension-catalog/';
    private const SETTING='extension_packages_v1';
    private const FILES=['module.json','main.php','admin.php','schema.json'];
    public const ASSETS=['style.css'=>'text/css; charset=utf-8','script.js'=>'text/javascript; charset=utf-8'];

    public static function installed(): array
    {
        $items=json_decode(Settings::get(self::SETTING,'{}'),true,12);
        return is_array($items)?$items:[];
    }

    private static function registryForWrite(): array
    {
        $items=json_decode(Settings::all()[self::SETTING]??'{}',true,12,JSON_THROW_ON_ERROR);
        if (!is_array($items)) throw new \RuntimeException('El registro de extensiones no es válido.');
        return $items;
    }

    public static function claim(string $code): array
    {
        $claim=LicenseCode::open(trim($code),self::PREFIX,2048);
        if (($claim['v']??null)!==1 || !is_string($claim['sha256']??null)
            || !preg_match('/^[a-f0-9]{64}$/D',$claim['sha256']) || !is_array($claim['module']??null)) {
            throw new \InvalidArgumentException('Firma de extensión inválida.');
        }
        return ['sha256'=>$claim['sha256'],'module'=>self::manifest($claim['module'])];
    }

    public static function manifest(array $data): array
    {
        foreach (['id'=>46,'name'=>80,'description'=>280,'version'=>20,'min_core'=>20] as $key=>$limit) {
            $value=$data[$key]??null;
            if (!is_string($value) || $value==='' || strlen($value)>$limit || !mb_check_encoding($value,'UTF-8')
                || preg_match('/[<>\x00-\x1f]/',$value)) throw new \InvalidArgumentException('Datos de extensión inválidos.');
        }
        $version='/^(?:0|[1-9][0-9]{0,4})(?:\.(?:0|[1-9][0-9]{0,4})){2}$/D';
        if (!preg_match('/^addon-[a-z0-9]+(?:-[a-z0-9]+)*$/D',$data['id'])
            || !preg_match('/^Goldensite(?:CMS)? /i',$data['name'])
            || !preg_match($version,$data['version']) || !preg_match($version,$data['min_core'])
            || ($data['api']??null)!==1 || !is_bool($data['pro']??null) || !is_bool($data['admin']??null)) {
            throw new \InvalidArgumentException('Manifiesto de extensión incompatible.');
        }
        $module=[];
        foreach (['id','name','description','version','min_core','api','pro','admin'] as $key) $module[$key]=$data[$key];
        if (array_key_exists('native',$data) || $module['id']===ForumDistribution::ID) {
            if ($module['id']!==ForumDistribution::ID || ($data['native']??null)!=='forum' || $module['pro'] || $module['admin']
                || array_key_exists('assets',$data) || array_key_exists('database',$data)
                || version_compare($module['min_core'],'1.0.24','<')) throw new \InvalidArgumentException('Paquete nativo de Forum no compatible.');
            return $module+['native'=>'forum'];
        }
        if (array_key_exists('assets',$data)) {
            $assets=$data['assets'];
            if (!is_array($assets) || !array_is_list($assets) || count($assets)>count(self::ASSETS)) throw new \InvalidArgumentException('Lista de recursos inválida.');
            $seen=[];
            foreach ($assets as $asset) {
                if (!is_string($asset) || !isset(self::ASSETS[$asset]) || isset($seen[$asset])) throw new \InvalidArgumentException('Recurso no permitido o duplicado.');
                $seen[$asset]=true;
            }
            if ($assets && version_compare($module['min_core'],'1.0.22','<')) throw new \InvalidArgumentException('Los recursos requieren GoldenSiteCMS 1.0.22 o posterior.');
            sort($assets,SORT_STRING);
            $module['assets']=$assets;
        }
        if (array_key_exists('database',$data)) {
            if ($data['database']!==true || version_compare($module['min_core'],'1.0.23','<')) {
                throw new \InvalidArgumentException('El esquema de extensión requiere GoldenSiteCMS 1.0.23 o posterior.');
            }
            $module['database']=true;
        }
        return $module;
    }

    public static function inspect(string $path): array
    {
        $size=filesize($path);
        if (!$size || $size>self::MAX_BYTES) throw new \InvalidArgumentException('El ZIP de extensión admite hasta 2 MB.');
        $zip=new \ZipArchive();
        if ($zip->open($path)!==true) throw new \InvalidArgumentException('El ZIP de extensión no se pudo abrir.');
        $files=[]; $total=0;
        try {
            $forumFiles=array_map(static fn(string $name): string=>'forum/'.$name,ForumDistribution::FILES);
            $allowed=array_merge(self::FILES,array_keys(self::ASSETS),$forumFiles);
            if ($zip->numFiles<2 || $zip->numFiles>count($allowed)) throw new \InvalidArgumentException('El ZIP contiene una estructura no admitida.');
            for ($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i);
                $name=$stat['name'];
                $total+=$stat['size'];
                $zip->getExternalAttributesIndex($i,$platform,$attributes);
                if (!in_array($name,$allowed,true) || isset($files[$name]) || $total>self::MAX_BYTES
                    || ($attributes>>16&0170000)===0120000) throw new \InvalidArgumentException('El ZIP contiene un archivo no permitido.');
                $contents=$zip->getFromIndex($i);
                if (!is_string($contents) || strlen($contents)!==$stat['size']) throw new \InvalidArgumentException('El ZIP contiene un archivo incompleto.');
                if (str_ends_with($name,'.php')) {
                    if (!str_starts_with($contents,'<?php') && !str_starts_with($name,'forum/Views/')) throw new \InvalidArgumentException('La entrada de la extensión debe ser PHP.');
                    try { token_get_all($contents,TOKEN_PARSE); }
                    catch (\ParseError) { throw new \InvalidArgumentException('La extensión contiene PHP inválido.'); }
                }
                $files[$name]=$contents;
            }
        } finally { $zip->close(); }
        $data=json_decode($files['module.json']??'',true,8,JSON_THROW_ON_ERROR);
        if (!is_array($data)) throw new \InvalidArgumentException('Falta el manifiesto de extensión.');
        $module=self::manifest($data);
        if (($module['native']??null)==='forum') {
            if (count($files)!==count($forumFiles)+1 || array_diff($forumFiles,array_keys($files))) throw new \InvalidArgumentException('El ZIP de Forum debe contener la implementación completa.');
            return [$module,$files];
        }
        if (array_intersect($forumFiles,array_keys($files))) throw new \InvalidArgumentException('Los archivos nativos requieren un paquete Forum.');
        if (!isset($files['main.php']) || $module['admin']!==isset($files['admin.php'])) {
            throw new \InvalidArgumentException('Los puntos de entrada no coinciden con el manifiesto.');
        }
        foreach (self::ASSETS as $asset=>$type) {
            if (isset($files[$asset])!==in_array($asset,$module['assets']??[],true)) throw new \InvalidArgumentException('Los recursos del ZIP deben coincidir con el manifiesto.');
        }
        if (isset($files['schema.json'])!==($module['database']??false)) throw new \InvalidArgumentException('El manifiesto y el esquema deben coincidir.');
        if (isset($files['schema.json'])) ExtensionSchema::parse($module['id'],$files['schema.json']);
        return [$module,$files];
    }

    public static function install(string $path,string $code,bool $publish): string
    {
        if ($publish && ProductEdition::current()!=='owner') throw new \LogicException('Solo Owner puede publicar extensiones.');
        $claim=self::claim($code); $hash=$claim['sha256'];
        if (!hash_equals($hash,hash_file('sha256',$path))) throw new \InvalidArgumentException('El ZIP no coincide con su firma.');
        $module=$claim['module'];
        if (version_compare(ReleaseUpdate::version(),$module['min_core'],'<')) throw new \InvalidArgumentException('El ZIP requiere una versión posterior del núcleo.');
        return self::locked(static function (string $root) use ($path,$code,$publish,$module,$hash): string {
            $items=self::registryForWrite();
            $current=$items[$module['id']]??null;
            $latest=$current['latest']??($current?self::versionPointer($current):null);
            if (!$current && count($items)>=self::MAX_ITEMS) throw new \InvalidArgumentException('Se alcanzó el límite de extensiones instaladas.');
            if ($latest && $latest['sha256']!==$hash && !version_compare($module['version'],$latest['version'],'>')) {
                throw new \InvalidArgumentException('Instala una versión de extensión posterior a la actual.');
            }
            self::inspect($path);
            $directory=$root.'/'.$hash;
            if (is_link($directory) || (!is_dir($directory) && !mkdir($directory,0700))) throw new \RuntimeException('No se pudo preparar la extensión.');
            $contents=file_get_contents($path);
            if ($contents===false || !hash_equals($hash,hash('sha256',$contents))) throw new \RuntimeException('El ZIP cambió durante la verificación.');
            self::saveArtifact($directory.'/package.zip',$contents);
            [$verified,$files]=self::inspect($directory.'/package.zip');
            if ($verified!==$module) throw new \InvalidArgumentException('El manifiesto del ZIP no coincide con la firma.');
            self::saveFiles($directory,$files);
            if (isset($files['schema.json'])) ExtensionSchema::apply(ExtensionSchema::parse($module['id'],$files['schema.json']));
            $assets=array_map(static fn(string $contents): string=>hash('sha256',$contents),array_intersect_key($files,self::ASSETS));
            $next=['sha256'=>$hash,'module'=>$module,'code'=>trim($code),'published'=>$publish,'assets'=>$assets,
                'main_hash'=>isset($files['main.php'])?hash('sha256',$files['main.php']):null,
                'admin_hash'=>isset($files['admin.php'])?hash('sha256',$files['admin.php']):null];
            $next['latest']=self::versionPointer($next);
            if ($current && $current['sha256']!==$hash) {
                unset($current['previous'],$current['latest']);
                $next['previous']=$current;
            } elseif (isset($current['previous'])) $next['previous']=$current['previous'];
            $items[$module['id']]=$next;
            Settings::set(self::SETTING,json_encode($items,JSON_THROW_ON_ERROR));
            return $module['id'];
        });
    }

    public static function rollback(string $id,string $expectedCurrent): void
    {
        self::locked(static function (string $root) use ($id,$expectedCurrent): void {
            $items=self::registryForWrite();
            $current=$items[$id]??null;
            $previous=$current['previous']??null;
            if (!$previous || !hash_equals($current['sha256'],$expectedCurrent) || $current['module']['pro']!==$previous['module']['pro']) {
                throw new \InvalidArgumentException('La versión cambió o no existe una versión anterior compatible para recuperar.');
            }
            $claim=self::claim($previous['code']);
            if ($claim['module']!==$previous['module'] || $claim['module']['id']!==$id || $claim['sha256']!==$previous['sha256']
                || version_compare(ReleaseUpdate::version(),$claim['module']['min_core'],'<')) throw new \InvalidArgumentException('La versión anterior no es válida para esta instalación.');
            $directory=$root.'/'.$claim['sha256'];
            $path=$directory.'/package.zip';
            if (is_link($directory) || is_link($path) || !is_file($path) || !hash_equals($claim['sha256'],hash_file('sha256',$path))) {
                throw new \InvalidArgumentException('El ZIP de la versión anterior no está disponible o su integridad no coincide.');
            }
            [$verified,$files]=self::inspect($path);
            if ($verified!==$claim['module']) throw new \InvalidArgumentException('El manifiesto de la versión anterior no coincide.');
            self::saveFiles($directory,$files);
            $previous['published']=false;
            $previous['latest']=$current['latest']??self::versionPointer($current);
            $items[$id]=$previous;
            Settings::set(self::SETTING,json_encode($items,JSON_THROW_ON_ERROR));
        });
    }

    public static function withdraw(string $id,string $expectedCurrent): void
    {
        if (ProductEdition::current()!=='owner') throw new \LogicException('Solo Owner puede retirar extensiones del canal.');
        self::locked(static function () use ($id,$expectedCurrent): void {
            $items=self::registryForWrite();
            $current=$items[$id]??null;
            if (!$current || !hash_equals($current['sha256'],$expectedCurrent)) throw new \InvalidArgumentException('La versión cambió. Vuelve a abrir el catálogo.');
            $items[$id]['published']=false;
            Settings::set(self::SETTING,json_encode($items,JSON_THROW_ON_ERROR));
        });
    }

    public static function useBundledForum(string $expectedCurrent): void
    {
        self::locked(static function () use ($expectedCurrent): void {
            $items=self::registryForWrite();
            $item=$items[ForumDistribution::ID]??null;
            if (($item['module']['native']??null)!=='forum' || !hash_equals($item['sha256'],$expectedCurrent)) {
                throw new \InvalidArgumentException('El paquete de Forum cambió. Vuelve a abrir el catálogo.');
            }
            $items[ForumDistribution::ID]['use_bundled']=true;
            $items[ForumDistribution::ID]['published']=false;
            Settings::set(self::SETTING,json_encode($items,JSON_THROW_ON_ERROR));
        });
    }

    private static function versionPointer(array $item): array
    {
        return ['version'=>$item['module']['version'],'sha256'=>$item['sha256']];
    }

    private static function locked(callable $operation): mixed
    {
        $root=self::directory(true);
        $path=$root.'/install.lock';
        if (is_link($path) || !($lock=fopen($path,'c'))) throw new \RuntimeException('El registro de extensiones no está disponible.');
        try {
            if (!flock($lock,LOCK_EX)) throw new \RuntimeException('No se pudo reservar el registro de extensiones.');
            Settings::reset();
            return $operation($root);
        } finally { fclose($lock); }
    }

    public static function directory(bool $create=false): string
    {
        $storage=dirname(__DIR__,2).'/storage';
        $root=$storage.'/extension-packages';
        if (is_link($storage) || is_link($root) || ($create && !is_dir($root) && !@mkdir($root,0700) && !is_dir($root))) {
            throw new \RuntimeException('El almacén de extensiones no está disponible.');
        }
        return $root;
    }

    private static function saveArtifact(string $file,string $contents): void
    {
        if (is_link($file)) throw new \RuntimeException('Ruta de extensión inválida.');
        if (is_file($file) && hash_equals(hash('sha256',$contents),hash_file('sha256',$file))) return;
        $temp=tempnam(dirname($file),'.extension-');
        if ($temp===false) throw new \RuntimeException('No se pudo preparar un archivo de extensión.');
        try {
            if (realpath(dirname($temp))!==realpath(dirname($file)) || file_put_contents($temp,$contents)!==strlen($contents)
                || !chmod($temp,0600) || !rename($temp,$file)) throw new \RuntimeException('No se pudo guardar un archivo de extensión.');
        } finally { if (is_file($temp)) unlink($temp); }
    }

    private static function saveFiles(string $directory,array $files): void
    {
        foreach ($files as $path=>$contents) {
            $parts=explode('/',$path);
            $name=array_pop($parts);
            $destination=$directory;
            foreach ($parts as $part) {
                $destination.='/'.$part;
                if (is_link($destination) || (!is_dir($destination) && !mkdir($destination,0700))) throw new \RuntimeException('No se pudo guardar la estructura de la extensión.');
            }
            self::saveArtifact($destination.'/'.$name,$contents);
        }
    }

    public static function offers(): array
    {
        return self::parseOffers(self::download('index.json',self::MAX_ITEMS*2052));
    }

    public static function parseOffers(string $json): array
    {
        $codes=json_decode($json,true,6,JSON_THROW_ON_ERROR);
        if (!is_array($codes) || !array_is_list($codes) || count($codes)>self::MAX_ITEMS) throw new \RuntimeException('El canal devolvió un catálogo inválido.');
        $rows=[];
        foreach ($codes as $code) {
            if (!is_string($code)) throw new \RuntimeException('El canal devolvió una firma inválida.');
            $row=self::claim($code);
            $id=$row['module']['id'];
            if (isset($rows[$id])) throw new \RuntimeException('El canal devolvió una extensión duplicada.');
            $rows[$id]=$row+['code'=>$code];
        }
        return $rows;
    }

    public static function import(string $code): string
    {
        $claim=self::claim($code);
        $archive=self::download('package/'.$claim['sha256'],self::MAX_BYTES);
        $root=self::directory(true);
        $temp=tempnam($root,'.download-');
        if ($temp===false) throw new \RuntimeException('El alojamiento no pudo preparar la descarga.');
        try {
            if (realpath(dirname($temp))!==realpath($root) || file_put_contents($temp,$archive)!==strlen($archive)) throw new \RuntimeException('Descarga incompleta.');
            return self::install($temp,$code,false);
        } finally { unlink($temp); }
    }

    private static function download(string $path,int $limit): string
    {
        $body='';
        $curl=curl_init(self::CHANNEL.$path);
        curl_setopt_array($curl,[CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_CONNECTTIMEOUT=>3,CURLOPT_TIMEOUT=>12,
            CURLOPT_WRITEFUNCTION=>static function ($handle,string $part) use (&$body,$limit): int {
                if (curl_getinfo($handle,CURLINFO_RESPONSE_CODE)!==200 || strlen($body)+strlen($part)>$limit) return 0;
                $body.=$part;
                return strlen($part);
            }]);
        if (curl_exec($curl)===false || curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200) throw new \RuntimeException('El canal oficial de extensiones no está disponible.');
        return $body;
    }
}
