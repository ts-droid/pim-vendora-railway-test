<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerInvoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class CustomerController extends Controller
{
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

        if ($pageNumber > 0) {
            $customers = $query->paginate($pageSize, ['*'], 'page_number', $pageNumber);
        } else {
            $customers = $query->get();
        }

        return ApiResponseController::success($customers->toArray());
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
                    Storage::disk('public')->delete($customer->logo_path);
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
        $filePath = 'customer/logos/' . time() . basename($path);

        $imageContent = @file_get_contents($url);

        if (!$imageContent) {
            return [false, '', ''];
        }

        // Save the image to the storage
        Storage::disk('public')->put($filePath, $imageContent);

        $fileURL = asset('storage/' . $filePath);

        return [true, $filePath, $fileURL];
    }
}
