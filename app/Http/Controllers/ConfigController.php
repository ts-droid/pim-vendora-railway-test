<?php

namespace App\Http\Controllers;

use App\Models\Config;
use Illuminate\Http\Request;

class ConfigController extends Controller
{
    public function getConfigRequest(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $configs = $request->input('configs');

        $updatedConfigs = [];

        if (is_array($configs)) {
            foreach ($configs as $key => $value) {
                $updatedConfigs[$key] = (string) $value;
            }
        }

        self::setConfigs($updatedConfigs);

        return ApiResponseController::success($updatedConfigs);
    }

    public static function setConfigs(array $configs = []): void
    {
        $__controllerLogContext = static::controllerStaticLogContext(__FUNCTION__, func_get_args());
        action_log('Invoked controller static method.', $__controllerLogContext);

        foreach ($configs as $key => $value) {
            Config::updateOrCreate(
                ['config' => $key],
                ['content' => $value]
            );
        }
    }

    public static function getConfig(string $config, mixed $default = ''): mixed
    {
        $__controllerLogContext = static::controllerStaticLogContext(__FUNCTION__, func_get_args());
        action_log('Invoked controller static method.', $__controllerLogContext);

        return Config::where('config', $config)->pluck('content')->first() ?: $default;
    }
}
