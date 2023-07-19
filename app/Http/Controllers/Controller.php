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
                $value = $request->get($attribute);

                if (!$value) {
                    continue;
                }

                if ($attribute == 'date' && str_contains($value, ',')) {
                    list($date1, $date2) = explode(',', $value);

                    $filter[] = [$attribute, '>=', $date1];
                    $filter[] = [$attribute, '<=', $date2];
                }
                else {
                    if (is_array($value)) {
                        $filter[] = [$attribute, $value];
                    }
                    else {
                        $filter[] = [$attribute, 'LIKE', '%' . $value . '%'];
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
