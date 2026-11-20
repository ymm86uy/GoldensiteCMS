<?php
declare(strict_types=1);
namespace Modules\GoldensiteSecurityAudit;

use App\Core\{ExtensionPackages, Installer, ReleaseIntegrity, SafeHttp};
use App\Security\{HeuristicAntivirus, UploadAntivirus, WafGateway};
use PDO;

final class SecurityScanner
{
    private const FILE_LIMIT=2500;
    private const SOURCE_LIMIT=4194304;
    private array $findings=[];
    private array $limits=[];
    private string $area='files';
    private array $coverage=[];

    public function __construct(private readonly PDO $db, private readonly string $root, private readonly string $siteUrl) {}

    public function scan(): array
    {
        $this->findings=$this->limits=[];
        $this->coverage=array_fill_keys(['files','integrity','runtime','accounts','headers'],true);
        $checks=[
            'Archivos e integridad'=>$this->files(...),
            'PHP y sesión'=>$this->runtime(...),
            'Base de datos y cuentas'=>$this->accounts(...),
            'Cabeceras HTTPS'=>$this->headers(...),
        ];
        $completed=[];
        foreach ($checks as $label=>$check) {
            $this->area=['Archivos e integridad'=>'files','PHP y sesión'=>'runtime','Base de datos y cuentas'=>'accounts','Cabeceras HTTPS'=>'headers'][$label];
            try { $check(); $completed[]=$label; }
            catch (\Throwable $error) {
                $this->coverage[$this->area]=false;
                if ($this->area==='files') $this->coverage['integrity']=false;
                error_log('Auditoría de seguridad: '.$label.' no completada ('.get_class($error).').');
                $this->limits[]='No se pudo completar la revisión de '.$label.'.';
            }
        }
        $order=['Crítico'=>0,'Alto'=>1,'Medio'=>2,'Informativo'=>3];
        usort($this->findings,static fn(array $a,array $b): int=>$order[$a['severity']]<=>$order[$b['severity']]);
        return ['findings'=>$this->findings,'limits'=>$this->limits,'completed'=>$completed,'coverage'=>$this->coverage];
    }

    private function add(string $severity,string $title,string $location,string $risk,string $fix,bool $observed=true): void
    {
        if (count($this->findings)>=150) {
            $this->coverage[$this->area]=false;
            $message='Se alcanzó el límite de 150 hallazgos. Repite la auditoría tras revisar los resultados.';
            if (!in_array($message,$this->limits,true)) $this->limits[]=$message;
            return;
        }
        $this->findings[]=compact('severity','title','location','risk','fix','observed')+['area'=>$this->area];
    }

