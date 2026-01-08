<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected bool $suppressControllerMethodLog = false;

    /**
     * Wrap controller action execution so we can emit consistent logs.
     *
     * @param string $method
     * @param array $parameters
     */
    public function callAction($method, $parameters)
    {
        $context = $this->controllerLogContext($method, $parameters);

        action_log('Handling controller action.', $context);

        $this->suppressControllerMethodLog = true;

        try {
            $response = parent::callAction($method, $parameters);
            return $response;
        } catch (\Throwable $exception) {
            action_log('Controller action threw exception.', array_merge($context, [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
            ]), 'error');

            throw $exception;
        } finally {
            $this->suppressControllerMethodLog = false;
        }
    }

    protected function controllerLogContext(string $method, array $parameters = []): array
    {
        $context = [
            'controller' => static::class,
            'action' => $method,
        ];

        if (!empty($parameters)) {
            $context['parameters'] = static::sanitizeLogValues($parameters);
        }

        if ($requestContext = static::buildRequestContext()) {
            $context['request'] = $requestContext;
        }

        return $context;
    }

    protected static function controllerStaticLogContext(string $method, array $parameters = []): array
    {
        $context = [
            'controller' => static::class,
            'action' => $method,
        ];

        if (!empty($parameters)) {
            $context['parameters'] = static::sanitizeLogValues($parameters);
        }

        if ($requestContext = static::buildRequestContext()) {
            $context['request'] = $requestContext;
        }

        return $context;
    }

    protected function controllerResponseContext($response): array
    {
        $context = [];

        if (is_object($response) && method_exists($response, 'getStatusCode')) {
            $context['status_code'] = $response->getStatusCode();
        }

        if (
            is_object($response) &&
            property_exists($response, 'headers') &&
            $response->headers &&
            method_exists($response->headers, 'has') &&
            $response->headers->has('Content-Type')
        ) {
            $context['response_type'] = $response->headers->get('Content-Type');
        }

        return $context;
    }

    protected static function buildRequestContext(): ?array
    {
        $request = request();
        if (!$request) {
            return null;
        }

        return [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => optional($request->route())->getName(),
            'ip' => $request->ip(),
            'user_id' => optional($request->user())->id,
        ];
    }

    protected static function sanitizeLogValues($value)
    {
        if (is_null($value) || is_scalar($value)) {
            return $value;
        }

        if (is_array($value)) {
            return array_map(function ($item) {
                return static::sanitizeLogValues($item);
            }, $value);
        }

        if (is_object($value)) {
            if ($value instanceof \Illuminate\Http\Request) {
                return [
                    'class' => get_class($value),
                    'method' => $value->method(),
                    'url' => $value->fullUrl(),
                ];
            }

            if (method_exists($value, 'getKey')) {
                return [
                    'class' => get_class($value),
                    'id' => $value->getKey(),
                ];
            }

            return get_class($value);
        }

        return $value;
    }

    protected static function controllerHiddenLogFields(): array
    {
        return [
            'password',
            'password_confirmation',
            'current_password',
            'token',
            'api_key',
            'secret',
        ];
    }

    protected function shouldLogControllerMethod(): bool
    {
        return !$this->suppressControllerMethodLog;
    }

    public function getModelFilter($model, $request): array
    {
        $filter = [];

        $attributes = get_model_attributes($model);

        foreach ($attributes as $attribute) {
            if ($request->get($attribute)) {
                $value = $request->get($attribute);

                if (!$value) {
                    continue;
                }

                if ($attribute == 'id') {
                    $filter[] = [$attribute, '=', $value];
                }
                elseif ($attribute == 'date' && str_contains($value, ',')) {
                    list($date1, $date2) = explode(',', $value);

                    $filter[] = [$attribute, '>=', $date1];
                    $filter[] = [$attribute, '<=', $date2];
                }
                else {
                    if (str_contains($value, ',')) {
                        $filter[] = [$attribute, explode(',', $value)];
                    }
                    else {
                        if (str_contains($value, '*')) {
                            if (strlen($value) === 1) {
                                continue;
                            }

                            $value = str_replace('*', '%', $value);
                        }
                        else {
                            $value = $value . '%';
                        }

                        if (str_starts_with($value, '!!')) {
                            $value = substr($value, 2);
                            $filter[] = [$attribute, 'NOT LIKE', $value];
                        } else {
                            $filter[] = [$attribute, 'LIKE', $value];
                        }
                    }
                }
            }
        }

        return $filter;
    }

    public function getQueryWithFilter($model, $filter)
    {
        $query = $model::query();

        if (!$filter) {
            return $query;
        }

        foreach ($filter as $item) {
            if (count($item) === 2) {
                $query->whereIn($item[0], $item[1]);
            }
            else {
                $query->where($item[0], $item[1], $item[2]);
            }
        }

        return $query;
    }
}
