<?php

namespace App\Http\Controllers;

use App\Models\CustomerInvoice;
use App\Models\CustomerInvoiceLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerInvoiceController extends Controller
{
    private array $invoices = [];

    public function get(Request $request)
    {
        $filter = $this->getModelFilter(CustomerInvoice::class, $request);

        $query = $this->getQueryWithFilter(CustomerInvoice::class, $filter);

        $chunkSize = env('DB_CHUNK_SIZE', 100);

        $query->with('customer', 'lines')->chunkById($chunkSize, function ($invoices) {
            foreach ($invoices as $invoice) {
                $this->invoices[] = $invoice;
            }
        });

        // Convert results to requested currency
        $convertToCurrency = $request->get('convert_to_currency', '');
        if ($convertToCurrency) {

            $currencyConverter = new CurrencyConvertController();

            foreach ($this->invoices as &$invoice) {
                // Convert main invoice
                $currencyConverter->convertArray($invoice, ['amount'], 'SEK', $convertToCurrency, $invoice['date']);

                // Convert invoice lines
                if ($invoice['lines']) {
                    foreach ($invoice['lines'] as &$line) {
                        $currencyConverter->convertArray($line, ['unit_price', 'amount', 'cost'], 'SEK', $convertToCurrency, $invoice['date']);
                    }
                }
            }

        }

        return ApiResponseController::success($this->invoices);
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
}