    private function files(): void
    {
        $public=$this->root.'/public';
        if (DIRECTORY_SEPARATOR!=='\\') {
            foreach (['.env','config','storage','storage/owner','public/uploads'] as $relative) {
                $mode=@fileperms($this->root.'/'.$relative);
                if ($mode!==false && ($mode&0002)) $this->add('Alto','Ruta escribible por cualquier usuario',$relative,'Otro usuario del servidor podría alterar archivos o configuración.','Asigna el directorio al usuario de la aplicación y retira el permiso de escritura para otros. Para .env, usa 0600 si PHP comparte su propietario.');
                elseif ($relative==='.env' && $mode!==false && ($mode&0004)) $this->add('Alto','Configuración privada legible por otros usuarios',$relative,'Las credenciales quedarían disponibles para otras cuentas locales.','Cambia .env a 0600 y verifica que PHP conserva acceso.');
            }
        } else { $this->coverage['files']=false; $this->limits[]='Los permisos Unix no se pueden interpretar en Windows. Revisa las ACL del alojamiento.'; }
        foreach ([$public.'/.htaccess',$public.'/uploads/.htaccess'] as $rule) {
            if (!is_link($rule) && is_file($rule)) {
                $contents=@file_get_contents($rule,false,null,0,16384);
                if (is_string($contents) && preg_match('/^\s*Options\s+(?:[^\r\n]*\s)?-Indexes(?:\s|$)/mi',$contents)) {
                    $this->limits[]='Hay una regla Apache contra el listado. Confirma que el vhost aplica esa regla a uploads.';
                    break;
                }
            }
        }
        if (!is_file($public.'/uploads/index.html') && !array_filter($this->limits,static fn($line)=>str_starts_with($line,'Hay una regla Apache'))) {
            $this->add('Medio','Protección de listado sin evidencia local','public/uploads/','El listado podría exponer nombres de archivos si el servidor permite autoindex.','Comprueba Options -Indexes en Apache o autoindex off en Nginx para uploads.',false);
        } elseif (is_file($public.'/uploads/index.html')) {
            $this->limits[]='Existe uploads/index.html. Comprueba que el servidor lo usa como índice o que autoindex está desactivado.';
        }
        $budget=self::SOURCE_LIMIT;
        $count=0; $partial=false;
        $scopes=['public','plugins','themes','modules'];
        if (!is_link($this->root.'/storage') && !is_link($this->root.'/storage/extension-packages')) {
            foreach (ExtensionPackages::installed() as $extension) {
                if (!($extension['use_bundled']??false) && preg_match('/^[a-f0-9]{64}$/D',$extension['sha256']??'')) {
                    $scopes[]='storage/extension-packages/'.$extension['sha256'];
                }
            }
        }
        foreach (array_unique($scopes) as $scope) {
            $base=$this->root.'/'.$scope;
            if (!is_dir($base) || is_link($base)) continue;
            $items=new \RecursiveCallbackFilterIterator(new \RecursiveDirectoryIterator($base,\FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $entry) use (&$partial): bool {
                    if ($entry->isLink()) return false;
                    if (!$entry->isDir()) return true;
                    if (in_array($entry->getFilename(),['vendor','.git','node_modules','cache'],true)) return false;
                    if (!$entry->isReadable()) { $partial=true; return false; }
                    return true;
                });
            foreach (new \RecursiveIteratorIterator($items,\RecursiveIteratorIterator::LEAVES_ONLY,\RecursiveIteratorIterator::CATCH_GET_CHILD) as $file) {
                if (++$count>self::FILE_LIMIT) { $partial=true; break 2; }
                if (!$file->isFile()) continue;
                $relative=str_replace('\\','/',substr($file->getPathname(),strlen($this->root)+1));
                $name=$file->getFilename();
                $media=str_starts_with($relative,'public/uploads/');
                if ($scope==='public' && preg_match('/^(?:\.env(?:\..+)?|config\.php)$|\.(?:sql|bak|old|backup)$/iD',$name)) {
                    $this->add('Alto','Archivo sensible dentro del directorio público',$relative,'Podría ser accesible si las reglas del servidor permiten servirlo.','Mueve los respaldos y secretos a storage privado y verifica las reglas del vhost.',false);
                }
                if (!preg_match('/\.(?:php\d*|phtml|phar)(?:\.|$)/i',$name)) continue;
                if ($media) $this->add('Crítico','Archivo con extensión ejecutable en medios',$relative,'Una configuración que permita ejecutar scripts en esta carpeta amplificaría el riesgo.','Revisa el origen del archivo y bloquea la ejecución de PHP en el directorio de medios.');
                $size=$file->getSize();
                if ($size>262144 || $size>$budget || !$file->isReadable()) { $partial=true; continue; }
                $budget-=$size;
                $this->source($file->getPathname(),$relative,$scope==='public');
            }
        }
        if ($partial) { $this->coverage['files']=false; $this->limits[]='La revisión de archivos es parcial por permisos, tamaño o límite de análisis.'; }
        $this->area='integrity';
        $manifest=$this->root.'/storage/audit-release-manifest.json';
        if (!is_file($manifest) && !is_file($this->root.'/'.ReleaseIntegrity::REFERENCE)) {
            $this->coverage['integrity']=false;
            $this->limits[]='Para comparar huellas SHA-256, coloca el manifiesto oficial de esta edición y versión en storage/audit-release-manifest.json.';
            return;
        }
        try {
            $integrity=is_file($this->root.'/'.ReleaseIntegrity::REFERENCE)
                ?ReleaseIntegrity::compareOfficial($this->root,41943040):ReleaseIntegrity::compare($this->root,$manifest,41943040);
            foreach (array_merge($integrity['missing'],$integrity['changed']) as $relative) $this->add('Alto','Diferencia frente al paquete de referencia',$relative,'El archivo falta o su contenido difiere de la referencia de esta versión.','Compara con el instalador oficial de la misma versión antes de restaurar el archivo.');
            if ($integrity['skipped']) { $this->coverage['integrity']=false; $this->limits[]='Se omitieron '.$integrity['skipped'].' archivos por el límite de integridad.'; }
        } catch (\RuntimeException | \InvalidArgumentException | \JsonException) {
            $this->coverage['integrity']=false;
            $this->limits[]='El manifiesto de integridad no se pudo validar para esta edición y versión.';
        }
    }

