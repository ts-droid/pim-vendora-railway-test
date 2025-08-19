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
    public function createShipment(SalesOrder $salesOrder, bool $isDirectDelivery = false)
    {
        $salesOrderService = new SalesOrderService();

        $postData = [
            'orderType' => $salesOrder->order_type,
            'shipmentDate' => (new DateTime())->format('Y-m-d H:i:s'),
            'shipmentWarehouse' => ($isDirectDelivery ? self::WAREHOUSE_DIRECT_ID : self::WAREHOUSE_ID)
        ];

        $response = $this->callAPI('POST', '/v2/salesorder/' . $salesOrder->order_number . '/action/createShipment', $postData);

        if (!$response['success']) {
            $salesOrder->update(['has_sync_error' => 1]);

            $salesOrderService->createLog($salesOrder->id, 'Failed to create shipment in Visma.net for this order.');

            throw new \Exception('Failed to create shipment in Visma.net. ' . json_encode($response));
        }

        $salesOrder->update([
            'status_shipment_created' => 1,
            'has_sync_error' => 0
        ]);

        $salesOrderService->createLog($salesOrder->id, 'Created shipment in Visma.net for this order.');
    }

    public function resetSalesOrder(SalesOrder $salesOrder): bool
    {
        $cancelResponse = $this->cancelSalesOrder($salesOrder);
        $cancelSuccess = $cancelResponse['success'];

        $salesOrderService = new SalesOrderService();

        if (!$cancelSuccess) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to reset order sync state to Visma.net.');
            return false;
        }

        $salesOrderService->createLog($salesOrder->id, 'Reset order sync state to Visma.net.');
        return true;
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
            $salesOrder->update(['has_sync_error' => 1]);

            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net. Customer ' . $customerNumber . ' not found.');

            throw new \Exception('Failed to send order to Visma.net. Customer ' . $customerNumber . ' not found.');
        }

        $orderData = $this->getOrderData($salesOrder, $customerNumber);

        $response = $this->callAPI('POST', '/v2/salesorder', $orderData);

        if ($response['http_code'] !== 201) {
            $salesOrder->update(['has_sync_error' => 1]);

            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net (' . ($response['response']['message'] ?? 'unknown-error') . ')');

            throw new \Exception('Failed to send order to Visma.net. (Code: ' . $response['http_code'] . ') Response: ' . json_encode($response));
        }

        // Update the order line numbers in the database
        $linesResponse = $this->callAPI('GET', '/v2/salesorder/' . $salesOrder->order_number);
        $remoteLines = $linesResponse['response']['lines'] ?? null;

        if (!$remoteLines) {
            $salesOrder->update(['has_sync_error' => 1]);

            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net. Post request was successful, but fetching the order lines failed.');

            throw new \Exception('Failed to send order to Visma.net. Post request was successful, but fetching the order lines failed.');
        }

        foreach ($remoteLines as $line) {
            $lineNumber = $line['lineNbr'];
            $articleNumber = $line['inventory']['number'];
            $quantity = $line['quantity'];

            SalesOrderLine::where('sales_order_id', $salesOrder->id)
                ->where('article_number', $articleNumber)
                ->where('quantity', $quantity)
                ->update(['line_number' => $lineNumber]);
        }

        $salesOrder->update([
            'status_sent_external' => 1,
            'has_sync_error' => 0
        ]);

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

        $orderData = $this->getOrderData($salesOrder, $customerNumber, true);

        $orderResponse = $this->callAPI('PATCH', '/v1/salesorder/' . $salesOrder->order_number, $orderData);
        if ($orderResponse['http_code'] !== 202) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update this order in Visma.net. API request failed to update order.');
            throw new \Exception('Failed to update order in Visma.net. API request failed to update order.');
        }

        $salesOrderService->createLog($salesOrder->id, 'This order was updated in Visma.net');
    }

    public function cancelSalesOrder(SalesOrder $salesOrder): array
    {
        $fetchResponse = $this->callAPI('GET', '/v2/salesorder/', $salesOrder->order_number);
        if (empty($fetchResponse['response']['orderNo'])) {
            return [
                'success' => false,
                'message' => 'Order not found in Visma.net.'
            ];
        }

        $orderType = $fetchResponse['response']['orderType'];

        $cancelResponse = $this->callAPI('POST', '/v2/salesorder/' . $salesOrder->order_number . '/action/cancelSalesOrder', [
            'orderType' => $orderType,
        ]);

        if (!($cancelResponse['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Failed to cancel sales order in Visma.net'
            ];
        }

        return [
            'success' => true,
            'message' => 'Order canceled successfully.'
        ];
    }

    public function fetchSalesOrder(string $orderNumber): array
    {
        $response = $this->callAPI('GET', '/v2/salesorder/' . $orderNumber);

        if (!$response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to fetch sales order from visma'
            ];
        }

        $salesOrder = $response['response'] ?? null;

        return $this->importOrder($salesOrder);
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

                $importResult = $this->importOrder($order);

                if (!$importResult['success']) {
                    Log::channel('vismanet')->warning('Failed to import sales order. Error message: ' . $importResult['message']);
                }
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_sales_orders_fetch' => $fetchTime]);
        }
    }

    private function importOrder(array $order): array
    {
        if (empty($order['orderType']) || empty($order['orderNo'])) {
            return [
                'success' => false,
                'message' => 'Missing order type or order number.'
            ];
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
            'billing_street_line_1' => (string) (($order['soBillingAddress']['addressLine1'] ?? '') ?: 'Ladugårdsvägen 1'),
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

        $vatRate = 0;
        if (!empty($order['vatTaxableTotal']) && !empty($order['orderTotal'])) {
            $vatRate = round((($order['orderTotal'] / $order['vatTaxableTotal']) - 1) * 100);
        }

        foreach (($order['lines'] ?? []) as $line) {
            $articleNumber = $line['inventory']['number'] ?? '';
            $lineNumber = $line['lineNbr'] ?? '';
            $warehouseID = $line['waregouse']['id'] ?? self::WAREHOUSE_ID;

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
                'vat_rate' => (float) $vatRate,
                'is_direct' => ($warehouseID === self::WAREHOUSE_DIRECT_ID ? 1 : 0),
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

        return [
            'success' => $response['success'],
            'message' => ($response['error_message'] ?? ''),
        ];
    }

    private function getOrderData(SalesOrder $salesOrder, string $customerNumber, bool $isUpdate = false): array
    {
        $orderData = [
            'orderType' => ['value' => $salesOrder->order_type],
            'orderNumber' => ['value' => $salesOrder->order_number],
            'currency' => ['value' => $salesOrder->currency],
            'hold' => ['value' => ($salesOrder->on_hold ? true : false)],
            'customer' => ['value' => $customerNumber],
            'note' => ['value' => ''],
            'lines' => [],
        ];

        $payMethodCode = $this->getPaymentMethodCode($salesOrder->pay_method, $salesOrder->currency);
        if ($payMethodCode) {
            $orderData['paymentMethod']['value'] = $payMethodCode;
        }

        $cashAccountCode = $this->getCashAccountCode($salesOrder->pay_method, $salesOrder->currency);
        if ($cashAccountCode) {
            $orderData['cashAccount']['value'] = $cashAccountCode;
        }

        if ($salesOrder->sales_person) {
            $orderData['salesPerson']['value'] = $salesOrder->sales_person;
        }

        $orderData['note']['value'] = '';

        if ($salesOrder->note) {
            $orderData['note']['value'] = $salesOrder->note;
        }
        if ($salesOrder->internal_note) {
            $orderData['note']['value'] = ($orderData['note']['value'] ? PHP_EOL . PHP_EOL : '') . $salesOrder->internal_note;
        }
        if ($salesOrder->store_note) {
            $orderData['note']['value'] = ($orderData['note']['value'] ? PHP_EOL . PHP_EOL : '') . $salesOrder->store_note;
        }

        if (!$orderData['note']['value']) {
            unset($orderData['note']['value']);
        }

        if ($salesOrder->shipping_address_id && $salesOrder->shippingAddress) {
            $orderData['soShippingContact'] = [
                'value' => [
                    'overrideContact' => ['value' => true],
                    'name' => ['value' => $salesOrder->shippingAddress->full_name],
                    'email' => ['value' => $salesOrder->email],
                    'phone1' => ['value' => $salesOrder->phone],
                ]
            ];

            if ($salesOrder->shippingAddress->attention) {
                $orderData['soShippingContact']['value']['attention']['value'] = $salesOrder->shippingAddress->attention;
            }

            $orderData['soShippingAddress'] = [
                'value' => [
                    'overrideAddress' => ['value' => true],
                    'addressLine1' => ['value' => $salesOrder->shippingAddress->street_line_1],
                    'addressLine2' => ['value' => $salesOrder->shippingAddress->street_line_2],
                    'postalCode' => ['value' => $this->getFixedPostalCode($salesOrder->shippingAddress->postal_code, $salesOrder->shippingAddress->country_code)],
                    'city' => ['value' => $salesOrder->shippingAddress->city],
                    'countryId' => ['value' => $salesOrder->shippingAddress->country_code],
                ]
            ];
        }

        if ($salesOrder->billing_address_id && $salesOrder->billingAddress) {
            $orderData['soBillingContact'] = [
                'value' => [
                    'overrideContact' => ['value' => true],
                    'name' => ['value' => $salesOrder->billingAddress->full_name],
                    'email' => ['value' => $salesOrder->email],
                    'phone1' => ['value' => $salesOrder->phone],
                ]
            ];

            $orderData['soBillingAddress'] = [
                'value' => [
                    'overrideAddress' => ['value' => true],
                    'addressLine1' => ['value' => $salesOrder->billingAddress->street_line_1],
                    'addressLine2' => ['value' => $salesOrder->billingAddress->street_line_2],
                    'postalCode' => ['value' => $this->getFixedPostalCode($salesOrder->billingAddress->postal_code, $salesOrder->billingAddress->country_code)],
                    'city' => ['value' => $salesOrder->billingAddress->city],
                    'countryId' => ['value' => $salesOrder->billingAddress->country_code],
                ]
            ];
        }

        foreach ($salesOrder->lines as $orderLine) {
            $lineData = [
                'operation' => ($isUpdate ? 'Update' : 'Insert'),
                'lineNbr' => ['value' => $orderLine->line_number],
                'inventoryNumber' => ['value' => $orderLine->article_number],
                'warehouse' => ['value' => self::WAREHOUSE_ID],
                'quantity' => ['value' => $orderLine->quantity],
                'unitCost' => ['value' => $orderLine->unit_cost],
                'unitPrice' => ['value' => $orderLine->unit_price],
                'lineDescription' => ['value' => $orderLine->description],
                'completed' => ['value' => ((bool) $orderLine->is_completed)],
            ];

            if ($salesOrder->order_type === 'PR') {
                $lineData['reasonCode']['value'] = '20';
                $lineData['freeItem']['value'] = true;
            }

            if ($orderLine->sales_person) {
                $lineData['salesPerson']['value'] = $orderLine->sales_person;
            }

            if ($orderLine->invoice_number) {
                $lineData['invoiceNbr']['value'] = $orderLine->invoice_number;
            }

            $orderData['lines'][] = $lineData;
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

    private function getFixedPostalCode(string $postalCode, string $countryCode): string
    {
        switch ($countryCode) {
            case 'GB':
                // A space is required so we cannot remove all whitespace
                return trim($postalCode);

            case 'SE':
                // Only digits
                return preg_replace('/\D/', '', $postalCode);

            default:
                // Remove whitespace
                return preg_replace('/\s/', '', $postalCode);

        }
    }
}
