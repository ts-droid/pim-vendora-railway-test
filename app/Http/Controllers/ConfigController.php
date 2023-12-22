<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function getConfigRequest(Request $request)
    {
        $configs = explode(',', $request->input('config', ''));

        $content = [];

        foreach ($configs as $config) {
            $content[] = [
                'config' => $config,
                'content' => self::getConfig($config),
            ];
        }

        return ApiResponseController::success($content);
    }

    public function setConfigRequest(Request $request)
    {
        $configs = $request->input('configs');

        if (is_array($configs)) {
            self::setConfigs($configs);
        }

        return ApiResponseController::success($configs);
    }

    public static function setConfigs(array $configs = []): void
    {
        foreach ($configs as $key => $value) {
            Config::updateOrCreate(
                ['config' => $key],
                ['content' => $value]
            );
        }
    }

    public static function getConfig(string $config, mixed $default = ''): mixed
    {
        return Config::where('config', $config)->pluck('content')->first() ?: $default;
    }
}
