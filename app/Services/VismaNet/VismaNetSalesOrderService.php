<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use App\Services\SalesOrderService;
use DateTime;
use Illuminate\Http\Request;

class VismaNetSalesOrderService extends VismaNetApiService
{
    const WAREHOUSE_ID = 1; // 1 = Huvudlager

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
        $customerNumber = $salesOrder->customer ?: 10460; // 10460 = Retail customer in Visma.net

        $response = $this->callAPI('GET', '/v1/customer/' . $customerNumber);
        $customer = $response['response'] ?? null;

        if (!$customer) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net. Customer ' . $salesOrder->customer . ' not found.');
            throw new \Exception('Failed to send order to Visma.net. Customer ' . $salesOrder->customer . ' not found.');
        }

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

            $orderData['orderLines'][] = $lineData;
        }

        $response = $this->callAPI('POST', '/v3/salesorders/', $orderData);

        if ($response['http_code'] !== 201) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to send this order to Visma.net (' . ($response['response']['details']['message'] ?? 'unknown-error') . ')');

            throw new \Exception('Failed to send order to Visma.net. Error message: ' . ($response['response']['details']['message'] ?? 'unknown-error') . ' (Code: ' . $response['http_code'] . ')');
        }

        $salesOrderService->createLog($salesOrder->id, 'This order was sent to Visma.net');
    }

    public function updateSalesOrder(SalesOrder $salesOrder): void
    {
        // TODO: Implement update sales order method
    }

    public function fetchSalesOrder(string $orderNumber): void
    {
        $response = $this->callAPI('GET', '/v2/salesorder/' . $orderNumber);
        $order = $response['response'];

        if (!$order || !is_array($order)) {
            return;
        }

        $this->importOrder($order);
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

        $salesOrderController = new SalesOrderController();

        $orderData = [
            'order_type' => (string) $order['orderType'],
            'order_number' => (string) $order['orderNo'],
            'customer_ref_no' => (string) ($order['customerRefNo'] ?? ''),
            'status' => (string) ($order['status'] ?? ''),
            'invoice_number' => (string) ($order['invoiceNbr'] ?? ''),
            'sales_person' => (string) ($order['salesPerson']['id'] ?? ''),
            'date' => (string) ($order['date'] ?? ''),
            'customer' => (string) ($order['customer']['internalId'] ?? ''),
            'currency' => (string) ($order['currency'] ?? ''),
            'order_total' => (float) ($order['orderTotal'] ?? 0),
            'exchange_rate' => (float) ($order['exchangeRate'] ?? 0),
            'note' => (string) ($order['note'] ?? ''),
            'on_hold' => (($order['hold'] ?? false) ? 1 : 0),
            'lines' => [],
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

        $response = $salesOrderController->get(new Request([
            'order_type' => $orderData['order_type'],
            'order_number' => $orderData['order_number'],
        ]));

        $existingSalesOrder = ApiResponseController::getDataFromResponse($response);

        if (!$existingSalesOrder) {
            // Create new sales order
            $salesOrderController->store(new Request($orderData));
        }
        else {
            // Update existing sales order
            $salesOrder = SalesOrder::find($existingSalesOrder[0]['id']);

            // Force update of order lines, will remove order lines that are not present int the request
            $orderData['force_order_lines'] = 1;

            $salesOrderController->update(new Request($orderData), $salesOrder);
        }
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
}
