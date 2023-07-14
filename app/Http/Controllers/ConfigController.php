<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public static function setConfigs(array $configs = []): void
    {
        foreach ($configs as $key => $value) {
            Config::updateOrCreate(
                ['config' => $key],
                ['content' => $value]
            );
        }
    }

    public static function getConfig(string $config): string
    {
        return Config::where('config', $config)->pluck('content')->first();
    }
}
