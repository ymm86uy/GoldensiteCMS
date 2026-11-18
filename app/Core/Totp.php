<?php

namespace App\Core;

final class Totp
{
    public static function consumeRecovery(int $id,string $code): bool { $code=strtoupper(preg_replace('/[\s-]/','',$code)); if (!preg_match('/^[A-F0-9]{16}$/D',$code)) return false; $db=Database::getInstance()->getConnection(); $db->beginTransaction(); try { $stmt=$db->prepare('SELECT totp_recovery_json FROM users WHERE id=? FOR UPDATE'); $stmt->execute([$id]); $codes=json_decode($stmt->fetchColumn()?:'[]',true); if (!is_array($codes)) $codes=[]; $matched=false; foreach ($codes as $key=>$hash) if (is_string($hash)&&hash_equals($hash,hash('sha256',$code))) { unset($codes[$key]); $matched=true; break; } if ($matched) $db->prepare('UPDATE users SET totp_recovery_json=? WHERE id=?')->execute([json_encode(array_values($codes),JSON_THROW_ON_ERROR),$id]); $db->commit(); return $matched; } catch (\Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; } }
    public static function counter(string $secret, string $code, ?int $now = null): ?int
    {
        if (!preg_match('/^[0-9]{6}$/D', $code) || !preg_match('/^[A-Z2-7]{16,128}$/D', $secret)) return null;
        $bits = '';
        foreach (str_split($secret) as $char) $bits .= str_pad(decbin(strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567', $char)), 5, '0', STR_PAD_LEFT);
        $key = '';
        for ($i = 0; $i + 8 <= strlen($bits); $i += 8) $key .= chr(bindec(substr($bits, $i, 8)));
        $counter = intdiv($now ?? time(), 30);
        for ($offset = -1; $offset <= 1; $offset++) {
            $step = $counter + $offset;
            if ($step < 0) continue;
            $digest = hash_hmac('sha1', pack('N2', intdiv($step, 4294967296), $step % 4294967296), $key, true);
            $position = ord($digest[19]) & 15;
            $number = unpack('N', substr($digest, $position, 4))[1] & 0x7fffffff;
            if (hash_equals(str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT), $code)) return $step;
        }
        return null;
    }
}
