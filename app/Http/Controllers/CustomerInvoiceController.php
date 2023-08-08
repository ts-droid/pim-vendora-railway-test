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
        $page = (int) $request->get('page', 1);
        $pageSize = (int) $request->get('page_size', 1000);

        $invoices = $this->getRows($request, $page, $pageSize);

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

    private function getRows(Request $request, int $page, int $pageSize)
    {
        $whereQuery = '';

        // Date filter
        if ($request->has('date')) {
            $date = $request->get('date');

            if (str_contains($date, ',')) {
                list($date1, $date2) = explode(',', $date);

                $whereQuery .= ' AND ci.date BETWEEN \'' . $date1 . '\' AND \'' . $date2 . '\'';
            }
            else {
                $whereQuery .= ' AND ci.date = \'' . $date . '\'';
            }
        }

        // Customer number filter
        if ($request->has('customer_number')) {
            $customerNumber = $request->get('customer_number');

            if (str_contains($customerNumber, ',')) {
                $customerNumbers = explode(',', $customerNumber);

                $whereQuery .= ' AND ci.customer_number IN (\'' . implode('\',\'', $customerNumbers) . '\')';
            }
            else {
                $whereQuery .= ' AND ci.customer_number = \'' . $customerNumber . '\'';
            }
        }

        if ($whereQuery) {
            $whereQuery = substr($whereQuery, 5);
            $whereQuery = 'WHERE ' . $whereQuery;
        }

        $invoices = DB::select(
            'SELECT ci.*
            FROM customer_invoices AS ci
            ' . $whereQuery . '
            ORDER BY ci.date DESC
            LIMIT ' . $pageSize . ' OFFSET ' . (($page - 1) * $pageSize)
        );

        $salesPersons = DB::select(
            'SELECT *
            FROM sales_people'
        );

        // Set invoice ID as array key and filter out data from invoices
        $invoiceIDs = [];
        $customerNumbers = [];

        $newInvoices = [];
        foreach ($invoices as $invoice) {
            $invoice = (array) $invoice;
            $newInvoices[$invoice['id']] = $invoice;

            $invoiceIDs[] = $invoice['id'];
            $customerNumbers[] = $invoice['customer_number'];
        }
        $invoices = $newInvoices;


        // Fetch and add invoices lines to invoices
        $articleFields = (new Article)->getFillable();
        foreach ($articleFields as &$articleField) {
            $articleField = 'a.' . $articleField . ' AS a_' . $articleField;
        }

        $supplierFields = (new Supplier)->getFillable();
        foreach ($supplierFields as &$supplierField) {
            $supplierField = 's.' . $supplierField . ' AS s_' . $supplierField;
        }

        $invoicesLines = DB::select(
            'SELECT cil.*,
                ' . implode(',', $articleFields) . ',
                ' . implode(',', $supplierFields) . '
            FROM customer_invoice_lines AS cil
            LEFT JOIN articles AS a ON a.article_number = cil.article_number
            LEFT JOIN suppliers AS s ON s.number = a.supplier_number
            WHERE cil.customer_invoice_id IN (' . implode(',', $invoiceIDs) . ')'
        );

        foreach ($invoicesLines as $invoicesLine) {
            $invoicesLine = (array) $invoicesLine;

            //$invoicesLine['sales_person_id'];

            $invoicesLine['article'] = [];
            $invoicesLine['supplier'] = [];
            $invoicesLine['sales_person'] = null;

            foreach ($invoicesLine as $key => $value) {
               if (str_starts_with($key, 'a_')) {
                   $invoicesLine['article'][substr($key, 2)] = $value;
                   unset($invoicesLine[$key]);
               }

               if (str_starts_with($key, 's_')) {
                   $invoicesLine['supplier'][substr($key, 2)] = $value;
                   unset($invoicesLine[$key]);
               }
            }

            foreach ($salesPersons as $salesPerson) {
                if ($invoicesLine['sales_person_id'] == $salesPerson->external_id) {
                    $invoicesLine['sales_person'] = (array) $salesPerson;
                    break;
                }
            }

            $invoice = $invoices[$invoicesLine['customer_invoice_id']];

            if (!isset($invoice['lines'])) {
                $invoice['lines'] = [];
            }

            $invoices[$invoicesLine['customer_invoice_id']]['lines'][] = $invoicesLine;
        }


        // Fetch and add customers to invoices
        $customers = DB::select(
            'SELECT *
            FROM customers
            WHERE customer_number IN (\'' . implode('\',\'', $customerNumbers) . '\')'
        );

        $newCustomers = [];
        foreach ($customers as $customer) {
            $customer = (array) $customer;
            $newCustomers[$customer['customer_number']] = $customer;
        }
        $customers = $newCustomers;

        foreach ($invoices as &$invoice) {
            $invoice['customer'] = $customers[$invoice['customer_number']] ?? null;
        }


        // Reset the key to be the index
        $invoices = array_values($invoices);

        return $invoices;
    }
}
