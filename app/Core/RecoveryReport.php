<?php
namespace App\Core;

final class RecoveryReport
{
    public static function latestBackup(): ?array
    {
        $latest=null;
        foreach (glob(dirname(__DIR__,2).'/storage/backups/*')?:[] as $file) {
            if (!is_file($file) || is_link($file) || !preg_match('/\.(zip|sql)$/D',basename($file)) || filesize($file)===0) continue;
            $time=filemtime($file);
            if ($time && $time>($latest['time']??0)) $latest=['file'=>basename($file),'time'=>$time];
        }
        return $latest;
    }

    public static function verified(): ?array
    {
        $report=json_decode(Settings::get('last_recovery_drill',''),true);
        return is_array($report) && self::valid($report)?$report:null;
    }

    public static function valid(array $report): bool
    {
        return ($report['format']??null)===1 && is_int($report['time']??null) && $report['time']>0 && $report['time']<=time()
            && is_string($report['backup']??null) && preg_match('/^release_[a-zA-Z0-9_.-]+\.zip$/D',$report['backup'])===1
            && is_string($report['sha256']??null) && preg_match('/^[a-f0-9]{64}$/D',$report['sha256'])===1
            && ($report['checks']??null)===['database'=>true,'files'=>true,'admin_login'=>true];
    }
}
