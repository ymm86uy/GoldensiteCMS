<?php
namespace App\Core;

final class ReleaseUpdate
{
    public const MANIFEST = 'goldensite-update.json';
    public const FORMAT = 'goldensite-update-1';
    private const STATE = '.goldensite-release';
    private const MAX_ARCHIVE_BYTES = 20971520;

    public static function version(): string
    {
        $path = self::root().'/release-version.txt';
        if (is_file($path)) {
            return self::versionNumber(trim((string)file_get_contents($path)));
        }
        // Baseline of installations that predate the versioned updater.
        return '1.1.0';
    }

    public static function inspect(string $archive, string $signature): array
    {
        $size = is_file($archive) ? filesize($archive) : false;
        if (!$size || $size > self::MAX_ARCHIVE_BYTES) {
            throw new \InvalidArgumentException('El ZIP debe pesar como máximo 20 MB.');
        }
        UpdateSignature::verify($archive,$signature);
        $files = SafeArchive::read($archive,self::root(),true);
        $manifest = self::manifest($files);
        if ($manifest['edition']!==ProductEdition::current()) {
            throw new \InvalidArgumentException('El ZIP pertenece a otra edición de Goldensite.');
        }
        if ($manifest['edition']==='commercial') {
            foreach (array_keys($files) as $path) {
                if (ProductEdition::ownerOnlyPath($path)) throw new \InvalidArgumentException('El ZIP comercial contiene componentes exclusivos de Owner.');
            }
        }
        if ($manifest['from'] !== self::version() || !version_compare($manifest['version'],$manifest['from'],'>')) {
            throw new \InvalidArgumentException('La versión de origen no coincide o el destino no es posterior.');
        }
        return [$manifest,$files];
    }

    public static function stage(string $archive, string $signature): array
    {
        if (!is_uploaded_file($archive)) {
            throw new \InvalidArgumentException('Selecciona un ZIP recibido mediante el formulario.');
        }
        [$manifest] = self::inspect($archive,$signature);
        $inbox = self::inbox();
        $id = bin2hex(random_bytes(16));
        $dir = $inbox.'/'.$id;
        if (!mkdir($dir,0700)) {
            throw new \RuntimeException('No se pudo preparar el paquete privado.');
        }
        try {
            if (!move_uploaded_file($archive,$dir.'/package.zip')) {
                throw new \RuntimeException('No se pudo guardar el ZIP.');
            }
            self::write($dir.'/signature.txt',$signature,0600);
        } catch (\Throwable $error) {
            @unlink($dir.'/package.zip');
            @unlink($dir.'/signature.txt');
            @rmdir($dir);
            throw $error;
        }
        return $manifest+['id'=>$id];
    }

    public static function apply(string $id): array
    {
        self::requireCli();
        return self::install($id, false);
    }

