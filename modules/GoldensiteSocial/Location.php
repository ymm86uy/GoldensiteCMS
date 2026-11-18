<?php
declare(strict_types=1);
namespace Modules\GoldensiteSocial;

final class Location
{
    // 0.09° of latitude is approximately 10 km. Adapt the longitude
    // divisions to the latitude band to retain a similar width.
    private const ROWS=2000;
    private const EQUATOR_COLUMNS=4000;

    public static function fromPublication(): array
    {
        $region=Access::field('region',2,false);
        $mode=Access::field('location_mode',12,false);
        if ($mode==='') $mode=$region===''?'none':'country';
        if ($mode==='none') return [null,null,null];
        if (!in_array($mode,['country','approximate'],true)) {
            throw new \InvalidArgumentException('Elige cómo compartir la ubicación.');
        }
        $region=Access::region($region);
        if ($mode==='country') return [$region,null,null];
        $row=self::index('zone_row',self::ROWS);
        $col=self::index('zone_col',self::columns($row));
        return [$region,$row,$col];
    }

    private static function index(string $key,int $size): int
    {
        $value=Access::field($key,4);
        if (!ctype_digit($value) || (int)$value>=$size) {
            throw new \InvalidArgumentException('Vuelve a calcular tu zona aproximada.');
        }
        return (int)$value;
    }

    private static function columns(int $row): int
    {
        $latitude=-90+($row+0.5)*180/self::ROWS;
        return max(1,(int)floor(self::EQUATOR_COLUMNS*cos(deg2rad($latitude))+0.5));
    }

    public static function point(?int $row,?int $col): ?array
    {
        if ($row===null || $col===null) return null;
        $latitude=-90+($row+0.5)*180/self::ROWS;
        $longitude=-180+($col+0.5)*360/self::columns($row);
        // Same projection as the existing 900 × 365 map.
        return [round(450+2.5*$longitude,2),round(max(0,min(365,212.5-2.5*$latitude)),2)];
    }
}
