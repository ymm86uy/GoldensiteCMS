<?php
// Real schema/data changes with an injected 1412: engine versions handle DDL snapshots differently.
$db->exec("CREATE TABLE backup_consistency_fixture (id INT PRIMARY KEY, content VARCHAR(100)) ENGINE=InnoDB");
$db->exec("INSERT INTO backup_consistency_fixture VALUES (1,'Preserved snapshot row')");
$dsn='mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.$name.';charset=utf8mb4';
$probe=new class($dsn,getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]) extends PDO {
    public PDO $schemaWriter;
    public int $remaining=1;
    public int $snapshots=0;
    public string $prefix='once';
    public ?PDOException $lastError=null;
    public ?PDOException $forcedError=null;
    public function query(string $query,?int $fetchMode=null,mixed ...$fetchModeArgs): PDOStatement|false
    {
        if ($query==='SHOW TABLES') {
            $this->snapshots++;
            if ($this->forcedError!==null) throw $this->forcedError;
            if ($this->remaining>0) {
                $this->remaining--;
                $this->schemaWriter->exec('ALTER TABLE backup_consistency_fixture ADD COLUMN '.$this->prefix.'_'.$this->snapshots.' INT NULL, ALGORITHM=COPY');
                $error=new PDOException('Fixture: consistent snapshot invalidated by concurrent DDL');
                $error->errorInfo=['HY000',1412,'Table definition has changed'];
                $this->lastError=$error;
                throw $error;
            }
        }
        try { return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$fetchModeArgs); }
        catch (PDOException $error) { $this->lastError=$error;throw $error; }
    }
};
$probe->schemaWriter=$db;
$backup=dirname(__DIR__).'/storage/backup-consistency-'.bin2hex(random_bytes(8)).'.sql';
try {
    App\Core\DatabaseBackup::write($backup,$probe);
    $contents=file_get_contents($backup);
    $check($probe->snapshots===2 && (int)($probe->lastError?->errorInfo[1]??0)===1412,'No se reprodujo y recuperó el cambio concurrente de esquema');
    $check(substr_count($contents,'Preserved snapshot row')===1 && substr_count($contents,'-- GoldenSiteCMS / SQL backup')===1,'El respaldo reutilizó contenido parcial de la primera lectura');
    $check(!$probe->inTransaction(),'El respaldo dejó una transacción abierta');
} finally { if(is_file($backup))unlink($backup); }
$type=get_class($probe);
$unstable=new $type($dsn,getenv('DB_USER'),getenv('DB_PASS'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$unstable->schemaWriter=$db;$unstable->remaining=0;$unstable->forcedError=$probe->lastError;
try {
    try { App\Core\DatabaseBackup::write($backup,$unstable); $check(false,'Un esquema inestable se anunció como respaldado'); }
    catch (PDOException $error) { $check((int)($error->errorInfo[1]??0)===1412 && $unstable->snapshots===2,'El reintento no está limitado a un nuevo snapshot'); }
    $check(!is_file($backup) && !$unstable->inTransaction(),'Quedó un respaldo incompleto o una transacción abierta');
    $unstable->beginTransaction();
    try { App\Core\DatabaseBackup::write($backup,$unstable); $check(false,'Se intervino una transacción ajena'); }
    catch (LogicException) { $check($unstable->inTransaction() && !is_file($backup),'Se modificó una transacción del llamante'); }
} finally {
    if ($unstable->inTransaction())$unstable->rollBack();
    if(is_file($backup))unlink($backup);
}