    public static function applyFromPanel(string $id): array
    {
        if (!IdentityAccess::superuser()) throw new \LogicException('Solo el administrador principal puede instalar actualizaciones.');
        if (filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)
            && !filter_var(ini_get('opcache.validate_timestamps'), FILTER_VALIDATE_BOOLEAN)) {
            throw new \InvalidArgumentException('El servidor conserva el código PHP en caché. Pide al responsable del servidor que use el procedimiento de actualización por consola.');
        }
        if (function_exists('set_time_limit')) set_time_limit(180);
        ignore_user_abort(true);
        return self::install($id, true);
    }

    private static function install(string $id, bool $fromPanel): array
    {
        self::validId($id);
        return self::locked(static function () use ($id,$fromPanel): array {
            $root = self::root();
            $marker = $root.'/'.self::STATE.'-active';
            if (file_exists($marker) || LegacyFormat::updateInProgress($root)) {
                throw new \RuntimeException('Hay una actualización interrumpida. Ejecuta el comando de recuperación indicado en la consola.');
            }
            $input = self::inbox().'/'.$id;
            if (is_link($input) || is_link($input.'/package.zip') || is_link($input.'/signature.txt')) {
                throw new \RuntimeException('El paquete preparado no es un archivo regular.');
            }
            $signature = is_file($input.'/signature.txt') ? (string)file_get_contents($input.'/signature.txt',false,null,0,2049) : '';
            $run = bin2hex(random_bytes(16));
            $job = self::state().'/'.$run;
            if (!mkdir($job,0700)) throw new \RuntimeException('No se pudo crear el punto de recuperación.');
            $source = $input.'/package.zip';
            if (!is_file($source) || filesize($source)>self::MAX_ARCHIVE_BYTES || !copy($source,$job.'/package.zip')) {
                throw new \RuntimeException('El ZIP preparado no está disponible o supera el límite.');
            }
            [$manifest,$files] = self::inspect($job.'/package.zip',$signature);
            unset($files[self::MANIFEST]);
            if ($fromPanel) self::checkPanelPermissions(array_keys($files));
            $added = self::snapshot($job,$files);
            SafeArchive::read($job.'/originals.zip',$root,true);
            $journal = $manifest+['added'=>$added,'snapshot_sha256'=>hash_file('sha256',$job.'/originals.zip')];
            self::write($job.'/journal.json',json_encode($journal,JSON_THROW_ON_ERROR),0600);
            self::saveRecoveryRunner($job,$run);
            self::write($marker,$run,0644);
            if (!$fromPanel) fwrite(STDERR,'Punto de recuperación: php '.self::STATE."/$run/recover.php\n");
            $backupName = 'pre-update-'.$run.'.sql';
            try {
                DatabaseBackup::write(DatabaseBackup::directory().'/'.$backupName);
                if (isset($files['database/schema.sql'])) {
                    SchemaMigrator::migrate(Database::getInstance()->getConnection(),$files['database/schema.sql']);
                }
                foreach ($files as $path=>$content) {
                    self::write($root.'/'.$path,$content);
                }
                self::write($root.'/release-version.txt',$manifest['version']."\n",0644);
                if (!unlink($marker)) throw new \RuntimeException('No se pudo cerrar el mantenimiento.');
            } catch (\Throwable $error) {
                try {
                    self::restore($job,$journal);
                    if (!unlink($marker)) throw new \RuntimeException('No se pudo cerrar el mantenimiento.');
                } catch (\Throwable $restoreError) {
                    throw new \RuntimeException('Recuperación incompleta. Mantén el servicio detenido y ejecuta rollback '.$run.'.',0,$restoreError);
                }
                throw new \RuntimeException('La instalación falló y se restauró el código. Revisa el SQL de respaldo y los cambios aditivos de esquema. '.$error->getMessage(),0,$error);
            }
            @unlink($job.'/package.zip');
            return $manifest+['rollback'=>$run,'database_backup'=>$backupName];
        });
    }

    private static function checkPanelPermissions(array $paths): void
    {
        foreach ($paths as $path) {
            $directory = dirname(self::root().'/'.$path);
            while (!is_dir($directory)) $directory = dirname($directory);
            if (!is_writable($directory)) {
                throw new \InvalidArgumentException('El servidor web no puede actualizar los archivos de esta instalación. Pide al responsable del servidor que use el procedimiento por consola.');
            }
        }
    }

    public static function rollback(string $run): void
    {
        self::requireCli();
        self::validId($run);
        $root = self::root();
        $state = self::state();
        $legacyState = $root.'/'.LegacyFormat::RELEASE_DIRECTORY;
        if (!is_dir($state.'/'.$run) && is_dir($legacyState.'/'.$run)) {
            $state = self::directory($legacyState);
        }
        self::locked(static function () use ($run,$root,$state): void {
            $marker = $state.'-active';
            $otherState = basename($state) === self::STATE ? LegacyFormat::RELEASE_DIRECTORY : self::STATE;
            if (file_exists($root.'/'.$otherState.'-active')) {
                throw new \RuntimeException('Hay otra actualización interrumpida. Recupera primero esa operación.');
            }
            $job = $state.'/'.$run;
            if (is_link($job) || !is_file($job.'/journal.json')) throw new \RuntimeException('Punto de recuperación inexistente.');
            $journal = json_decode((string)file_get_contents($job.'/journal.json'),true,32,JSON_THROW_ON_ERROR);
            $active = is_file($marker) ? trim((string)file_get_contents($marker)) : null;
            if ($active !== $run && ($active !== null || self::version() !== $journal['version'])) {
                throw new \RuntimeException('Solo se puede recuperar la operación interrumpida o la última versión aplicada.');
            }
            self::write($marker,$run,0644);
            self::restore($job,$journal);
            if (!unlink($marker)) throw new \RuntimeException('No se pudo cerrar el mantenimiento.');
        }, $state);
    }

    public static function manifest(array $files): array
    {
        $raw = $files[self::MANIFEST] ?? '';
        if (strlen($raw)>4096 || count($files)<2) throw new \InvalidArgumentException('Falta el manifiesto o el ZIP no contiene una actualización.');
        $manifest = json_decode($raw,true,8,JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || !in_array($manifest['format']??null,[self::FORMAT,LegacyFormat::PREFIX.'-update-1'],true)) {
            throw new \InvalidArgumentException('Formato de actualización no reconocido.');
        }
        $edition = $manifest['edition']??'owner';
        if (!in_array($edition,['owner','commercial'],true)) throw new \InvalidArgumentException('Edición de actualización inválida.');
        return ['from'=>self::versionNumber($manifest['from']??null),'version'=>self::versionNumber($manifest['version']??null),'edition'=>$edition];
    }

    private static function snapshot(string $job, array $files): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($job.'/originals.zip',\ZipArchive::CREATE|\ZipArchive::EXCL)!==true) {
            throw new \RuntimeException('No se pudo crear el respaldo de código.');
        }
        $added = [];
        try {
            // Keep the archive present even when all deployed files are new.
            if (!$zip->addFromString(self::MANIFEST,'{}')) throw new \RuntimeException('No se pudo iniciar el respaldo.');
            foreach (array_keys($files) as $path) {
                $existing = self::root().'/'.$path;
                if (is_file($existing)) {
                    if (!$zip->addFile($existing,$path)) throw new \RuntimeException('No se pudo guardar '.$path);
                } elseif (file_exists($existing)) {
                    throw new \RuntimeException('El destino no es un archivo: '.$path);
                } else {
                    $added[] = $path;
                }
            }
        } finally {
            if (!$zip->close()) throw new \RuntimeException('El respaldo de código quedó incompleto.');
        }
        return $added;
    }

    private static function saveRecoveryRunner(string $job,string $run): void
    {
        foreach (['ReleaseUpdate','SafeArchive','LegacyFormat'] as $class) {
            $source = file_get_contents(__DIR__.'/'.$class.'.php');
            if ($source===false) throw new \RuntimeException('No se pudo preparar la recuperación autónoma.');
            self::write($job.'/'.$class.'.php',$source,0600);
        }
        $runner = '<?php if (PHP_SAPI !== "cli") exit(1); '
            .'require __DIR__."/LegacyFormat.php"; require __DIR__."/SafeArchive.php"; require __DIR__."/ReleaseUpdate.php"; '
            .'try { \App\Core\ReleaseUpdate::rollback('.var_export($run,true).'); echo "Código restaurado.\n"; } '
            .'catch (\Throwable $error) { fwrite(STDERR,$error->getMessage().PHP_EOL); exit(1); }';
        self::write($job.'/recover.php',$runner,0600);
    }

    private static function restore(string $job,array $journal): void
    {
        $archive = $job.'/originals.zip';
        if (!hash_equals($journal['snapshot_sha256'],(string)hash_file('sha256',$archive))) {
            throw new \RuntimeException('El respaldo de código no coincide con su registro.');
        }
        $root = self::root();
        $files = SafeArchive::read($archive,$root,true);
        unset($files[self::MANIFEST]);
        foreach ($files as $path=>$content) self::write($root.'/'.$path,$content);
        foreach ($journal['added'] as $path) {
            if (!SafeArchive::pathAllowed($path)) throw new \RuntimeException('Ruta de recuperación inválida.');
            $target = $root.'/'.$path;
            if (file_exists($target)) {
                $resolved = str_replace('\\','/',(string)realpath($target));
                if (is_link($target) || !str_starts_with($resolved,str_replace('\\','/',$root).'/') || !unlink($target)) {
                    throw new \RuntimeException('No se pudo retirar '.$path);
                }
            }
        }
        self::write($root.'/release-version.txt',self::versionNumber($journal['from'])."\n",0644);
    }

    private static function write(string $path,string $contents,?int $mode = null): void
    {
        $directory = dirname($path);
        if (!is_dir($directory) && !mkdir($directory,0755,true)) throw new \RuntimeException('No se pudo crear el destino.');
        if (is_link($path)) throw new \RuntimeException('Destino enlazado.');
        $temporary = $directory.'/.goldensite-'.bin2hex(random_bytes(12));
        try {
            if (file_put_contents($temporary,$contents,LOCK_EX)!==strlen($contents)
                || !chmod($temporary,$mode ?? (is_file($path)?fileperms($path)&0777:0644))
                || !rename($temporary,$path)) {
                throw new \RuntimeException('No se pudo guardar '.basename($path).'.');
            }
            if (str_ends_with(strtolower($path), '.php') && function_exists('opcache_invalidate')) {
                @opcache_invalidate($path, true);
            }
        } finally {
            if (is_file($temporary)) @unlink($temporary);
        }
    }

    private static function locked(callable $action, ?string $state = null): mixed
    {
        $path = ($state ?? self::state()).'/deployment.lock';
        if (is_link($path)) throw new \RuntimeException('El bloqueo no puede ser un enlace.');
        $lock = fopen($path,'c+b');
        if (!$lock) throw new \RuntimeException('No se pudo abrir el bloqueo.');
        try {
            if (!flock($lock,LOCK_EX|LOCK_NB)) throw new \RuntimeException('Ya hay una operación en marcha.');
            return $action();
        } finally {
            fclose($lock);
        }
    }

    private static function root(): string { return dirname(__DIR__,2); }
    private static function state(): string { return self::directory(self::root().'/'.self::STATE); }
    private static function inbox(): string { return self::directory(self::root().'/storage/update-inbox'); }

    private static function directory(string $path): string
    {
        if (is_link($path) || (!is_dir($path) && !mkdir($path,0700))) {
            throw new \RuntimeException('No se pudo abrir el directorio privado de actualizaciones.');
        }
        return $path;
    }

    private static function versionNumber(mixed $version): string
    {
        if (!is_string($version) || !preg_match('/^(?:0|[1-9][0-9]{0,5})(?:\.(?:0|[1-9][0-9]{0,5})){2}$/D',$version)) {
            throw new \InvalidArgumentException('La versión debe tener formato X.Y.Z.');
        }
        return $version;
    }

    private static function validId(string $id): void
    {
        if (!preg_match('/^[a-f0-9]{32}$/D',$id)) throw new \InvalidArgumentException('Identificador de actualización inválido.');
    }

    private static function requireCli(): void
    {
        if (PHP_SAPI !== 'cli') throw new \LogicException('El despliegue solo se ejecuta desde la consola del servidor.');
    }
}
