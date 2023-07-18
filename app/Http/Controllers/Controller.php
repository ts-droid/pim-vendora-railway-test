<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function getModelFilter($model, $request): array
    {
        $filter = [];

        foreach ((new $model)->getFillable() as $attribute) {
            if ($request->get($attribute)) {
                $filter[] = [$attribute, 'LIKE', '%' . $request->get($attribute) . '%'];
            }
        }

        return $filter;
    }
}
