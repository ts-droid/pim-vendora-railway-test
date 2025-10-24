<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class TranslateExcludeService
{
    public static function getAll(): array
    {
        return DB::table('translate_excludes')
            ->pluck('value')
            ->toArray();
    }

    public static function add(string $value): void
    {
        $value = trim($value);
        if (!$value) return;

        $exists = DB::table('translate_excludes')
            ->whereRaw('BINARY `value` = ?', [$value])
            ->exists();

        if (!$exists) {
            DB::table('translate_excludes')->insert(['value' => $value]);
        }
    }

    public static function remove(string $value): void
    {
        $value = trim($value);
        if (!$value) return;

        DB::table('translate_excludes')
            ->whereRaw('BINARY `value` = ?', [$value])
            ->delete();

    }
}
