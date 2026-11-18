<?php
namespace App\Geo;

use MaxMind\Db\Reader;

final class CountryLookup
{
    private ?Reader $reader = null;
    private array $countries = [];

    public function __construct()
    {
        $path = __DIR__.'/dbip-country-lite.mmdb';
        if (!is_readable($path)) return;
        try {
            $this->reader = new Reader($path);
        } catch (\RuntimeException $error) {
            error_log('GeoIP country data: '.$error->getMessage());
        }
    }

    public function available(): bool
    {
        return $this->reader !== null;
    }

    public function country(string $ip): ?array
    {
        if (!$this->reader || !filter_var($ip,FILTER_VALIDATE_IP)) return null;
        $binary = inet_pton($ip);
        if (strlen($binary) === 16 && str_starts_with($binary,str_repeat("\0",10)."\xff\xff")) {
            $ip = inet_ntop(substr($binary,12));
        }
        if (!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_GLOBAL_RANGE)) return null;
        if (array_key_exists($ip,$this->countries)) return $this->countries[$ip];
        try {
            $record = $this->reader->get($ip)['country'] ?? [];
        } catch (\RuntimeException $error) {
            error_log('GeoIP lookup: '.$error->getMessage());
            return $this->countries[$ip] = null;
        }
        $code = $record['iso_code'] ?? '';
        return $this->countries[$ip] = preg_match('/^[A-Z]{2}$/D',$code)
            ? ['code'=>$code,'name'=>$record['names']['es'] ?? $record['names']['en'] ?? $code]
            : null;
    }
}
