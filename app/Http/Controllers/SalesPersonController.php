<?php

namespace App\Http\Controllers;

use App\Models\SalesPerson;
use App\Services\SalesBudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SalesPersonController extends Controller
{
    public function get(Request $request)
    {
        $filter = $this->getModelFilter(SalesPerson::class, $request);

        $query = $this->getQueryWithFilter(SalesPerson::class, $filter);

        $salesPersons = $query->get();

        return ApiResponseController::success($salesPersons->toArray());
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'name' => 'required|string'
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $salesPerson = SalesPerson::create([
            'external_id' => $request->external_id,
            'name' => $request->name
        ]);

        return ApiResponseController::success([$salesPerson->toArray()]);
    }

    public function getOne(Request $request, SalesPerson $salesPerson)
    {
        return ApiResponseController::success($salesPerson->toArray());
    }

    public function update(Request $request, SalesPerson $salesPerson)
    {
        $fillables = (new SalesPerson)->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $salesPerson->{$key} = $value;
            }
        }

        $salesPerson->save();

        return ApiResponseController::success([$salesPerson->toArray()]);
    }

    public function allBudget(Request $request)
    {
        $year = $request->input('year') ?: date('Y');

        $budget = [];

        $salesPersons = SalesPerson::all();

        if ($salesPersons) {
            $budgetService = new SalesBudgetService();

            foreach ($salesPersons as $salesPerson) {
                $budget[$salesPerson->id] = $budgetService->getBudgetForYear([$salesPerson->id], $year);
            }
        }

        return ApiResponseController::success($budget);
    }

    public function budget(Request $request, SalesPerson $salesPerson)
    {
        $year = $request->input('year') ?: date('Y');

        $budgetService = new SalesBudgetService();
        $budget = $budgetService->getBudgetForYear([$salesPerson->id], $year);

        return ApiResponseController::success($budget);
    }

    public function saveBudget(Request $request, SalesPerson $salesPerson)
    {
        $entries = $request->input('entries');

        if ($entries && is_array($entries)) {
            $budgetService = new SalesBudgetService();

            foreach ($entries as $entry) {
                $salesPersonID = (int) $entry['sales_person_id'] ?? 0;
                $year = (int) $entry['year'] ?? 0;
                $month = (int) $entry['month'] ?? 0;
                $turnover = (int) $entry['turnover'] ?? 0;

                if (!$salesPersonID || !$year || !$month) {
                    continue;
                }

                $budgetService->setBudget($salesPersonID, $year, $month, $turnover);
            }
        }

        return ApiResponseController::success();
    }
}
