<?php
namespace App\Core;

final class MetNorwayForecast
{
    private const ENDPOINT = 'https://api.met.no/weatherapi/locationforecast/2.0/compact';
    private const REFRESH_INTERVAL = 10800;

    public static function current(array $coordinates, string $contact): array
    {
        $directory = dirname(__DIR__,2).'/storage/cache/met-weather';
        if (!is_dir($directory) && !@mkdir($directory,0700,true) && !is_dir($directory)) throw new \RuntimeException('Caché meteorológica no disponible.');
        $zone = hash('sha256',$coordinates['lat'].','.$coordinates['lon']);
        $file = $directory.'/'.$zone.'.json';
        $lock = fopen($directory.'/'.substr($zone,0,2).'.lock','c');
        if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new \RuntimeException('Consulta meteorológica en curso.');
        try {
            $now = time();
            $cached = is_file($file) ? json_decode((string)file_get_contents($file),true,10) : null;
            if (($cached['expires']??0) <= $now) {
                $hold = $directory.'/retry-after';
                if ((is_file($hold) && (int)file_get_contents($hold)>$now) || !RateLimiter::consume('met-weather:upstream',5,1)) {
                    throw new \RuntimeException('Proveedor meteorológico ocupado.');
                }
                [$status,$headers,$body] = self::request($coordinates,$contact,$cached['modified']??'');
                if ($status===429) {
                    $retry = self::headerTime($headers['retry-after']??'');
                    file_put_contents($hold,(string)max($now+300,$retry),LOCK_EX);
                    throw new \RuntimeException('Proveedor meteorológico ocupado.');
                }
                if ($status===200 || $status===203) {
                    if ($status===203) error_log('MET Norway Locationforecast: versión del servicio en retirada.');
                    $cached = ['periods'=>self::parse($body),'modified'=>self::httpDate($headers['last-modified']??'')];
                } elseif ($status!==304 || !$cached) {
                    throw new \RuntimeException('Proveedor meteorológico no disponible.');
                }
                $cached['expires'] = max($now+self::REFRESH_INTERVAL,self::headerTime($headers['expires']??''));
                if (file_put_contents($file,json_encode($cached,JSON_THROW_ON_ERROR),LOCK_EX)===false) throw new \RuntimeException('No se pudo guardar el pronóstico.');
                if (random_int(1,100)===1) {
                    foreach (glob($directory.'/*.json') ?: [] as $candidate) {
                        if (filemtime($candidate)<$now-172800) @unlink($candidate);
                    }
                }
            }
            $forecast = null;
            foreach ($cached['periods'] as $period) {
                if ($period['time']>$now && $forecast!==null) break;
                $forecast = $period;
                if ($period['time']>$now) break;
            }
            if ($forecast===null || abs($forecast['time']-$now)>21600) throw new \RuntimeException('Pronóstico fuera de vigencia.');
            $forecast['valid_at'] = gmdate('c',$forecast['time']);
            unset($forecast['time']);
            return $forecast;
        } finally {
            flock($lock,LOCK_UN);
            fclose($lock);
        }
    }

    private static function httpDate(string $value): string
    {
        return preg_match('/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/D',$value) ? $value : '';
    }

    private static function headerTime(string $value): int
    {
        if (ctype_digit($value)) return time()+min(86400,(int)$value);
        return self::httpDate($value)==='' ? 0 : (strtotime($value) ?: 0);
    }

    private static function request(array $coordinates,string $contact,string $modified): array
    {
        $curl = curl_init(self::ENDPOINT.'?'.http_build_query($coordinates,'','&',PHP_QUERY_RFC3986));
        $headers = [];
        $body = '';
        $requestHeaders = ['Accept: application/json'];
        if (self::httpDate($modified)!=='') $requestHeaders[] = 'If-Modified-Since: '.$modified;
        curl_setopt_array($curl,[CURLOPT_PROTOCOLS_STR=>'https',CURLOPT_FOLLOWLOCATION=>false,
            CURLOPT_CONNECTTIMEOUT_MS=>1500,CURLOPT_TIMEOUT_MS=>4000,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,
            CURLOPT_USERAGENT=>'GoldenSiteCMS Weather/1.0 '.$contact,CURLOPT_HTTPHEADER=>$requestHeaders,CURLOPT_ENCODING=>'',
            CURLOPT_HEADERFUNCTION=>static function($handle,string $line) use (&$headers): int {
                if (preg_match('/^(last-modified|expires|retry-after):\s*([^\r\n]{1,128})/i',$line,$match)) $headers[strtolower($match[1])] = trim($match[2]);
                return strlen($line);
            },CURLOPT_WRITEFUNCTION=>static function($handle,string $chunk) use (&$body): int {
                if (strlen($body)+strlen($chunk)>524288) return 0;
                $body .= $chunk;
                return strlen($chunk);
            }]);
        if (curl_exec($curl)!==true) throw new \RuntimeException('Proveedor meteorológico no disponible.');
        return [curl_getinfo($curl,CURLINFO_RESPONSE_CODE),$headers,$body];
    }

    public static function parse(string $body): array
    {
        $json = json_decode($body,true,24,JSON_THROW_ON_ERROR);
        $series = $json['properties']['timeseries']??null;
        if (!is_array($series)) throw new \RuntimeException('Pronóstico inválido.');
        $periods = [];
        foreach (array_slice($series,0,12) as $period) {
            $date = $period['time']??'';
            $temperature = $period['data']['instant']['details']['air_temperature']??null;
            if (!is_string($date) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D',$date)
                || (!is_int($temperature) && !is_float($temperature)) || !is_finite((float)$temperature) || abs($temperature)>100) continue;
            $time = strtotime($date);
            if ($time===false) continue;
            $symbol = $period['data']['next_1_hours']['summary']['symbol_code']??$period['data']['next_6_hours']['summary']['symbol_code']??'';
            $symbol = is_string($symbol) ? explode('_',$symbol)[0] : '';
            $condition = match (true) {
                str_contains($symbol,'thunder')=>'Tormenta eléctrica',
                str_contains($symbol,'sleet')=>'Aguanieve',
                str_contains($symbol,'snow')=>'Nieve',
                str_contains($symbol,'rain')=>'Lluvia',
                $symbol==='clearsky'=>'Despejado', $symbol==='fair'=>'Mayormente despejado',
                $symbol==='partlycloudy'=>'Parcialmente nublado', $symbol==='cloudy'=>'Nublado', $symbol==='fog'=>'Niebla',
                default=>'Estado del cielo sin datos',
            };
            $periods[] = ['time'=>$time,'temperature'=>round($temperature,1),'condition'=>$condition];
        }
        if (!$periods) throw new \RuntimeException('Pronóstico sin datos válidos.');
        return $periods;
    }
}
