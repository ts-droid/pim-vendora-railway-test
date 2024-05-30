<?php

namespace App\Http\Controllers;

use App\Models\ArticlePrice;
use App\Models\Customer;
use App\Models\CustomerInvoice;
use App\Models\Supplier;
use App\Services\CustomerCreditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function topList(Request $request)
    {
        $limit = (int) $request->get('limit', 10);
        $articleNumber = $request->get('article_number');

        $customers = Customer::orderBy('sales_last_30_days', 'DESC')
            ->limit($limit)
            ->get()
            ->toArray();

        if ($articleNumber) {
            foreach ($customers as &$customer) {
                $priceRow = ArticlePrice::where('article_number', $articleNumber)
                    ->where('customer_id', $customer['id'])
                    ->first();

                $customer['article_data'] = [
                    'price' => $priceRow->base_price_SEK ?? 0,
                    'percent' => $priceRow->percent ?? 100,
                    'percent_inner' => $priceRow->percent_inner ?? 100,
                    'percent_master' => $priceRow->percent_master ?? 100,
                ];
            }
        }

        return ApiResponseController::success($customers);
    }

    public function get(Request $request)
    {
        $filter = $this->getModelFilter(Customer::class, $request);

        $query = $this->getQueryWithFilter(Customer::class, $filter);

        $orderBy = $request->input('order_by', '');
        $orderByDirection = $request->input('order_by_direction', 'ASC');
        $pageNumber = (int)$request->input('page_number', 0);
        $pageSize = (int)$request->input('page_size', 100);

        if ($orderBy) {
            $query->orderBy($orderBy, $orderByDirection);
        }

        if ($request->has('only_fields')) {
            $fields = explode(',', $request->input('only_fields'));
            $query->select($fields);
        }

        if ($pageNumber > 0) {
            $customers = $query->paginate($pageSize, ['*'], 'page_number', $pageNumber);
        } else {
            $customers = $query->get();
        }

        return ApiResponseController::success($customers->toArray());
    }

    public function getCustomer(Request $request, Customer $customer)
    {
        $customerArray = $customer->toArray();

        $customerCreditService = new CustomerCreditService();
        $customerArray['amount_due'] = $customerCreditService->getAmountDue($customer['customer_number'])[0];

        return ApiResponseController::success($customerArray);
    }

    public function getCustomerSales(Request $request, Customer $customer)
    {
        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $startDateLast = date('Y-m-d', strtotime($startDate . ' -1 year'));
        $endDateLast = date('Y-m-d', strtotime($endDate . ' -1 year'));

        $summary = [
            'turnover' => 0,
            'profit' => 0,
            'cost' => 0,
            'margin' => 0,
        ];

        $articles = [];
        $brands = [];

        $perMonth = [];
        $date = $startDate;
        while($date <= $endDate) {
            $label = substr($date, 0, 7);

            if (!isset($perMonth[$label])) {
                $perMonth[$label] = [
                    'label' => $label,
                    'start_date' => $label . '-01',
                    'end_date' => date('Y-m-t', strtotime($label . '-01')),
                    'start_date_last' => date('Y-m-01', strtotime($label . '-01' . ' -1 years')),
                    'end_date_last' => date('Y-m-t', strtotime($label . '-01' . ' -1 years')),
                    'turnover' => 0,
                    'cost' => 0,
                    'profit' => 0,
                    'margin' => 0,
                    'turnover_last' => 0,
                    'cost_last' => 0,
                    'profit_last' => 0,
                    'margin_last' => 0,
                ];
            }

            $date = date('Y-m-d', strtotime($date . ' +1 days'));
        }

        // Load all customer invoice lines
        $invoiceLines = DB::table('customer_invoice_lines')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->join('articles', 'articles.article_number', '=', 'customer_invoice_lines.article_number')
            ->where('customer_invoices.customer_number', $customer->customer_number)
            ->where('customer_invoices.date', '>=', $startDate)
            ->where('customer_invoices.date', '<=', $endDate)
            ->select(
                'customer_invoice_lines.*',
                'customer_invoices.date',
                'articles.supplier_number'
            )
            ->get();

        // Load customer invoice lines from last year
        $invoiceLinesLast = DB::table('customer_invoice_lines')
            ->join('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->join('articles', 'articles.article_number', '=', 'customer_invoice_lines.article_number')
            ->where('customer_invoices.customer_number', $customer->customer_number)
            ->where('customer_invoices.date', '>=', $startDateLast)
            ->where('customer_invoices.date', '<=', $endDateLast)
            ->select(
                'customer_invoice_lines.*',
                'customer_invoices.date',
                'articles.supplier_number'
            )
            ->get();

        foreach ($invoiceLines as $invoiceLine) {
            // Add to summary
            $summary['turnover'] += $invoiceLine->amount;
            $summary['cost'] += $invoiceLine->cost;

            // Add to articles
            if (!isset($articles[$invoiceLine->article_number])) {
                $articles[$invoiceLine->article_number] = [
                    'article_number' => $invoiceLine->article_number,
                    'description' => $invoiceLine->description,
                    'last_purchase_date' => $invoiceLine->date,
                    'last_purchase_quantity' => $invoiceLine->quantity,
                    'total_units' => 0,
                    'total_amount' => 0,
                    'total_cost' => 0,
                    'avg_in_price' => 0,
                    'avg_purchase_price' => 0,
                    'margin' => 0,
                    'customer_margin' => 0, // TODO: Calculate this
                ];
            }

            // Add to brands
            if (!isset($brands[$invoiceLine->supplier_number])) {
                $supplier = Supplier::where('number', $invoiceLine->supplier_number)->first();
                $supplierName = $supplier->name ?? '';
                $brandName = $supplier->brand_name ?? '';

                $brands[$invoiceLine->supplier_number] = [
                    'supplier_number' => $invoiceLine->supplier_number,
                    'brand' => $brandName ?: $supplierName,
                    'last_purchase_date' => $invoiceLine->date,
                    'last_purchase_quantity' => $invoiceLine->quantity,
                    'total_units' => 0,
                    'total_amount' => 0,
                    'total_cost' => 0,
                    'avg_in_price' => 0,
                    'avg_purchase_price' => 0,
                    'margin' => 0,
                    'customer_margin' => 0, // TODO: Calculate this
                ];
            }

            // Add to per month data
            foreach ($perMonth as &$monthData) {
                if ($invoiceLine->date < $monthData['start_date'] || $invoiceLine->date > $monthData['end_date']) {
                    continue;
                }

                $monthData['turnover'] += $invoiceLine->amount;
                $monthData['cost'] += $invoiceLine->cost;
            }

            $articles[$invoiceLine->article_number]['total_units'] += $invoiceLine->quantity;
            $brands[$invoiceLine->supplier_number]['total_units'] += $invoiceLine->quantity;

            $articles[$invoiceLine->article_number]['total_amount'] += $invoiceLine->amount;
            $brands[$invoiceLine->supplier_number]['total_amount'] += $invoiceLine->amount;

            $articles[$invoiceLine->article_number]['total_cost'] += $invoiceLine->cost;
            $brands[$invoiceLine->supplier_number]['total_cost'] += $invoiceLine->cost;

            $articles[$invoiceLine->article_number]['avg_in_price'] = $articles[$invoiceLine->article_number]['total_cost'] / $articles[$invoiceLine->article_number]['total_units'];
            $brands[$invoiceLine->supplier_number]['avg_in_price'] = $brands[$invoiceLine->supplier_number]['total_cost'] / $brands[$invoiceLine->supplier_number]['total_units'];

            $articles[$invoiceLine->article_number]['avg_purchase_price'] = $articles[$invoiceLine->article_number]['total_amount'] / $articles[$invoiceLine->article_number]['total_units'];
            $brands[$invoiceLine->supplier_number]['avg_purchase_price'] = $brands[$invoiceLine->supplier_number]['total_amount'] / $brands[$invoiceLine->supplier_number]['total_units'];

            if ($articles[$invoiceLine->article_number]['total_amount'] > 0) {
                $articles[$invoiceLine->article_number]['margin'] = round(($articles[$invoiceLine->article_number]['total_amount'] - $articles[$invoiceLine->article_number]['total_cost']) / $articles[$invoiceLine->article_number]['total_amount'] * 100, 2);
            }
            if ($brands[$invoiceLine->supplier_number]['total_amount'] > 0) {
                $brands[$invoiceLine->supplier_number]['margin'] = round(( $brands[$invoiceLine->supplier_number]['total_amount'] -  $brands[$invoiceLine->supplier_number]['total_cost']) /  $brands[$invoiceLine->supplier_number]['total_amount'] * 100, 2);
            }

            if ($invoiceLine->date > $articles[$invoiceLine->article_number]['last_purchase_date']) {
                $articles[$invoiceLine->article_number]['last_purchase_date'] = $invoiceLine->date;
                $articles[$invoiceLine->article_number]['last_purchase_quantity'] = $invoiceLine->quantity;
            }

            if ($invoiceLine->date > $brands[$invoiceLine->supplier_number]['last_purchase_date']) {
                $brands[$invoiceLine->supplier_number]['last_purchase_date'] = $invoiceLine->date;
                $brands[$invoiceLine->supplier_number]['last_purchase_quantity'] = $invoiceLine->quantity;
            }
        }

        foreach ($invoiceLinesLast as $invoiceLine) {
            foreach ($perMonth as &$monthData) {
                if ($invoiceLine->date < $monthData['start_date_last'] || $invoiceLine->date > $monthData['end_date_last']) {
                    continue;
                }

                $monthData['turnover_last'] += $invoiceLine->amount;
                $monthData['cost_last'] += $invoiceLine->cost;
            }
        }

        // Calculate profit and margin on summary
        $summary['profit'] = $summary['turnover'] - $summary['cost'];
        if ($summary['turnover']) {
            $summary['margin'] = round(($summary['turnover'] - $summary['cost']) / $summary['turnover'] * 100, 2);
        }

        // Calculate profit and margin on per month data
        foreach ($perMonth as &$monthData) {
            $monthData['profit'] = $monthData['turnover'] - $monthData['cost'];
            if ($monthData['turnover']) {
                $monthData['margin'] = round(($monthData['turnover'] - $monthData['cost']) / $monthData['turnover'] * 100, 2);
            }

            $monthData['profit_last'] = $monthData['turnover_last'] - $monthData['cost_last'];
            if ($monthData['turnover_last']) {
                $monthData['margin_last'] = round(($monthData['turnover_last'] - $monthData['cost_last']) / $monthData['turnover_last'] * 100, 2);
            }
        }

        return ApiResponseController::success([
            'summary' => $summary,
            'per_month' => $perMonth,
            'articles' => $articles,
            'brands' => $brands,
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'external_id' => 'required|string',
            'customer_number' => 'required|string',
            'vat_number' => 'required|string',
            'org_number' => 'required|string',
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $customerData = [
            'external_id' => $request->external_id,
            'customer_number' => $request->customer_number,
            'vat_number' => $request->vat_number,
            'org_number' => $request->org_number,
            'name' => $request->name,
            'country' => (string)($request->country ?? ''),
            'shop_url' => (string)($request->shop_url ?? ''),
            'access_key' => Str::random(32),
            'credit_limit' => ($request->credit_limit ?? 0),
            'credit_terms' => ($request->credit_terms ?? 0)
        ];

        // Upload logo?
        if ($request->logo ?? '') {
            list($success, $logoPath, $logoURL) = $this->uploadLogo($request->logo);

            if ($success) {
                $customerData['logo_path'] = $logoPath;
                $customerData['logo_url'] = $logoURL;
            }
        }

        $customer = Customer::create($customerData);

        return ApiResponseController::success([$customer->toArray()]);
    }

    public function update(Request $request, Customer $customer)
    {
        $fillables = (new Customer)->getFillable();

        foreach ($request->all() as $key => $value) {
            if (in_array($key, ['logo_path', 'logo_url'])) {
                continue;
            }

            if (in_array($key, $fillables)) {
                $customer->{$key} = is_null($value) ? '' : $value;
            }
        }

        // Upload logo?
        if ($request->logo ?? '') {
            list($success, $logoPath, $logoURL) = $this->uploadLogo($request->logo);

            if ($success) {
                // Remove old logo
                if ($customer->logo_path) {
                    DoSpacesController::delete($customer->logo_path);
                }

                $customer->logo_path = $logoPath;
                $customer->logo_url = $logoURL;
            }
        }

        $customer->save();

        return ApiResponseController::success([$customer->toArray()]);
    }

    public function VATNumberToCustomerNumber(array $VATNumbers)
    {
        $VATNumbers = array_filter($VATNumbers);

        if (!$VATNumbers) {
            return [];
        }

        return Customer::whereIn('vat_number', $VATNumbers)
            ->where('customer_number', '!=', '')
            ->whereNotNull('customer_number')
            ->pluck('customer_number')
            ->toArray();
    }

    public function calculateSales()
    {
        $customers = Customer::all();

        if (!$customers) {
            return;
        }

        // Load all invoices within the last 30 days and summarize the sales per customer
        $customerSummary = [];

        $invoices = CustomerInvoice::where('date', '>=', date('Y-m-d', strtotime('-30 days')))
            ->get();

        foreach (($invoices ?: []) as $invoice) {
            foreach (($invoice->lines ?: []) as $invoiceLine) {
                if (!isset($customerSummary[$invoice->customer_number])) {
                    $customerSummary[$invoice->customer_number] = [
                        'sales' => 0,
                    ];
                }

                $customerSummary[$invoice->customer_number]['sales'] += $invoiceLine->amount;
            }
        }

        // Update each customer with the sales
        foreach ($customers as $customer) {
            $customer->sales_last_30_days = (float) ($customerSummary[$customer->customer_number]['sales'] ?? 0);
            $customer->save();
        }

        // Special update for LifeStyleStore
        $customer = Customer::where('customer_number', 'vendora')->first();
        if ($customer) {
            $customer->sales_last_30_days = 100_000_000;
            $customer->save();
        }
    }

    private function uploadLogo(string $url)
    {
        if (!$url) {
            return [false, '', ''];
        }

        // Extract the filename from the URL
        $path = parse_url(trim($url), PHP_URL_PATH);
        $filePath = 'customer/logos/' . basename($path);

        $imageContent = @file_get_contents($url);

        if (!$imageContent) {
            return [false, '', ''];
        }

        // Store the image
        $filePath = DoSpacesController::store($filePath, $imageContent, true);

        $fileURL = DoSpacesController::getURL($filePath);

        return [true, $filePath, $fileURL];
    }
}
