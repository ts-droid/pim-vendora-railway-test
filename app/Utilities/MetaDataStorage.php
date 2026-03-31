<?php

namespace App\Utilities;

use App\Models\MetaData;

class MetaDataStorage
{
    public static function set(string $name, array $data): void
    {
        MetaData::updateOrCreate(
            ['name' => $name],
            ['value' => $data]
        );
    }

    public static function get(string $name): array
    {
        $metaData = MetaData::where('name', $name)->first();
        return $metaData ? $metaData->value : [];
    }

    public static function delete(string $name): void
    {
        MetaData::where('name', $name)->delete();
    }
}
