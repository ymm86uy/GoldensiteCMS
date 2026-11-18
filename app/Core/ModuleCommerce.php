<?php
namespace App\Core;

/** One commercial policy for Owner, catalog, activation and distribution. */
final class ModuleCommerce
{
    private static ?array $snapshot=null;
    public static function reset(): void { self::$snapshot=null; FrontendEngine::resetModuleCache(); }
    public static function snapshot(): array
    {
        if (self::$snapshot!==null) return self::$snapshot;
        $data=['revision'=>0,'offers'=>[]]; $path=dirname(__DIR__,2).'/config/catalog-policy.nfx';
        if (is_file($path)) {
            $claims=LicenseCode::open(trim(file_get_contents($path)),'NFXC1',131072);
            self::validateSnapshot($claims); $data=$claims;
        }
        try {
            $db=Database::getInstance(); $state=$db->fetch('SELECT revision FROM goldensite_catalog_state WHERE id=1');
            if ((int)($state['revision']??0)>=(int)$data['revision']) {
                $offers=[]; foreach ($db->fetchAll('SELECT * FROM goldensite_module_offers') as $row) { foreach (['amount_cents','discount_percent','starts_at','ends_at'] as $key) $row[$key]=$row[$key]===null?null:(int)$row[$key]; $offers[$row['module_id']]=$row; }
                $data=['revision'=>(int)($state['revision']??0),'offers'=>$offers];
            }
        } catch (\PDOException $e) { if ($e->getCode()!=='42S02') throw $e; }
        self::validateSnapshot(['v'=>1,'type'=>'catalog']+$data);
        return self::$snapshot=$data;
    }
    public static function offer(string $id,array $defaults=[]): array
    {
        $row=self::snapshot()['offers'][ModuleCatalog::canonical($id)]??[];
        return $row+$defaults;
    }
    public static function money(int $cents,string $currency,string $period): string
    {
        return ($currency==='USD'?'$':$currency.' ').number_format($cents/100,2,'.','').(['once'=>'','month'=>'/mes','year'=>'/año'][$period]);
    }
    public static function pricing(string $id,string $fallback): array
    {
        $offer=self::offer($id); $list=isset($offer['amount_cents'])?self::money($offer['amount_cents'],$offer['currency'],$offer['period']):$fallback;
        $percent=(int)($offer['discount_percent']??0); $now=time();
        $active=$percent>0&&(($offer['starts_at']??null)===null||$now>=$offer['starts_at'])&&(($offer['ends_at']??null)===null||$now<$offer['ends_at'])&&isset($offer['amount_cents']);
        $amount=$active?intdiv($offer['amount_cents']*(100-$percent)+50,100):($offer['amount_cents']??null);
        return ['list'=>$list,'price'=>$active?self::money($amount,$offer['currency'],$offer['period']):$list,'discount'=>$active?$percent:0,'ends_at'=>$active?($offer['ends_at']??null):null];
    }
    public static function editing(string $id,?array $prices=null): array
    {
        $module=ModuleCatalog::get($id); $text=($prices??ModuleCatalog::prices())[$id]??($module['price_def']??'');
        $amount=0; $period='once';
        if (preg_match('~^\$([0-9]{1,6})(?:\.([0-9]{1,2}))?(/mes|/año)?$~Du',$text,$m)) { $amount=(int)$m[1]*100+(int)str_pad($m[2]??'',2,'0'); $period=($m[3]??'')==='/mes'?'month':((($m[3]??'')==='/año')?'year':'once'); }
        return self::offer($id,['tier'=>$module['is_pro']?'pro':'free','amount_cents'=>$amount,'currency'=>'USD','period'=>$period,'discount_percent'=>0,'starts_at'=>null,'ends_at'=>null]);
    }
    public static function write(string $id,array $row): void
    {
        Database::getInstance()->execute('INSERT INTO goldensite_module_offers (module_id,tier,amount_cents,currency,period,discount_percent,starts_at,ends_at) VALUES (?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE tier=VALUES(tier),amount_cents=VALUES(amount_cents),currency=VALUES(currency),period=VALUES(period),discount_percent=VALUES(discount_percent),starts_at=VALUES(starts_at),ends_at=VALUES(ends_at)',[$id,$row['tier'],$row['amount_cents'],$row['currency'],$row['period'],$row['discount_percent'],$row['starts_at'],$row['ends_at']]);
    }
    private static function validateSnapshot(array $claims): void
    {
        if (($claims['v']??null)!==1||($claims['type']??'')!=='catalog'||!is_int($claims['revision']??null)||$claims['revision']<0||!is_array($claims['offers']??null)||count($claims['offers'])>100) throw new \InvalidArgumentException('Catálogo firmado inválido.');
        foreach ($claims['offers'] as $id=>$row) {
            if (!is_string($id)||!isset(ModuleCatalog::definitions()[$id])||!is_array($row)||!in_array($row['tier']??'', ['free','pro'],true)||!is_int($row['amount_cents']??null)||$row['amount_cents']<0||$row['amount_cents']>99999999||!in_array($row['currency']??'', ['USD','EUR','UYU','BRL','MXN','GBP'],true)||!in_array($row['period']??'', ['once','month','year'],true)||!is_int($row['discount_percent']??null)||$row['discount_percent']<0||$row['discount_percent']>100) throw new \InvalidArgumentException('Oferta firmada inválida.');
            foreach (['starts_at','ends_at'] as $key) if (!array_key_exists($key,$row)||($row[$key]!==null&&!is_int($row[$key]))) throw new \InvalidArgumentException('Vigencia firmada inválida.');
        }
    }
    public static function import(string $code): void
    {
        Auth::requireAdmin(); $claims=LicenseCode::open(trim($code),'NFXC1',131072); self::validateSnapshot($claims); $current=self::snapshot();
        $db=Database::getInstance(); $pdo=$db->getConnection(); $pdo->beginTransaction();
        try {
            $db->execute('INSERT IGNORE INTO goldensite_catalog_state (id,revision) VALUES (1,0)'); $state=$db->fetch('SELECT revision FROM goldensite_catalog_state WHERE id=1 FOR UPDATE');
            if ($claims['revision']<=max((int)$state['revision'],$current['revision'])) throw new \InvalidArgumentException('El catálogo ya está aplicado o es anterior al actual.');
            $db->execute('DELETE FROM goldensite_module_offers'); foreach ($claims['offers'] as $id=>$row) self::write($id,$row);
            $db->execute('UPDATE goldensite_catalog_state SET revision=? WHERE id=1',[$claims['revision']]); LicenseService::audit('signed_catalog_imported','revision:'.$claims['revision']); $pdo->commit(); self::reset();
        } catch (\Throwable $e) { if ($pdo->inTransaction()) $pdo->rollBack(); throw $e; }
    }
}
