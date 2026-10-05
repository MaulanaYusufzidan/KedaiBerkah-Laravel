<?php

namespace App\Support;

class Like
{
    /**
     * Pola LIKE "mengandung" dengan karakter khusus (% _ !) di-escape.
     * Pakai bersama klausa `ESCAPE '!'` agar perilakunya sama di MySQL/MariaDB dan SQLite.
     */
    public static function contains(string $search): string
    {
        return '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
    }
}
