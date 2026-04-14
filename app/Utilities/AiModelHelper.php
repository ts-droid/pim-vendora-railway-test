<?php

namespace App\Utilities;

use App\Http\Controllers\ConfigController;

class AiModelHelper
{
    public static function getAllModels(): array
    {
        $models = ConfigController::getConfig('openai_models');
        $models = preg_split("/\r\n|\n|\r/", $models);
        $models = array_map('trim', $models);
        return array_filter($models);
    }

    public static function getAllProviders(): array
    {
        $models = self::getAllModels();

        $providers = [];
        foreach ($models as $model) {
            $modelParts = explode('-', $model);
            $provider = $modelParts[0] ?? '';

            if ($provider && !in_array($provider, $providers)) {
                $providers[] = $provider;
            }
        }

        return $providers;
    }

    public static function getProviderModels(string $provider): array
    {
        $models = self::getAllModels();

        $providerModels = [];
        foreach ($models as $model) {
            if (str_starts_with($model, $provider)) {
                $providerModels[] = $model;
            }
        }

        return $providerModels;
    }

    public static function getProviderLatestModel(string $provider): string
    {
        $providerModels = self::getProviderModels($provider);
        return $providerModels[0] ?? '';
    }
}
