<?php
namespace App\Core;

final class LegacyFormat
{
    // Frozen wire identifier from releases before the rebrand. These bytes
    // are also part of existing encryption key derivations.
    public const PREFIX = "\x6e\x65\x78\x6f\x72\x61";
    public const BRAND_PATTERN = '(?:goldensite|'.self::PREFIX.')';
    public const RELEASE_DIRECTORY = '.'.self::PREFIX.'-release';

    public static function updateInProgress(string $root): bool
    {
        return is_file($root.'/.goldensite-release-active')
            || is_file($root.'/'.self::RELEASE_DIRECTORY.'-active');
    }
}
