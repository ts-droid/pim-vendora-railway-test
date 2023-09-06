<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CustomerInvoiceController extends Controller
{
    public function get(Request $request)
    {
        $performanceLogController = new PerformanceLogController();
        $performanceLogController->start('request');

        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 1000);
        $simpleData = (bool) $request->get('simple_data', 0);

        $invoices = $this->getRows($request, $page, $pageSize, $simpleData);

        // Convert results to requested currency
        $convertToCurrency = $request->get('convert_to_currency', '');
        if ($convertToCurrency) {

            $currencyConverter = new CurrencyConvertController();

            foreach ($invoices as &$invoice) {
                // Convert main invoice
                $currencyConverter->convertArray($invoice, ['amount'], 'SEK', $convertToCurrency, $invoice['date']);

                // Convert invoice lines
                if ($invoice['lines']) {
                    foreach ($invoice['lines'] as &$line) {
                        $currencyConverter->convertArray($line, ['unit_price', 'amount', 'cost'], 'SEK', $convertToCurrency, $invoice['date']);

                        if ($line['article'] ?? null) {
                            $currencyConverter->convertArray($line['article'], ['cost_price_avg', 'external_cost'], 'SEK', $convertToCurrency, date('Y-m-d'));
                        }
                    }
                }
            }

        }

        $performanceLogController->end('request');

        return ApiResponseController::success([
            'results' => $invoices,
            'page' => $page,
            'next_page' => ((count($invoices) == $pageSize) ? ($page + 1) : null),
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'invoice_number' => 'required|string',
            'date' => 'required|string',
            'customer_number' => 'required|string',
            'currency' => 'required|string',
            'amount' => 'required|numeric',
            'lines' => 'required|array',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return ApiResponseController::error($errors[0]);
        }

        $invoice = CustomerInvoice::create([
            'invoice_number' => ($request->invoice_number ?? ''),
            'date' => ($request->date ?? ''),
            'status' => ($request->status ?? ''),
            'customer_number' => ($request->customer_number ?? ''),
            'credit_terms' => ($request->credit_terms ?? ''),
            'currency' => ($request->currency ?? ''),
            'amount' => ($request->amount ?? ''),
        ]);

        foreach ($request->lines as $line) {
            $invoiceLine = CustomerInvoiceLine::create([
                'customer_invoice_id' => $invoice->id,
                'line_key' => (string) ($line['line_key'] ?? ''),
                'article_number' => (string) ($line['article_number'] ?? ''),
                'description' => (string) ($line['description'] ?? ''),
                'order_number' => (string) ($line['order_number'] ?? ''),
                'shipment_number' => (string) ($line['shipment_number'] ?? ''),
                'line_type' => (string) ($line['line_type'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'unit_price' => (float) ($line['unit_price'] ?? 0),
                'amount' => (float) ($line['amount'] ?? 0),
                'cost' => (float) ($line['cost'] ?? 0),
                'sales_person_id' => (string) ($line['sales_person_id'] ?? ''),
            ]);
        }

        return ApiResponseController::success([$invoice->toArray()]);
    }

    public function update(Request $request, CustomerInvoice $invoice)
    {
        $fillables = (new CustomerInvoice)->getFillable();
        $fillablesLine = (new CustomerInvoiceLine)->getFillable();

        // Update the invoice
        foreach ($request->all() as $key => $value) {
            if (in_array($key, $fillables)) {
                $invoice->{$key} = $value;
            }
        }

        $invoice->save();

        // Update invoice lines
        foreach (($request->lines ?? []) as $line) {
            $invoiceLine = CustomerInvoiceLine::where([
                ['customer_invoice_id', '=', $invoice->id],
                ['line_key', '=', ($line['line_key'] ?? '')]
            ])->first();

            if ($invoiceLine) {
                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $invoiceLine->{$key} = $value;
                    }
                }

                $invoiceLine->save();
            }
        }

        return ApiResponseController::success([$invoice->toArray()]);
    }

    private function getRows(Request $request, int $page, int $pageSize, bool $simpleData = false)
    {
        $whereQuery = '';

        // Date filter
        if ($request->has('date')) {
            $date = $request->get('date');

            if (str_contains($date, ',')) {
                list($date1, $date2) = explode(',', $date);

                $whereQuery .= ' AND date BETWEEN \'' . $date1 . '\' AND \'' . $date2 . '\'';
            }
            else {
                $whereQuery .= ' AND date = \'' . $date . '\'';
            }
        }

        // Customer number filter
        if ($request->has('customer_number')) {
            $customerNumber = $request->get('customer_number');

            if (str_contains($customerNumber, ',')) {
                $customerNumbers = explode(',', $customerNumber);

                $whereQuery .= ' AND customer_number IN (\'' . implode('\',\'', $customerNumbers) . '\')';
            }
            else {
                $whereQuery .= ' AND customer_number = \'' . $customerNumber . '\'';
            }
        }

        if ($whereQuery) {
            $whereQuery = substr($whereQuery, 5);
            $whereQuery = 'WHERE ' . $whereQuery;
        }

        $articleFields = [];
        $articleFillables = (new Article)->getFillable();
        foreach ($articleFillables as $articleFillable) {
            if (str_contains($articleFillable, 'shop_description')
                || str_contains($articleFillable, 'shop_title')
                || str_contains($articleFillable, 'width')
                || str_contains($articleFillable, 'height')
                || str_contains($articleFillable, 'depth')) {
                continue;
            }

            $articleFields[] = $articleFillable;
        }

        $supplierFields = [];
        $supplierFillables = (new Supplier)->getFillable();
        foreach ($supplierFillables as $supplierFillable) {
            $supplierFields[] = $supplierFillable;
        }

        $invoiceFields = ['*'];
        $invoiceLineFields = ['*'];

        if ($simpleData) {
            $invoiceFields = ['id', 'date', 'customer_number'];
            $articleFields = ['id', 'article_number', 'supplier_number', 'cost_price_avg', 'external_cost'];
            $supplierFields = ['id', 'number', 'name', 'brand_name'];
            $invoiceLineFields = ['id', 'customer_invoice_id', 'sales_person_id', 'amount', 'cost', 'quantity', 'article_number', 'description'];
        }

        $invoices = DB::select(
            'SELECT ' . implode(',', $invoiceFields) . '
            FROM customer_invoices
            ' . $whereQuery . '
            ORDER BY date DESC
            LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize)
        );

        $salesPersons = DB::select(
            'SELECT *
            FROM sales_people'
        );

        $articles = DB::select(
            'SELECT ' . implode(',', $articleFields) . '
            FROM articles'
        );

        $suppliers = DB::select(
            'SELECT ' . implode(',', $supplierFields) . '
            FROM suppliers'
        );

        $customers = DB::select(
            'SELECT *
            FROM customers'
        );

        $invoices = $this->setValueAsKey($invoices, 'id');
        $articles = $this->setValueAsKey($articles, 'article_number');
        $suppliers = $this->setValueAsKey($suppliers, 'number');
        $customers = $this->setValueAsKey($customers, 'customer_number');
        $salesPersons = $this->setValueAsKey($salesPersons, 'external_id');

        // Store invoiceID's in a temporary table
        $invoiceIDs = array_keys($invoices);

        $invoicesLines = [];

        if ($invoiceIDs) {
            $invoicesLines = DB::select(
                'SELECT ' . implode(',', $invoiceLineFields) . '
                FROM customer_invoice_lines
                WHERE customer_invoice_id IN (' . implode(',', $invoiceIDs) . ')'
            );
        }

        // Connect data to the invoice lines
        foreach ($invoicesLines as $invoicesLine) {
            $invoicesLine = (array) $invoicesLine;

            $invoicesLine['article'] = $articles[$invoicesLine['article_number']] ?? null;
            $invoicesLine['article']['supplier'] = $suppliers[$invoicesLine['article']['supplier_number'] ?? ''] ?? null;
            $invoicesLine['sales_person'] = $salesPersons[$invoicesLine['sales_person_id'] ?? ''] ?? null;

            $invoice = $invoices[$invoicesLine['customer_invoice_id']];

            if (!isset($invoice['lines'])) {
                $invoice['lines'] = [];
            }

            $invoices[$invoicesLine['customer_invoice_id']]['lines'][] = $invoicesLine;
        }

        // Connect customers to the invoices
        foreach ($invoices as &$invoice) {
            $invoice['customer'] = $customers[$invoice['customer_number']] ?? null;
        }

        // Reset the key to be the index
        $invoices = array_values($invoices);

        return $invoices;
    }


    private function setValueAsKey(array $array, string $key)
    {
        $newArray = [];

        foreach ($array as $item) {
            $item = (array) $item;
            $newArray[$item[$key]] = $item;
        }

        return $newArray;
    }
}
