<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\Api\SalesOrderApiController;
use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Services\NotificationService;
use App\Services\SalesOrderService;
use DateTime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VismaNetSalesOrderService extends VismaNetApiService
{
    public function createShipment(SalesOrder $salesOrder, bool $isDirectDelivery = false)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $salesOrderService = new SalesOrderService();

        $postData = [
            'orderType' => $salesOrder->order_type,
            'shipmentDate' => (new DateTime())->format('Y-m-d H:i:s'),
            'shipmentWarehouse' => ($isDirectDelivery ? self::WAREHOUSE_DIRECT_ID : self::WAREHOUSE_ID)
        ];

        $response = $this->callAPI('POST', '/v2/salesorder/' . $salesOrder->order_number . '/action/createShipment', $postData);

        if (!$response['success']) {
            $salesOrder->update(['has_sync_error' => 1]);

            $errorMessage = 'Failed to create shipment in Visma.net for sales order #' . $salesOrder->id;

            $salesOrderService->createLog($salesOrder->id, $errorMessage);
            NotificationService::sendMail('Failed to create shipment in Visma.net', $errorMessage);
            throw new \Exception($errorMessage . ' ' . json_encode($response));
        }

        $salesOrder->update([
            'status_shipment_created' => 1,
            'has_sync_error' => 0
        ]);

        $salesOrderService->createLog($salesOrder->id, 'Created shipment in Visma.net for this order.');
    }

    public function resetSalesOrder(SalesOrder $salesOrder): bool
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $cancelResponse = $this->cancelSalesOrder($salesOrder);
        $cancelSuccess = $cancelResponse['success'];

        $salesOrderService = new SalesOrderService();

        if (!$cancelSuccess) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to reset order sync state to Visma.net. (' . $cancelResponse['message'] . ')');
            return false;
        }

        $salesOrderService->createLog($salesOrder->id, 'Reset order sync state to Visma.net.');
        return true;
    }

    public function sendSalesOrder(SalesOrder $salesOrder): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Check if order already exists in Visma.net
        $response = $this->callAPI('GET', '/v3/SalesOrders/' . urlencode($salesOrder->order_type) . '/' . urlencode($salesOrder->order_number));
        if (!empty($response['response']['orderId'])) {
            $this->updateSalesOrder($salesOrder);
            return;
        }

        $salesOrderService = new SalesOrderService();

        // Fetch or create customer in Visma.net
        $customerNumber = $this->getCustomerNumber($salesOrder);
        if (!$customerNumber) {
            // Create the customer
            $customer = $this->createCustomer($salesOrder);

            if ($customer) {
                $customerNumber = $customer['number'];
                $salesOrder->update(['customer' => $customer['number']]);
            }
        }

        if (!$customerNumber) {
            $salesOrder->update(['has_sync_error' => 1]);

            $errorMessage = 'Failed to send sales order #' . $salesOrder->id . ' to Visma.net. Customer ' . $customerNumber . ' not found.';

            $salesOrderService->createLog($salesOrder->id, $errorMessage);
            NotificationService::sendMail('Failed to send order to Visma.net', $errorMessage);
            throw new \Exception($errorMessage);
        }

        $orderData = $this->getOrderData($salesOrder, $customerNumber);

        $response = $this->callAPI('POST', '/v3/SalesOrders', $orderData);

        if ($response['http_code'] !== 201) {
            $salesOrder->update(['has_sync_error' => 1]);

            $errorMessage = 'Failed to send sales order #' . $salesOrder->id . ' to Visma.net. Error: ' . ($response['response']['message'] ?? ('http-' . $response['http_code']));

            $salesOrderService->createLog($salesOrder->id, $errorMessage);
            NotificationService::sendMail('Failed to send order to Visma.net', $errorMessage);
            throw new \Exception($errorMessage . ' Response: ' . json_encode($response));
        }

        // Update the order line numbers in the database
        $linesResponse = $this->callAPI('GET', '/v3/SalesOrders/' . urlencode($salesOrder->order_type) . '/' . urlencode($salesOrder->order_number));
        $remoteLines = $linesResponse['response']['value'] ?? null;

        if (!$remoteLines) {
            $salesOrder->update(['has_sync_error' => 1]);

            $errorMessage = 'Failed to send sales order #' . $salesOrder->id . ' to Visma.net. Failed to updated local order lines after creation.';

            $salesOrderService->createLog($salesOrder->id, $errorMessage);
            NotificationService::sendMail('Failed to send order to Visma.net', $errorMessage);
            throw new \Exception($errorMessage);
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

        $salesOrder->update([
            'status_sent_external' => 1,
            'has_sync_error' => 0
        ]);

        $salesOrderService->createLog($salesOrder->id, 'This order was sent to Visma.net');
    }

    public function updateSalesOrder(SalesOrder $salesOrder): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        // Check if the order exists in Visma.net
        $response = $this->callAPI('GET', '/v3/SalesOrders/' . urlencode($salesOrder->order_type) . '/' . urlencode($salesOrder->order_number));
        if (empty($response['response']['orderId'])) {
            $this->sendSalesOrder($salesOrder);
            return;
        }

        $salesOrderService = new SalesOrderService();

        // Fetch or create customer in Visma.net
        $customerNumber = $this->getCustomerNumber($salesOrder);
        if (!$customerNumber) {
            // Create the customer
            $customer = $this->createCustomer($salesOrder);

            if ($customer) {
                $customerNumber = $customer['number'];
                $salesOrder->update(['customer' => $customer['number']]);
            }
        }

        if (!$customerNumber) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update this order in Visma.net. Customer ' . $customerNumber . ' not found.');
            throw new \Exception('Failed to update order in Visma.net. Customer ' . $customerNumber . ' not found.');
        }

        $orderData = $this->getOrderData($salesOrder, $customerNumber, true);

        // First update the status (this must be done separately in the v3 endpoint)
        $statusResponse = $this->callAPI('PATCH', '/v3/SalesOrders/' . urlencode($salesOrder->order_type) . '/' . urlencode($salesOrder->order_number), [
            'status' => $orderData['status']
        ]);

        if ($statusResponse['http_code'] !== 202) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update order status in Visma.net. API request failed to update order.');
            throw new \Exception('Failed to update order status in Visma.net. API request failed to update order..');
        }


        unset($orderData['status']);

        $orderResponse = $this->callAPI('PATCH', '/v3/SalesOrders/' . urlencode($salesOrder->order_type) . '/' . urlencode($salesOrder->order_number), $orderData);
        if ($orderResponse['http_code'] !== 202) {
            $salesOrderService->createLog($salesOrder->id, 'Failed to update this order in Visma.net. API request failed to update order.');
            throw new \Exception('Failed to update order in Visma.net. API request failed to update order.');
        }

        $salesOrderService->createLog($salesOrder->id, 'This order was updated in Visma.net');
    }

    public function cancelSalesOrder(SalesOrder $salesOrder): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $fetchResponse = $this->callAPI('GET', '/v3/SalesOrders/' . $salesOrder->order_type . '/' . $salesOrder->order_number);
        if (empty($fetchResponse['response']['orderId'])) {
            return [
                'success' => false,
                'message' => 'Order not found in Visma.net.'
            ];
        }

        // Delete the sales order
        $headers = ['If-Match' => $fetchResponse['response']['version']];
        $deleteResponse = $this->callAPI('DELETE', '/v3/SalesOrders/' . $salesOrder->order_type . '/' . $salesOrder->order_number, [], '', false, false, $headers);

        if (!($deleteResponse['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Failed to delete sales order in Visma.net ' . json_encode($deleteResponse)
            ];
        }

        return [
            'success' => true,
            'message' => 'Order canceled successfully.'
        ];
    }

    public function fetchSalesOrder(string $orderNumber): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

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

    public function deleteSalesOrders(): void
    {
        return;

        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $orders = $this->getPagedResultsV3('/v3/SalesOrders', ['pageSize' => 1000]);

        if (count($orders) === 0) return;

        $orderNumbers = array_column($orders, 'orderId');
        unset($orders);

        $localOrderNumbers = DB::table('sales_orders')->where('date', '>', '2023-01-01')->pluck('order_number')->toArray();

        $deletedOrderNumber = array_diff($localOrderNumbers, $orderNumbers);

        if (count($deletedOrderNumber) === 0) return;

        foreach ($deletedOrderNumber as $orderNumber) {
            $salesOrder = SalesOrder::where('order_number', $orderNumber)->first();
            if (!$salesOrder) continue;

            SalesOrderLine::where('sales_order_id', $salesOrder->id)->delete();
            $salesOrder->delete();
        }
    }

    public function fetchSalesOrders(string $updatedAfter = ''): void
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_sales_orders_fetch');

        if ($updatedAfter) {
            $params['modifiedSince'] = date('Y-m-d H:i:s', strtotime('-1 minutes', strtotime($updatedAfter)));
            $params['orderBy'] = 'lastModified asc';
        }

        $orders = $this->getPagedResult('/v3/SalesOrders', $params, 'value');

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
        if (empty($order['type']) || empty($order['orderId'])) {
            return [
                'success' => false,
                'message' => 'Missing order type or order number.'
            ];
        }

        $invoiceNumber =

        $orderData = [
            'order_type' => (string) $order['type'],
            'sales_person' => (string) ($order['salesPerson']['id'] ?? ''),
            'customer_number' => (string) ($order['customerId'] ?? ''),
            'currency' => (string) ($order['currency'] ?? ''),
            'note' => (string) ($order['note'] ?? ''),
            'order_number' => (string) $order['orderId'],
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
            $warehouseID = $line['warehouse']['id'] ?? self::WAREHOUSE_ID;

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
                'active_unit_price' => (float) ($line['unitPrice'] ?? 0),
                'unbilled_amount' => (float) ($line['unbilledAmount'] ?? 0),
                'description' => (string) ($line['lineDescription'] ?? ''),
                'is_completed' => (int) ($line['completed'] ?? 0),
                'vat_rate' => (float) $vatRate,
                'is_direct' => ($warehouseID == self::WAREHOUSE_DIRECT_ID ? 1 : 0),
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

    public function getOrderData(SalesOrder $salesOrder, string $customerNumber, bool $isUpdate = false): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $remoteLineIds = [];

        if ($isUpdate) {
            $remoteLines = $this->callAPI('GET', '/v3/SalesOrders/' . urlencode($salesOrder->order_type) . '/' . ($salesOrder->order_number) . '/lines');
            $remoteLines = $remoteLines['value'] ?? [];

            foreach ($remoteLines as $remoteLine) {
                $remoteLineIds[] = $remoteLine['lineId'];
            }
        }

        $orderData = [
            'type' => $salesOrder->order_type,
            'orderId' => $salesOrder->order_number,
            'currency' => $salesOrder->currency,
            'status' => $salesOrder->status,
            'customer' => [
                'id' => $customerNumber,
            ],
            'note' => '',
            'orderLines' => [],
            'newOrderLines' => [],
            'removeLines' => []
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
            $orderData['note'] .= $salesOrder->note;
        }
        if ($salesOrder->internal_note) {
            $orderData['note'] .= PHP_EOL . PHP_EOL . $salesOrder->internal_note;
        }
        if ($salesOrder->store_note) {
            $orderData['note'] .= PHP_EOL . PHP_EOL . $salesOrder->store_note;
        }

        $orderData['note'] = trim($orderData['note']);

        if (!$orderData['note']) {
            unset($orderData['note']);
        }

        if ($salesOrder->shipping_address_id && $salesOrder->shippingAddress) {
            $orderData['shipping'] = [
                'address' => [
                    'line1' => $salesOrder->shippingAddress->street_line_1,
                    'line2' => $salesOrder->shippingAddress->street_line_2,
                    'postalCode' => get_fixed_postal_code($salesOrder->shippingAddress->postal_code, $salesOrder->shippingAddress->country_code),
                    'city' => $salesOrder->shippingAddress->city,
                    'countryId' => $salesOrder->shippingAddress->country_code,
                    'overridesDefault' => true,
                ],
                'contact' => [
                    'name' => $salesOrder->shippingAddress->full_name,
                    'phone1' => $salesOrder->phone,
                    'email' => $salesOrder->email,
                    'overridesDefault' => true,
                ],
            ];

            if ($salesOrder->shippingAddress->attention) {
                $orderData['shipping']['contact']['attention'] = $salesOrder->shippingAddress->attention;
            }
        }

        if ($salesOrder->billing_address_id && $salesOrder->billingAddress) {
            $orderData['billing'] = [
                'address' => [
                    'line1' => $salesOrder->billingAddress->street_line_1,
                    'line2' => $salesOrder->billingAddress->street_line_2,
                    'postalCode' => get_fixed_postal_code($salesOrder->billingAddress->postal_code, $salesOrder->billingAddress->country_code),
                    'city' => $salesOrder->billingAddress->city,
                    'countryId' => $salesOrder->billingAddress->country_code,
                    'overridesDefault' => true,
                ],
                'contact' => [
                    'name' => $salesOrder->billingAddress->full_name,
                    'phone1' => $salesOrder->phone,
                    'email' => $salesOrder->email,
                    'overridesDefault' => true,
                ],
            ];

            if ($salesOrder->billingAddress->attention) {
                $orderData['billing']['contact']['attention'] = $salesOrder->billingAddress->attention;
            }
        }

        $updatedLineIds = [];

        foreach ($salesOrder->lines as $orderLine) {
            $lineData = [
                'lineId' => $orderLine->line_number,
                'inventoryId' => $orderLine->article_number,
                'warehouseId' => self::WAREHOUSE_ID,
                'quantity' => $orderLine->quantity,
                'unitCost' => $orderLine->unit_cost,
                'unitPrice' => $orderLine->unit_price,
                'description' => $orderLine->description,
                'completed' => (bool) $orderLine->is_completed
            ];

            if ($salesOrder->order_type === 'PR') {
                $lineData['reasonCode'] = '20';
                $lineData['freeItem'] = true;
            }

            if ($orderLine->sales_person) {
                $lineData['salesPersonId'] = $orderLine->sales_person;
            }

            if ($orderLine->invoice_number) {
                $lineData['invoiceNumber'] = $orderLine->invoice_number;
            }


            if (in_array($lineData['lineId'], $remoteLineIds)) {
                $orderData['orderLines'][] = $lineData;
            } else {
                $orderData['newOrderLines'][] = $lineData;
            }

            $updatedLineIds[] = $lineData['lineId'];
        }

        foreach ($remoteLineIds as $lineId) {
            if (in_array($lineId, $updatedLineIds)) {
                continue;
            }

            $updatedLineIds['removeLines'][] = $lineId;
        }

        if (empty($orderData['orderLines'])) {
            unset($orderData['orderLines']);
        }

        if (empty($orderData['newOrderLines'])) {
            unset($orderData['newOrderLines']);
        }

        if (empty($orderData['removeLines'])) {
            unset($orderData['removeLines']);
        }

        return $orderData;
    }

    public function createCustomer(SalesOrder $salesOrder): ?array
    {
        $isCustomerEU = is_eu_country($salesOrder->billingAddress->country_code ?? '');

        if ($salesOrder->billingAddress->country_code == 'SE') {
            $customerClassId = '10';            // Svenska kunder
            $vatZoneId = '01';                  // Inhemsk
        }
        elseif ($isCustomerEU) {
            $customerClassId = '20';            // Kunder EU
            $vatZoneId = '01';                  // Inhemsk
        } else {
            $customerClassId = '30';            // Kunder utanför EU
            $vatZoneId = '03';                  // Export/import
        }

        $payload = [
            'name' => ['value' => $salesOrder->billingAddress->full_name],
            'status' => ['value' => 'Active'],
            'currencyId' => ['value' => $salesOrder->currency],
            'customerClassId' => ['value' => $customerClassId],
            'vatRegistrationId' => ['value' => $salesOrder->vat_number],
            'acceptAutoInvoices' => ['value' => false],
            // 'vatZoneId' => ['value' => $vatZoneId],
            'mainContact' => ['value' => [
                'name' => ['value' => $salesOrder->billingAddress->full_name],
                'attention' => ['value' => $salesOrder->billingAddress->attention],
                'email' => ['value' => $salesOrder->email],
                'phone1' => ['value' => $salesOrder->phone],
            ]],
            'mainAddress' => ['value' => [
                'addressLine1' => ['value' => $salesOrder->billingAddress->street_line_1],
                'addressLine2' => ['value' => $salesOrder->billingAddress->street_line_2],
                'postalCode' => ['value' => get_fixed_postal_code($salesOrder->billingAddress->postal_code, $salesOrder->billingAddress->country_code)],
                'city' => ['value' => $salesOrder->billingAddress->city],
                'countryId' => ['value' => $salesOrder->billingAddress->country_code],
            ]],
            'invoiceAddress' => ['value' => [
                'addressLine1' => ['value' => $salesOrder->billingAddress->street_line_1],
                'addressLine2' => ['value' => $salesOrder->billingAddress->street_line_2],
                'postalCode' => ['value' => get_fixed_postal_code($salesOrder->billingAddress->postal_code, $salesOrder->billingAddress->country_code)],
                'city' => ['value' => $salesOrder->billingAddress->city],
                'countryId' => ['value' => $salesOrder->billingAddress->country_code],
            ]],
            'deliveryAddress' => ['value' => [
                'addressLine1' => ['value' => $salesOrder->shippingAddress->street_line_1],
                'addressLine2' => ['value' => $salesOrder->shippingAddress->street_line_2],
                'postalCode' => ['value' => get_fixed_postal_code($salesOrder->shippingAddress->postal_code, $salesOrder->shippingAddress->country_code)],
                'city' => ['value' => $salesOrder->shippingAddress->city],
                'countryId' => ['value' => $salesOrder->shippingAddress->country_code],
            ]],
        ];

        $response = $this->callAPI('POST', '/v1/customer', $payload);
        if (!$response['success']) {
            return null;
        }

        $location = $response['headers']['Location'][0] ?? null;

        $response = $this->callAPI('GET', $location);
        if (!$response['success']) {
            return null;
        }

        return ($response['response'] ?? null);
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

    public function getCustomerNumber(SalesOrder $salesOrder)
    {
        if ($salesOrder->customer) {
            return $salesOrder->customer;
        }

        if (is_eu_country($salesOrder->billingAddress->country_code ?? '')) {
            // EU Customer
            if ($salesOrder->vat_number) {
                // VAT-number provided, this must be a company, so use its own customer
                // Check if a customer already exists, else return null so we can create it
                return null;
                // $response = $this->callAPI('GET', '/v1/customer?vatRegistrationId=' . $salesOrder->vat_number);
                // return ($response['response'][0]['number'] ?? null);
            } else {
                // No VAT-number so this is a private person, use retail customer
                return self::RETAIL_CUSTOMER_NUMBER_EU;
            }
        } else {
            // Export customer
            return self::RETAIL_CUSTOMER_NUMBER_NON_EU;
        }
    }
}
