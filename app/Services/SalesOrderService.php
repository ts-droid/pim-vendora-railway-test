<?php

namespace App\Services;

use App\Actions\DispatchOrderCreated;
use App\Actions\DispatchOrderUpdated;
use App\Enums\LaravelQueues;
use App\Http\Controllers\CurrencyConvertController;
use App\Mail\SalesOrderConfirmation;
use App\Models\Address;
use App\Models\Customer;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesOrderLog;
use App\Models\SupplierArticlePrice;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class SalesOrderService
{
    public function updateSalesOrder(SalesOrder $salesOrder, array $data): SalesOrder
    {
        $addressService = new AddressService();

        // Update shipping address
        $shippingAddressData = [
            'full_name' => $data['shipping_full_name'] ?? null,
            'first_name' => $data['shipping_first_name'] ?? null,
            'last_name' => $data['shipping_last_name'] ?? null,
            'street_line_1' => $data['shipping_street_line_1'] ?? null,
            'street_line_2' => $data['shipping_street_line_2'] ?? null,
            'postal_code' => $data['shipping_postal_code'] ?? null,
            'city' => $data['shipping_city'] ?? null,
            'country_code' => $data['shipping_country_code'] ?? null,
        ];

        if ($salesOrder->shipping_address_id) {
            $shippingAddress = $addressService->updateAddress($salesOrder->shippingAddress, $shippingAddressData);
        }
        else {
            $shippingAddress = $addressService->createAddress($shippingAddressData);
        }


        // Update billing address
        $billingAddressData = [
            'full_name' => $data['billing_full_name'] ?? null,
            'first_name' => $data['billing_first_name'] ?? null,
            'last_name' => $data['billing_last_name'] ?? null,
            'street_line_1' => $data['billing_street_line_1'] ?? null,
            'street_line_2' => $data['billing_street_line_2'] ?? null,
            'postal_code' => $data['billing_postal_code'] ?? null,
            'city' => $data['billing_city'] ?? null,
            'country_code' => $data['billing_country_code'] ?? null,
        ];

        if ($salesOrder->billing_address_id) {
            $billingAddress = $addressService->updateAddress($salesOrder->billingAddress, $billingAddressData);
        }
        else {
            $billingAddress = $addressService->createAddress($billingAddressData);
        }


        // Update order lines (if set)
        $lines = $data['lines'] ?? [];
        if ($lines) {
            SalesOrderLine::where('sales_order_id', $salesOrder->id)->delete();

            $lineNumber = 1;

            foreach ($lines as $line) {
                if (!isset($line['line_number'])) {
                    $line['line_number'] = $lineNumber++;

                    $line['sales_person'] = ($line['sales_person'] ?? '') ?: ($data['sales_person'] ?? '');

                    $currency = $data['currency'] ?? $salesOrder->currency;

                    $this->insertOrderLine($salesOrder->id, $line, $currency);
                }
            }
        }


        // Update base order data
        $orderData = [
            'shipping_address_id' => $shippingAddress->id,
            'billing_address_id' => $billingAddress->id,
        ];

        if (isset($data['order_type'])) {
            $orderData['order_type'] = (string) $data['order_type'];
        }
        if (isset($data['order_number'])) {
            $orderData['order_number'] = (string) $data['order_number'];
        }
        if (isset($data['customer_ref_no'])) {
            $orderData['customer_ref_no'] = (string) $data['customer_ref_no'];
        }
        if (isset($data['status'])) {
            $orderData['status'] = (string) $data['status'];
        }
        if (isset($data['invoice_number'])) {
            $orderData['invoice_number'] = (string) $data['invoice_number'];
        }
        if (isset($data['sales_person'])) {
            $orderData['sales_person'] = (string) $data['sales_person'];
        }
        if (isset($data['date'])) {
            $orderData['date'] = (string) $data['date'];
        }
        if (isset($data['customer_number'])) {
            $orderData['customer'] = (string) $data['customer_number'];
        }
        if (isset($data['currency'])) {
            $orderData['currency'] = (string) $data['currency'];
        }
        if (isset($data['exchange_rate'])) {
            $orderData['exchange_rate'] = (float) $data['exchange_rate'];
        }
        if (isset($data['note'])) {
            $orderData['note'] = (string) $data['note'];
        }
        if (isset($data['internal_note'])) {
            $orderData['internal_note'] = (string) $data['internal_note'];
        }
        if (isset($data['store_note'])) {
            $orderData['store_note'] = (string) $data['store_note'];
        }
        if (isset($data['on_hold'])) {
            $orderData['on_hold'] = (int) $data['on_hold'];
        }
        if (isset($data['source'])) {
            $orderData['source'] = (string) $data['source'];
        }
        if (isset($data['phone'])) {
            $orderData['phone'] = (string) $data['phone'];
        }
        if (isset($data['email'])) {
            $orderData['email'] = (string) $data['email'];
        }
        if (isset($data['billing_email'])) {
            $orderData['billing_email'] = (string) $data['billing_email'];
        }
        if (isset($data['pay_method'])) {
            $orderData['pay_method'] = (string) $data['pay_method'];
        }

        $salesOrder->update($orderData);

        $this->createLog($salesOrder->id, 'This order was updated.');

        $this->calculateOrderTotals($salesOrder);

        $salesOrder->refresh();

        $skipDispatch = $data['skip_dispatch'] ?? false;
        if (!$skipDispatch) {
            (new DispatchOrderUpdated())->execute($salesOrder);
        }

        return $salesOrder;
    }

    public function createSalesOrder(array $data): SalesOrder
    {
        $currencyConverter = new CurrencyConvertController();
        $addressService = new AddressService();

        // Fetch the customer
        $customer = null;
        if (!empty($data['customer_number'])) {
            $customer = Customer::where('customer_number', $data['customer_number'])->first();
        }

        // Create the billing address
        $billingAddress = $addressService->createAddress([
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
        $shippingAddress = $addressService->createAddress([
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
            'customer_ref_no' => $data['customer_ref_no'] ?? '',
            'status' => (string) (($data['status'] ?? '') ?: 'Open'),
            'invoice_number' => $data['invoice_number'] ?? '',
            'sales_person' => (string) ($data['sales_person'] ?? ''),
            'date' => (string) (($data['date'] ?? '') ?: date('Y-m-d')),
            'customer' => $customer->customer_number ?? '',
            'currency' => $data['currency'],
            'order_total' => 0,
            'order_total_quantity' => 0,
            'exchange_rate' => (float) ($data['exchange_rate'] ?? $currencyRate),
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
            if (!isset($line['line_number'])) {
                $line['line_number'] = $lineNumber++;
            }

            $line['sales_person'] = ($line['sales_person'] ?? '') ?: ($data['sales_person'] ?? '');

            $this->insertOrderLine($salesOrder->id, $line, $data['currency']);
        }

        $this->calculateOrderTotals($salesOrder);

        $salesOrder->refresh();

        $this->createLog($salesOrder->id, 'This order was created.');

        $skipDispatch = $data['skip_dispatch'] ?? false;
        if (!$skipDispatch) {
            // (new DispatchOrderCreated())->execute($salesOrder);
        }

        $skipEmail = $data['skip_email'] ?? false;
        if (!$skipEmail && $salesOrder->email) {
            Mail::to($salesOrder->email)
                ->queue(new SalesOrderConfirmation($salesOrder))
                ->onQueue(LaravelQueues::DEFAULT->value);
        }

        return $salesOrder;
    }

    public function createLog(int $salesOrderID, string $description)
    {
        SalesOrderLog::create([
            'sales_order_id' => $salesOrderID,
            'description' => $description,
        ]);
    }

    private function calculateOrderTotals(SalesOrder $salesOrder): void
    {
        $totalAmount = 0;
        $totalQuantity = 0;

        foreach ($salesOrder->lines as $line) {
            $totalAmount += $line->unit_price * $line->quantity;
            $totalQuantity += $line->quantity;
        }

        $salesOrder->update([
            'order_total' => $totalAmount,
            'order_total_quantity' => $totalQuantity,
        ]);
    }

    private function insertOrderLine(int $salesOrderID, array $line, string $currency): SalesOrderLine
    {
        $currencyConverter = new CurrencyConvertController();

        // Get article cost
        $unitCost = $line['unit_cost'] ?? null;

        if ($unitCost === null) {
            $supplierPrice = SupplierArticlePrice::where('article_number', $line['article_number'])->first();
            if ($supplierPrice) {
                $unitCost = $currencyConverter->convert(
                    (float) $supplierPrice->price,
                    $supplierPrice->currency,
                    $currency
                );
            }
        }

        return SalesOrderLine::create([
            'sales_order_id' => $salesOrderID,
            'line_number' => $line['line_number'],
            'article_number' => $line['article_number'],
            'invoice_number' => (string) ($line['invoice_number'] ?? ''),
            'sales_person' => (string) $line['sales_person'] ?? '',
            'quantity' => (int) ($line['quantity'] ?? 0),
            'quantity_on_shipments' => (int) ($line['quantity_on_shipments'] ?? 0),
            'quantity_open' => (int) ($line['quantity_open'] ?? 0),
            'unit_cost' => $unitCost,
            'unit_price' => (float) ($line['unit_price'] ?? 0),
            'description' => (string) $line['description'] ?? '',
            'unbilled_amount' => (float) ($line['unbilled_amount'] ?? 0),
            'is_completed' => (int) ($line['is_completed'] ?? 0)
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