    private function source(string $path,string $relative,bool $public): void
    {
        if ($public) {
            $result=HeuristicAntivirus::scanFile($path,basename($path));
            if (($result['score']??0)>=20) $this->add('Medio','Patrón que merece inspección',$relative,
                implode(' · ',$result['reasons']),'Compara con el paquete oficial y revisa el archivo con el analizador existente.',false);
            return;
        }
        $contents=@file_get_contents($path,false,null,0,262145);
        if (!is_string($contents) || strlen($contents)>262144) return;
        $code='';
        foreach (token_get_all($contents) as $token) {
            if (!is_array($token)) { $code.=$token; continue; }
            $code.=match($token[0]){
                T_COMMENT,T_DOC_COMMENT,T_INLINE_HTML=>' ',
                T_CONSTANT_ENCAPSED_STRING=>"''",
                T_OPEN_TAG_WITH_ECHO=>'echo ',
                default=>$token[1],
            };
        }
        if (preg_match('/\b(?:echo|print)\s*\(?\s*\$_(?:GET|POST|REQUEST)\s*\[/i',$code)) {
            $this->add('Alto','Salida directa de parámetros en plantilla',$relative,'Una salida sin escape contextual podría permitir que se interprete contenido no confiable.','Valida el parámetro y escapa el texto HTML con ConsoleView::escape(). Revisa por separado otros contextos.',false);
        }
        if (preg_match('/->\s*(?:query|exec)\s*\(\s*["\'][^\r\n]{0,180}?["\']\s*\.\s*\$/i',$code)) {
            $this->add('Medio','Consulta construida por concatenación',$relative,'Si el valor concatenado procede de una entrada externa, podría cambiar la consulta.','Usa PDO::prepare() y parámetros para los valores. Valida identificadores dinámicos contra una lista permitida.',false);
        }
    }

    private function runtime(): void
    {
        foreach (Installer::requirements() as $label=>$ok) if (!$ok && !str_starts_with($label,'Escritura')) {
            $this->add('Alto','Requisito de PHP pendiente',$label,'El núcleo requiere esta versión o extensión para funcionar con su configuración prevista.','Usa la versión indicada por el instalador y habilita la extensión correspondiente en PHP.');
        }
        if (filter_var(ini_get('display_errors'),FILTER_VALIDATE_BOOLEAN) || strtolower((string)ini_get('display_errors'))==='stderr') {
            $this->add('Alto','PHP muestra errores','display_errors','Los errores podrían mostrar rutas y detalles de la aplicación.','Configura display_errors=Off y log_errors=On en el PHP del sitio.');
        }
        $cookie=session_get_cookie_params();
        foreach (['httponly'=>$cookie['httponly'],'secure'=>$cookie['secure'],'use_strict_mode'=>(bool)ini_get('session.use_strict_mode')] as $key=>$on) {
            if (!$on) $this->add('Medio','Protección de sesión desactivada','session.'.$key,'Se reduce la protección de la sesión del panel.','Activa la opción en PHP o en la configuración de HTTPS del sitio.');
        }
        if (WafGateway::configuration()['mode']!=='block') $this->add('Informativo','WAF fuera del modo bloqueo','WAF','Las reglas pueden observar y registrar sin bloquear.','Revisa la política y sus excepciones en WAF y análisis de archivos.');
        if (UploadAntivirus::policy()!=='required') $this->add('Informativo','Análisis antivirus de cargas sin exigir','Carga de archivos','La configuración observada no exige un análisis de ClamAV en cada carga.','Revisa el estado del analizador global en el panel WAF.');
    }

