<?php
namespace App\Core;
final class Money
{
    public static function cents(mixed $value,int $maximum=99999999): int
    {
        if (!is_string($value)||!preg_match('/^(?:0|[1-9][0-9]{0,7})(?:\.[0-9]{1,2})?$/D',$value)) throw new \InvalidArgumentException('Usa un importe válido con hasta dos decimales y punto decimal.');
        [$whole,$fraction]=array_pad(explode('.',$value),2,''); $cents=(int)$whole*100+(int)str_pad($fraction,2,'0');
        if ($cents>$maximum) throw new \InvalidArgumentException('El importe supera el límite permitido.'); return $cents;
    }
    public static function decimal(int $cents): string
    {
        return ($cents<0?'-':'').intdiv(abs($cents),100).'.'.str_pad((string)(abs($cents)%100),2,'0',STR_PAD_LEFT);
    }
    public static function display(int $cents,string $currency='USD'): string { return $currency.' '.self::decimal($cents); }
}
