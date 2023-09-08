<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Schema;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    public function getModelFilter($model, $request): array
    {
        $filter = [];

        $attributes = (new $model)->getFillable();
        if (!$attributes) {
            $attributes = Schema::getColumnListing((new $model)->getTable());
        }

        foreach ($attributes as $attribute) {
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

                        $filter[] = [$attribute, 'LIKE', $value];
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