    private function accounts(): void
    {
        $account=(string)$this->db->query('SELECT CURRENT_USER()')->fetchColumn();
        if (strtolower(explode('@',$account,2)[0])==='root') $this->add('Crítico','Conexión a la base con root','PDO','La aplicación opera con una identidad administrativa de base de datos.','Configura un usuario dedicado con permisos solo sobre la base del CMS.');
        $standard=(int)$this->db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('goldensite_pages','goldensite_posts','goldensite_active_modules')")->fetchColumn();
        if ($standard!==3) $this->add('Informativo','Tablas esperadas sin verificar','Esquema GoldenSiteCMS','La cuenta de PDO no ve todas las tablas de referencia con prefijo goldensite_.','Verifica la base configurada, sus permisos y las migraciones de la versión instalada.');
        $rows=$this->db->query("SELECT username,status,(totp_secret IS NOT NULL AND totp_secret <> '') AS mfa FROM users WHERE role='admin' OR role_id=1 ORDER BY id LIMIT 251")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows)>250) { array_pop($rows); $this->limits[]='Se revisaron las primeras 250 cuentas administradoras.'; }
        foreach ($rows as $user) {
            $name=(string)$user['username'];
            if (!in_array(strtolower((string)$user['status']),['active','enabled','1'],true)) {
                $this->add('Informativo','Cuenta administradora inactiva',$name,'Conserva el rol y podría recuperarlo al reactivarse.','Revisa el rol en el directorio de usuarios si la cuenta ya no corresponde.');
                continue;
            }
            if (in_array(strtolower($name),['admin','root','test'],true)) $this->add('Medio','Nombre administrativo predecible',$name,'Facilita adivinar el identificador de inicio de sesión.','Usa un identificador individual y activa el segundo factor.');
            if (!(int)$user['mfa']) $this->add('Alto','Administrador sin segundo factor',$name,'Una contraseña comprometida podría habilitar el acceso al panel.','Activa TOTP desde Seguridad de mi cuenta y guarda los códigos de recuperación.');
        }
    }

    private function headers(): void
    {
        $configured=parse_url($this->siteUrl);
        if (!$configured || ($configured['scheme']??null)!=='https' || empty($configured['host'])) {
            $this->coverage['headers']=false;
            $this->limits[]='No se hizo la consulta de cabeceras porque APP_URL no tiene HTTPS.';
            return;
        }
        $result=SafeHttp::publicResponseHeaders($this->siteUrl);
        if ($result['status']<200 || $result['status']>=300) {
            $this->coverage['headers']=false;
            $this->limits[]='La portada respondió HTTP '.$result['status'].'. Las cabeceras de la página final requieren un control manual.';
            return;
        }
        $h=$result['headers'];
        $csp=$h['content-security-policy']??'';
        $checks=[
            ['Content-Security-Policy',(bool)preg_match('/(?:^|;)\s*(?:default-src|script-src)\s+[^;]+/i',$csp),'Define una política de recursos para el tema activo y pruébala antes de aplicarla.'],
            ['X-Frame-Options',in_array(strtoupper($h['x-frame-options']??''),['DENY','SAMEORIGIN'],true) || (bool)preg_match('/(?:^|;)\s*frame-ancestors\s+[^;]+/i',$csp),'Configura DENY o SAMEORIGIN según el sitio, o una política frame-ancestors adecuada.'],
            ['X-Content-Type-Options',strtolower($h['x-content-type-options']??'')==='nosniff','Configura X-Content-Type-Options: nosniff.'],
            ['Strict-Transport-Security',(bool)preg_match('/(?:^|;)\s*max-age=0*[1-9][0-9]*(?:;|\s*$)/i',$h['strict-transport-security']??''),'Configura HSTS en el vhost HTTPS cuando el dominio funcione por HTTPS.'],
            ['Referrer-Policy',in_array(strtolower($h['referrer-policy']??''),['no-referrer','same-origin','strict-origin','strict-origin-when-cross-origin'],true),'Elige no-referrer o strict-origin-when-cross-origin según las necesidades del sitio.'],
        ];
        foreach ($checks as [$name,$ok,$fix]) if (!$ok) $this->add('Medio','Cabecera ausente o requiere revisión',$name,'La respuesta de la portada no cumple la comprobación básica de esta protección.',$fix);
        $this->limits[]='La revisión de cabeceras corresponde exclusivamente a la respuesta HTTPS de la portada configurada.';
    }
}
