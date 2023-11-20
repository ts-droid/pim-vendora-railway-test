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
            $content = [
                'config' => $config,
                'content' => self::getConfig($config),
            ];
        }

        return ApiResponseController::success($content);
    }

    public function setConfigRequest(Request $request)
    {
        $configs = explode(',', $request->input('config', ''));
        $contents = explode(',', $request->input('content', ''));

        $saveData = [];

        for ($i = 0;$i < count($configs);$i++) {
            $saveData[$configs[$i]] = $contents[$i];
        }

        self::setConfigs($saveData);

        return ApiResponseController::success($saveData);
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
