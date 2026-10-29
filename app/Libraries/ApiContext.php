<?php

namespace App\Libraries;

class ApiContext
{
    /** @var array<string, mixed>|null */
    private static ?array $actor = null;

    /**
     * @param array<string, mixed> $actor
     */
    public static function set(array $actor): void
    {
        self::$actor = $actor;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        return self::$actor;
    }
}
