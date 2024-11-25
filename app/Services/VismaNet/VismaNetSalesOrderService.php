<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class VismaNetSalesOrderService extends VismaNetApiService
{
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
            $params['lastModifiedDateTime'] = $updatedAfter;
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
            'customer_ref_no' => (string) $order['customerRefNo'],
            'status' => (string) $order['status'],
            'invoice_number' => (string) ($order['invoiceNbr'] ?? ''),
            'sales_person' => (string) ($order['salesPerson']['id'] ?? ''),
            'date' => (string) $order['date'],
            'customer' => (string) ($order['customer']['internalId'] ?? ''),
            'currency' => (string) $order['currency'],
            'order_total' => (float) $order['orderTotal'],
            'exchange_rate' => (float) $order['exchangeRate'],
            'note' => (string) ($order['note'] ?? ''),
            'on_hold' => (($order['hold'] ?? false) ? 1 : 0),
            'lines' => [],
        ];

        foreach (($order['lines'] ?? []) as $line) {
            $orderData['lines'][] = [
                'line_number' => $line['lineNbr'],
                'article_number' => $line['inventory']['number'],
                'invoice_number' => (string) ($line['invoiceNbr'] ?? ''),
                'sales_person' => ($line['salesPerson']['id'] ?? ''),
                'quantity' => (int) $line['quantity'],
                'quantity_on_shipments' => (int) ($line['qtyOnShipments'] ?? 0),
                'quantity_open' => (int) ($line['openQty'] ?? 0),
                'unit_cost' => (float) $line['unitCost'],
                'unit_price' => (float) $line['unitPrice'],
                'unbilled_amount' => (float) $line['unbilledAmount'],
                'description' => (string) ($line['lineDescription'] ?? ''),
                'is_completed' => (int) ($line['completed'] ?? 0),
            ];
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
}
