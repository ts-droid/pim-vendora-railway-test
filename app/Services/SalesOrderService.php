<?php

namespace App\Services;

use App\Http\Controllers\CurrencyConvertController;
use App\Models\Address;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SupplierArticlePrice;
use Illuminate\Support\Str;

class SalesOrderService
{
    public function createSalesOrder(array $data): SalesOrder
    {
        $currencyConverter = new CurrencyConvertController();

        // Fetch the customer
        $customer = null;
        if (!empty($data['customer_number'])) {
            $customer = Customer::where('customer_number', $data['customer_number'])->first();
        }

        // Create the billing address
        $billingAddress = Address::create([
            'full_name' => $data['billing_full_name'] ?? null,
            'first_name' => $data['billing_first_name'] ?? null,
            'last_name' => $data['billing_last_name'] ?? null,
            'street_line_1' => $data['billing_street_line_1'] ?? null,
            'street_line_2' => $data['billing_street_line_2'] ?? null,
            'postal_code' => $data['billing_postal_code'] ?? null,
            'city' => $data['billing_city'] ?? null,
            'country_code' => $data['billing_country_code'] ?? null,
        ]);

        // Create the shipping address
        $shippingAddress = Address::create([
            'full_name' => $data['shipping_full_name'] ?? null,
            'first_name' => $data['shipping_first_name'] ?? null,
            'last_name' => $data['shipping_last_name'] ?? null,
            'street_line_1' => $data['shipping_street_line_1'] ?? null,
            'street_line_2' => $data['shipping_street_line_2'] ?? null,
            'postal_code' => $data['shipping_postal_code'] ?? null,
            'city' => $data['shipping_city'] ?? null,
            'country_code' => $data['shipping_country_code'] ?? null,
        ]);

        // Create the sales order
        $currencyRate = $currencyConverter->convert(1, $data['currency'], 'SEK');

        $salesOrder = SalesOrder::create([
            'order_type' => $data['order_type'] ?? 'WO',
            'order_number' => $this->generateOrderNumber($data['order_number'] ?? ''),
            'status' => 'Draft', // TODO: Implement this
            'sales_person' => (string) ($data['sales_person'] ?? ''),
            'date' => date('Y-m-d'),
            'customer' => $customer->customer_number ?? '',
            'currency' => $data['currency'],
            'order_total' => 0,
            'order_total_quantity' => 0,
            'exchange_rate' => $currencyRate,
            'note' => $data['note'] ?? '',
            'internal_note' => $data['internal_note'] ?? '',
            'store_note' => $data['store_note'] ?? '',
            'on_hold' => (int) ($data['on_hold'] ?? 0),
            'source' => $data['source'] ?? 'API',
            'shipping_address_id' => $shippingAddress->id,
            'billing_address_id' => $billingAddress->id,
            'phone' => $data['phone'] ?? '',
            'email' => $data['email'] ?? '',
            'billing_email' => $data['billing_email'] ?? '',
            'pay_method' => $data['pay_method'],
        ]);

        // Create the sales order lines
        $lines = $data['lines'] ?? [];
        $lineNumber = 1;

        foreach ($lines as $line) {

            // Get article cost
            $unitCost = 0;

            $supplierPrice = SupplierArticlePrice::where('article_number', $line['article_number'])->first();
            if ($supplierPrice) {
                $unitCost = $currencyConverter->convert(
                    (float) $supplierPrice->price,
                    $supplierPrice->currency,
                    'SEK'
                );
            }


            SalesOrderLine::create([
                'sales_order_id' => $salesOrder->id,
                'line_number' => $lineNumber++,
                'article_number' => $line['article_number'],
                'invoice_number' => '',
                'sales_person' => (string) ($data['sales_person'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'quantity_on_shipments' => (int) ($line['quantity_on_shipments'] ?? 0),
                'quantity_open' => (int) ($line['quantity_open'] ?? 0),
                'is_completed' => 0,
                'unit_cost' => $unitCost,
                'unit_price' => (float) (($line['unit_price'] ?? 0) * $currencyRate),
                'description' => (string) $line['description'] ?? '',
            ]);
        }

        $this->calculateOrderTotals($salesOrder);

        $salesOrder->refresh();

        return $salesOrder;
    }

    public function calculateOrderTotals(SalesOrder $salesOrder): void
    {
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($salesOrder->lines as $line) {
            $totalAmount += $line->unit_price * $line->quantity;
            $totalQuantity += $line->quantity;
        }

        $salesOrder::update([
            'order_total' => $totalAmount,
            'order_total_quantity' => $totalQuantity,
        ]);
    }

    private function generateOrderNumber($orderNumberDefault): string
    {
        $attempt = 1;

        do {
            if ($orderNumberDefault) {
                $orderNumber = $orderNumberDefault;

                if ($attempt > 1) {
                    $orderNumber .= '-' . $attempt;
                }
            }
            else {
                $orderNumber = Str::random(12);
            }

            $attempt++;
        } while ($this->orderNumberExists($orderNumber));

        return $orderNumber;
    }

    private function orderNumberExists(string $orderNumber): bool
    {
        return SalesOrder::where('order_number', $orderNumber)->exists();
    }
}
