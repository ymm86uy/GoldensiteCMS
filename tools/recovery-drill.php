<?php
if (PHP_SAPI!=='cli') { http_response_code(404); exit(1); }
require dirname(__DIR__).'/app/bootstrap.php';

use App\Core\{Database,Environment,RecoveryReport,Settings,SqlBackup};

try {
    if (($argv[1]??'')==='record') {
        $file=$argv[2]??'';
        $report=is_file($file)?json_decode((string)file_get_contents($file,false,null,0,4096),true,8,JSON_THROW_ON_ERROR):null;
        if (!is_array($report) || !RecoveryReport::valid($report)) throw new RuntimeException('Informe de ensayo inválido.');
        $archive=dirname(__DIR__).'/storage/backups/'.$report['backup'];
        if (!is_file($archive) || is_link($archive) || !hash_equals($report['sha256'],hash_file('sha256',$archive))) throw new RuntimeException('El informe no corresponde al ZIP de respaldo local.');
        $prior=RecoveryReport::verified();
        if (!$prior || $report['time']>=$prior['time']) Settings::set('last_recovery_drill',json_encode($report,JSON_THROW_ON_ERROR));
        echo "Ensayo registrado en Site Doctor.\n";
        exit;
    }

    $dbName=Environment::get('DRILL_DB_NAME');
    $dbUser=Environment::get('DRILL_DB_USER');
    $dbPass=Environment::get('DRILL_DB_PASS');
    $dbPort=Environment::get('DRILL_DB_PORT','3306');
    $archive=realpath($argv[2]??'');
    $folder=realpath($argv[3]??'');
    if (($argv[1]??'')!=='run' || !$archive || !preg_match('/^release_[a-zA-Z0-9_.-]+\.zip$/D',basename($archive))
        || !$folder || scandir($folder)!==['.','..'] || !preg_match('/^goldensite_drill_[a-z0-9_]+$/D',$dbName)
        || !ctype_digit($dbPort) || (int)$dbPort<1 || (int)$dbPort>65535
        || $dbName===Environment::get('DB_NAME') || $dbUser==='' || $dbPass==='' || $dbUser===Environment::get('DB_USER')) {
        throw new RuntimeException('Uso: recovery-drill.php run <ZIP> <carpeta privada vacía>. Configura DRILL_DB_NAME, DRILL_DB_USER y DRILL_DB_PASS para una base local de ensayo vacía y un usuario dedicado.');
    }
    $db=new PDO('mysql:host=127.0.0.1;port='.$dbPort.';dbname='.$dbName.';charset=utf8mb4',$dbUser,$dbPass,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    foreach ($db->query('SHOW GRANTS')->fetchAll(PDO::FETCH_COLUMN) as $grant) {
        if (str_contains($grant,'WITH GRANT OPTION') || (!str_starts_with($grant,'GRANT USAGE ON *.* TO ')
            && !str_contains($grant,' ON `'.$dbName.'`.* TO '))) throw new RuntimeException('El usuario de ensayo debe tener permisos exclusivos sobre la base aislada.');
    }
    if ($db->query('SHOW TABLES')->fetchColumn()) throw new RuntimeException('La base del ensayo debe estar vacía.');
    $zip=new ZipArchive();
    if ($zip->open($archive,ZipArchive::CHECKCONS)!==true) throw new RuntimeException('ZIP de respaldo inválido.');
    $seen=[];
    $total=0;
    for ($i=0;$i<$zip->numFiles;$i++) {
        $entry=$zip->statIndex($i);
        $name=$entry['name'];
        $zip->getExternalAttributesIndex($i,$os,$attributes);
        $total+=$entry['size'];
        if ($total>2147483648 || $i>=40000 || ($attributes>>16&0170000)===0120000
            || !preg_match('~^[A-Za-z0-9_. -]+(?:/[A-Za-z0-9_. -]+)*$~D',$name)
            || preg_match('~(?:^|/)\.{1,2}(?:/|$)|[. ](?:/|$)|(?:^|/)\.env(?:/|$)~i',$name)
            || isset($seen[strtolower($name)])) throw new RuntimeException('El ZIP contiene una entrada insegura o supera el tamaño admitido.');
        $seen[strtolower($name)]=true;
    }
    if (!$zip->extractTo($folder)) throw new RuntimeException('No se pudieron restaurar los archivos.');
    for ($i=0;$i<$zip->numFiles;$i++) {
        $entry=$zip->statIndex($i);
        if (hash_file('crc32b',$folder.'/'.$entry['name'])!==sprintf('%08x',$entry['crc'])) throw new RuntimeException('Error de integridad al extraer el respaldo.');
    }
    $zip->close();
    foreach (['public/index.php','app/bootstrap.php','vendor/autoload.php','routes/web.php','database_dump.sql'] as $needed) {
        if (!is_file($folder.'/'.$needed)) throw new RuntimeException('Falta '.$needed.' en el respaldo.');
    }
    SqlBackup::restore($db,$folder.'/database_dump.sql');
    foreach (['users','settings','goldensite_pages'] as $table) $db->query('SELECT COUNT(*) FROM '.$table)->fetchColumn();
    $user='drill_'.bin2hex(random_bytes(6));
    $password=bin2hex(random_bytes(20));
    $db->prepare("INSERT INTO users (username,email,password,role,role_id,status) VALUES (?,?,?,'admin',1,'active')")->execute([$user,$user.'@example.test',password_hash($password,PASSWORD_BCRYPT)]);
    if (!is_dir($folder.'/storage')) mkdir($folder.'/storage',0700);
    $socket=stream_socket_server('tcp://127.0.0.1:0');
    if (!$socket) throw new RuntimeException('No se pudo reservar puerto local.');
    $address=stream_socket_get_name($socket,false);
    fclose($socket);
    $origin='http://'.$address;
    $config=['DB_HOST'=>'127.0.0.1','DB_PORT'=>$dbPort,'DB_NAME'=>$dbName,'DB_USER'=>$dbUser,'DB_PASS'=>$dbPass,'APP_KEY'=>bin2hex(random_bytes(32)),'APP_URL'=>$origin,'APP_ENV'=>'production','RATE_LIMIT_NAMESPACE'=>bin2hex(random_bytes(16))];
    $lines=[];
    foreach ($config as $key=>$value) $lines[]=$key.'='.json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_SLASHES);
    file_put_contents($folder.'/.env',implode("\n",$lines)."\n",LOCK_EX);
    chmod($folder.'/.env',0600);
    // The restored site keeps its MFA policy. Prepare a factor only for the isolated test account.
    $recoveryCode=strtoupper(bin2hex(random_bytes(8)));
    $seed=''; for ($i=0;$i<32;$i++) $seed.='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[random_int(0,31)];
    $originalAppKey=getenv('APP_KEY');
    putenv('APP_KEY='.$config['APP_KEY']);
    try {
        $sealed=\App\Core\SecretStore::seal($seed);
    } finally {
        $originalAppKey===false?putenv('APP_KEY'):putenv('APP_KEY='.$originalAppKey);
    }
    $db->prepare('UPDATE users SET totp_secret=?,totp_recovery_json=? WHERE username=?')->execute([
        $sealed,json_encode([hash('sha256',$recoveryCode)],JSON_THROW_ON_ERROR),$user]);
    $env=array_intersect_key(getenv(),array_flip(['PATH','SystemRoot','WINDIR','TEMP','TMP']));
    $runner=proc_open([PHP_BINARY,'-S',$address,'-t',$folder.'/public',$folder.'/public/index.php'],[0=>['pipe','r'],1=>['file',$folder.'/storage/drill.log','a'],2=>['file',$folder.'/storage/drill.log','a']],$pipes,$folder,$env,['bypass_shell'=>true]);
    if (!is_resource($runner)) throw new RuntimeException('No se pudo iniciar el sitio restaurado.');
    fclose($pipes[0]);
    try {
        $curl=curl_init();
        curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIEFILE=>'',CURLOPT_TIMEOUT=>5,CURLOPT_PROXY=>'']);
        $login=false;
        for ($attempt=0;$attempt<30;$attempt++) {
            curl_setopt($curl,CURLOPT_URL,$origin.'/login');
            $login=curl_exec($curl);
            if (is_string($login)) break;
            usleep(100000);
        }
        if (!is_string($login) || !preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/',$login,$match)) throw new RuntimeException('El sitio restaurado no muestra el formulario de acceso.');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['csrf_token'=>$match[1],'email'=>$user,'password'=>$password])]);
        curl_exec($curl);
        if (curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==302) throw new RuntimeException('No se completó el inicio de sesión.');
        curl_setopt_array($curl,[CURLOPT_HTTPGET=>true,CURLOPT_URL=>$origin.'/login/2fa']);
        $challenge=curl_exec($curl);
        if (curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200 || !is_string($challenge)
            || !preg_match('/name="csrf_token"\s+value="([a-f0-9]+)"/',$challenge,$match)) throw new RuntimeException('El sitio restaurado no muestra el segundo factor.');
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['csrf_token'=>$match[1],'totp_code'=>$recoveryCode])]);
        curl_exec($curl);
        if (curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==302) throw new RuntimeException('No se pudo verificar el segundo factor del ensayo.');
        curl_setopt_array($curl,[CURLOPT_HTTPGET=>true,CURLOPT_URL=>$origin.'/admin/dashboard']);
        $dashboard=curl_exec($curl);
        if (curl_getinfo($curl,CURLINFO_RESPONSE_CODE)!==200 || !is_string($dashboard) || !str_contains($dashboard,$user)) throw new RuntimeException('No se pudo verificar el panel con la sesión de ensayo.');
    } finally { proc_terminate($runner); proc_close($runner); }
    $report=['format'=>1,'time'=>time(),'backup'=>basename($archive),'sha256'=>hash_file('sha256',$archive),'checks'=>['database'=>true,'files'=>true,'admin_login'=>true]];
    $output=$folder.'/recovery-report.json';
    if (file_put_contents($output,json_encode($report,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT))===false) throw new RuntimeException('No se pudo guardar el informe.');
    echo $output.PHP_EOL;
} catch (Throwable $error) { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); }
