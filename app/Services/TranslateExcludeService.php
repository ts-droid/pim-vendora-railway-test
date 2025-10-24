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

        $row = DB::table('translate_excludes')
            ->whereRaw('BINARY `value` = ?', [$value])
            ->first();

        if ($row) {
            $row->update(['value' => $value]);
        } else {
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
