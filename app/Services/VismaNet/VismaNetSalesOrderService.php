<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\Api\SalesOrderApiController;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\SalesOrderService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VismaNetSalesOrderService extends VismaNetApiService
{
    const WAREHOUSE_ID = 1; // 1 = Huvudlager

    const RETAIL_CUSTOMER_NUMBER = 10460; // Retail customer in Visma.net

    public function createShipment(SalesOrder $salesOrder)
    {
        $salesOrderService = new SalesOrderService();

        $postData = [
            'orderType' => $salesOrder->order_type,
            'shipmentDate' => (new DateTime())->format('Y-m-d H:i:s'),
            'shipmentWarehouse' => self::WAREHOUSE_ID
        ];

        $response = $this->callAPI('POST', '/v2/salesorder/' . $salesOrder->order_number . '/action/createShipment', $postData);

        if (!$response['success']) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to create shipment in Visma.net for this order.');
            throw new \Exception('Failed to create shipment in Visma.net.');
        }

        $salesOrderService->createLog($salesOrder->id, 'Created shipment in Visma.net for this order.');
    }

    public function sendSalesOrder(SalesOrder $salesOrder): void
    {
        // Check if order already exists in Visma.net
        $response = $this->callAPI('GET', '/v2/salesorder/' . $salesOrder->order_number);
        if (!empty($response['response']['orderNo'])) {
            $this->updateSalesOrder($salesOrder);
            return;
        }

        $salesOrderService = new SalesOrderService();

        // Fetch customer from Visma.net
        $customerNumber = $salesOrder->customer ?: self::RETAIL_CUSTOMER_NUMBER;
        $customer = $this->getCustomer($customerNumber);

        if (!$customer) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net. Customer ' . $customerNumber . ' not found.');
            throw new \Exception('Failed to send order to Visma.net. Customer ' . $customerNumber . ' not found.');
        }

        $orderData = $this->getOrderData($salesOrder, $customerNumber);

        $response = $this->callAPI('POST', '/v3/salesorders/', $orderData);

        if ($response['http_code'] !== 201) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net (' . ($response['response']['details']['message'] ?? 'unknown-error') . ')');
            throw new \Exception('Failed to send order to Visma.net. Error message: ' . ($response['response']['details']['message'] ?? 'unknown-error') . ' (Code: ' . $response['http_code'] . ')');
        }

        // Update the order line numbers in the database
        $linesResponse = $this->callAPI('GET', '/v2/salesorders/' . $salesOrder->order_type . '/' . $salesOrder->order_number . '/lines');
        $remoteLines = $linesResponse['response']['value'] ?? null;

        if (!$remoteLines) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net. Post request was successful, but fetching the order lines failed.');
            throw new \Exception('Failed to send order to Visma.net. Post request was successful, but fetching the order lines failed.');
        }

        foreach ($remoteLines as $line) {
            $lineNumber = $line['lineId'];
            $articleNumber = $line['inventory']['id'];
            $quantity = $line['quantity'];

            SalesOrderLine::where('sales_order_id', $salesOrder->id)
                ->where('article_number', $articleNumber)
                ->where('quantity', $quantity)
                ->update(['line_number' => $lineNumber]);
        }

        $salesOrderService->createLog($salesOrder->id, 'This order was sent to Visma.net');
    }

    public function updateSalesOrder(SalesOrder $salesOrder): void
    {
        // Check if the order exists in Visma.net
        $response = $this->callAPI('GET', '/v2/salesorder/' . $salesOrder->order_number);
        if (empty($response['response']['orderNo'])) {
            $this->sendSalesOrder($salesOrder);
            return;
        }

        $salesOrderService = new SalesOrderService();

        // Fetch customer from Visma.net
        $customerNumber = $salesOrder->customer ?: self::RETAIL_CUSTOMER_NUMBER;
        $customer = $this->getCustomer($customerNumber);

        if (!$customer) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update this order in Visma.net. Customer ' . $customerNumber . ' not found.');
            throw new \Exception('Failed to update order in Visma.net. Customer ' . $customerNumber . ' not found.');
        }

        $orderData = $this->getOrderData($salesOrder, $customerNumber);
        $linesData = [
            'lines' => [],
            'updateCompleted' => true
        ];

        foreach ($orderData['lines'] as $line) {
            $linesData['lines'][] = $line;
        }

        $orderResponse = $this->callAPI('PATCH', '/v3/salesorders/' . $salesOrder->order_type . '/' . $salesOrder->order_number, $orderData);
        if ($orderResponse['http_code'] !== 202) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update this order in Visma.net. API request failed to update order.');
            throw new \Exception('Failed to update order in Visma.net. API request failed to update order.');
        }

        $linesResponse = $this->callAPI('PATCH', '/v3/salesorders/' . $salesOrder->order_type . '/' . $salesOrder->order_number . '/lines', $linesData);
        if ($linesResponse['http_code'] !== 202) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update this order in Visma.net. API request failed to update order lines.');
            throw new \Exception('Failed to update order in Visma.net. API request failed to update order lines.');
        }

        $salesOrderService->createLog($salesOrder->id, 'This order was updated in Visma.net');
    }

    public function fetchSalesOrders(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_sales_orders_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-1 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $orders = $this->getPagedResult('/v2/salesorder', $params);

        if ($orders) {
            foreach ($orders as $order) {
                $fetchedData = true;

                if (!$order || !is_array($order)) {
                    continue;
                }

                $this->importOrder($order);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_sales_orders_fetch' => $fetchTime]);
        }
    }

    private function importOrder(array $order): void
    {
        if (empty($order['orderType']) || empty($order['orderNo'])) {
            return;
        }

        $orderData = [
            'order_type' => (string) $order['orderType'],
            'sales_person' => (string) ($order['salesPerson']['id'] ?? ''),
            'customer_number' => (string) ($order['customer']['internalId'] ?? ''),
            'currency' => (string) ($order['currency'] ?? ''),
            'note' => (string) ($order['note'] ?? ''),
            'order_number' => (string) $order['orderNo'],
            'customer_ref_no' => (string) ($order['customerRefNo'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'invoice_number' => (string) ($order['invoiceNbr'] ?? ''),
            'date' => (string) ($order['date'] ?? ''),
            'exchange_rate' => (float) ($order['exchangeRate'] ?? 0),
            'on_hold' => (($order['hold'] ?? false) ? 1 : 0),
            'source' => 'visma_net',
            'phone' => (string) ((($order['soShippingContact']['phone1'] ?? '') ?: ($order['soBillingContact']['phone1'] ?? ''))),
            'email' => (string) ((($order['soShippingContact']['email'] ?? '') ?: ($order['soBillingContact']['email'] ?? ''))),
            'billing_email' => (string) ($order['soBillingContact']['email'] ?? ''),
            'pay_method' => 'invoice',

            'billing_full_name' => (string) ($order['soBillingContact']['name'] ?? ''),
            'billing_first_name' => $this->getFirstNameFromFullName($order['soBillingContact']['name'] ?? ''),
            'billing_last_name' => $this->getLastNameFromFullName($order['soBillingContact']['name'] ?? ''),
            'billing_street_line_1' => (string) ($order['soBillingAddress']['addressLine1'] ?? ''),
            'billing_street_line_2' => (string) ($order['soBillingAddress']['addressLine2'] ?? ''),
            'billing_postal_code' => (string) ($order['soBillingAddress']['postalCode'] ?? ''),
            'billing_city' => (string) ($order['soBillingAddress']['city'] ?? ''),
            'billing_country_code' => (string) ($order['soBillingAddress']['country']['id'] ?? ''),

            'shipping_full_name' => (string) ($order['soShippingContact']['name'] ?? ''),
            'shipping_first_name' => $this->getFirstNameFromFullName($order['soShippingContact']['name'] ?? ''),
            'shipping_last_name' => $this->getLastNameFromFullName($order['soShippingContact']['name'] ?? ''),
            'shipping_street_line_1' => (string) ($order['soShippingAddress']['addressLine1'] ?? ''),
            'shipping_street_line_2' => (string) ($order['soShippingAddress']['addressLine2'] ?? ''),
            'shipping_postal_code' => (string) ($order['soShippingAddress']['postalCode'] ?? ''),
            'shipping_city' => (string) ($order['soShippingAddress']['city'] ?? ''),
            'shipping_country_code' => (string) ($order['soShippingAddress']['country']['id'] ?? ''),

            'skip_dispatch' => 1,
            'skip_email' => 1,
            'lines' => []
        ];

        foreach (($order['lines'] ?? []) as $line) {
            $articleNumber = $line['inventory']['number'] ?? '';
            $lineNumber = $line['lineNbr'] ?? '';

            if (!$articleNumber || !$lineNumber) {
                continue;
            }

            $orderData['lines'][] = [
                'line_number' => $lineNumber,
                'article_number' => $articleNumber,
                'invoice_number' => (string) ($line['invoiceNbr'] ?? ''),
                'sales_person' => ($line['salesPerson']['id'] ?? ''),
                'quantity' => (int) ($line['quantity'] ?? 0),
                'quantity_on_shipments' => (int) ($line['qtyOnShipments'] ?? 0),
                'quantity_open' => (int) ($line['openQty'] ?? 0),
                'unit_cost' => (float) ($line['unitCost'] ?? 0),
                'unit_price' => (float) ($line['unitPrice'] ?? 0),
                'unbilled_amount' => (float) ($line['unbilledAmount'] ?? 0),
                'description' => (string) ($line['lineDescription'] ?? ''),
                'is_completed' => (int) ($line['completed'] ?? 0),
            ];

            trigger_stock_sync($articleNumber);
        }

        $salesOrderService = new SalesOrderService();
        $salesOrderApiController = new SalesOrderApiController($salesOrderService);

        $existingSalesOrder = SalesOrder::where('order_number', $order['orderNo'])->first();

        if ($existingSalesOrder) {
            // Update the order
            $response = $salesOrderApiController->update(new Request($orderData), $existingSalesOrder);
        }
        else {
            // Create a new order
            $response = $salesOrderApiController->store(new Request($orderData));
        }

        $response = json_decode($response->content(), true);
        if (!$response['success']) {
            Log::error($response['error_message']);
        }
    }

    private function getOrderData(SalesOrder $salesOrder, string $customerNumber, bool $withLineIDs = false): array
    {
        $orderData = [
            'type' => $salesOrder->order_type,
            'orderId' => $salesOrder->order_number,
            'currencyId' => $salesOrder->currency,
            'status' => $salesOrder->on_hold ? 'Hold' : 'Open',
            'customer' => [
                'id' => $customerNumber
            ],
            'note' => '',
            'overrideNumberSeries' => true,
            'orderLines' => [],
        ];

        $payMethodCode = $this->getPaymentMethodCode($salesOrder->pay_method, $salesOrder->currency);
        if ($payMethodCode) {
            $orderData['paymentSettings']['paymentMethodId'] = $payMethodCode;
        }

        $cashAccountCode = $this->getCashAccountCode($salesOrder->pay_method, $salesOrder->currency);
        if ($cashAccountCode) {
            $orderData['paymentSettings']['cashAccountId'] = $cashAccountCode;
        }

        if ($salesOrder->sales_person) {
            $orderData['salesPersonId'] = $salesOrder->sales_person;
        }

        if ($salesOrder->note) {
            $orderData['note'] = $salesOrder->note;
        }
        if ($salesOrder->internal_note) {
            $orderData['note'] = ($orderData['note'] ? PHP_EOL . PHP_EOL : '') . $salesOrder->internal_note;
        }
        if ($salesOrder->store_note) {
            $orderData['note'] = ($orderData['note'] ? PHP_EOL . PHP_EOL : '') . $salesOrder->store_note;
        }

        if ($salesOrder->shipping_address_id && $salesOrder->shippingAddress) {
            $orderData['shipping'] = [
                'address' => [
                    'line1' => $salesOrder->shippingAddress->street_line_1,
                    'line2' => $salesOrder->shippingAddress->street_line_2,
                    'line3' => '',
                    'postalCode' => $salesOrder->shippingAddress->postal_code,
                    'city' => $salesOrder->shippingAddress->city,
                    'countryId' => $salesOrder->shippingAddress->country_code,
                ],
                'contact' => [
                    'name' => $salesOrder->shippingAddress->full_name,
                    'attention' => '',
                    'phone1' => $salesOrder->phone,
                    'email' => $salesOrder->email,
                ]
            ];
        }

        if ($salesOrder->billing_address_id && $salesOrder->billingAddress) {
            $orderData['billing'] = [
                'address' => [
                    'line1' => $salesOrder->billingAddress->street_line_1,
                    'line2' => $salesOrder->billingAddress->street_line_2,
                    'line3' => '',
                    'postalCode' => $salesOrder->billingAddress->postal_code,
                    'city' => $salesOrder->billingAddress->city,
                    'countryId' => $salesOrder->billingAddress->country_code,
                ],
                'contact' => [
                    'name' => $salesOrder->billingAddress->full_name,
                    'attention' => '',
                    'phone1' => $salesOrder->phone,
                    'email' => $salesOrder->email,
                ]
            ];
        }

        foreach ($salesOrder->lines as $orderLine) {
            $lineData = [
                'inventoryId' => $orderLine->article_number,
                'description' => $orderLine->description,
                'quantity' => $orderLine->quantity,
                'unitCost' => $orderLine->unit_cost,
                'unitPrice' => $orderLine->unit_price,
                'warehouseId' => self::WAREHOUSE_ID
            ];

            if ($orderLine->sales_person) {
                $lineData['salesPersonId'] = $orderLine->sales_person;
            }

            if ($salesOrder->order_type === 'PR') {
                $lineData['reasonCode'] = '20';
            }

            if ($withLineIDs) {
                $lineData['lineId'] = $orderLine->line_number;
            }

            $orderData['orderLines'][] = $lineData;
        }

        return $orderData;
    }

    private function getCustomer(string $customerNumber): ?array
    {
        $response = $this->callAPI('GET', '/v1/customer/' . $customerNumber);
        return $response['response'] ?? null;
    }

    private function getPaymentMethodCode(string $payMethod, string $currency): ?int
    {
        $matrix = [
            'invoice' => [
                'EUR' => 24,
                'DKK' => 25,
                'NOK' => 26,
                'DEFAULT' => 21
            ],
            'klarna' => [
                'DEFAULT' => 22,
            ],
        ];

        if (isset($matrix[$payMethod][$currency])) {
            return $matrix[$payMethod][$currency];
        }

        return $matrix[$payMethod]['DEFAULT'] ?? null;
    }

    private function getCashAccountCode(string $payMethod, string $currency): ?int
    {
        $matrix = [
            'invoice' => [
                'SEK' => 1930,
                'NOK' => 1983,
                'DKK' => 1982,
                'EUR' => 1980,
            ],
            'klarna' => [
                'SEK' => 1930,
                'NOK' => 1930,
                'DKK' => 1930,
                'EUR' => 1930,
            ]
        ];

        return $matrix[$payMethod]['currency'] ?? null;
    }

    private function getFirstNameFromFullName(string $fullName): string
    {
        $nameParts = explode(' ', $fullName);
        return $nameParts[0] ?? '';
    }

    private function getLastNameFromFullName(string $fullName): string
    {
        $nameParts = explode(' ', $fullName);
        return isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
    }
}
