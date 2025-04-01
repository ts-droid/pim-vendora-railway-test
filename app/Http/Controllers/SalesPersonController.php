<?php

namespace App\Http\Controllers;

use App\Models\SalesPerson;
use App\Services\SalesBudgetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        $salesPersonArray = $salesPerson->toArray();

        if ($request->get('extended_data') == '1') {
            $salesPersonArray['sales'] = DB::table('customer_invoice_lines')
                ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
                ->selectRaw("SUBSTRING(customer_invoices.date, 1, 7) AS date, SUM(customer_invoice_lines.amount) as total_amount")
                ->where('sales_person_id', '=', $salesPerson->external_id)
                ->groupBy(DB::raw("SUBSTRING(customer_invoices.date, 1, 7)"))
                ->pluck('total_amount', 'date');
        }

        return ApiResponseController::success($salesPersonArray);
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
        // Save sales person data
        $updateData = [];

        if ($request->has('basal_compensation')) {
            $updateData['basal_compensation'] = (int) $request->input('basal_compensation');
        }
        if ($request->has('commission')) {
            $updateData['commission'] = (float) $request->input('commission');
        }
        if ($request->has('sample_amount')) {
            $updateData['sample_amount'] = (int) $request->input('sample_amount');
        }

        if ($updateData) {
            $salesPerson->update($updateData);
        }

        // Save budget data
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
