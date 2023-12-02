<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ApiResponseController;
use App\Http\Controllers\ConfigController;
use App\Http\Controllers\SalesOrderController;
use App\Models\SalesOrder;
use Illuminate\Http\Request;

class VismaNetSalesOrderService extends VismaNetApiService
{
    public function fetchSalesOrders(string $updatedAfter = '')
    {
        $fetchTime = date('Y-m-d H:i:s');

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_sales_orders_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = $updatedAfter;
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $orders = $this->getPagedResult('/v2/salesorder', $params);

        if ($orders) {

            $salesOrderController = new SalesOrderController();

            foreach ($orders as $order) {
                if (!$order || !is_array($order)) {
                    continue;
                }

                if ($order['hold'] ?? false) {
                    continue;
                }

                $orderData = [
                    'order_type' => (string) $order['orderType'],
                    'order_number' => (string) $order['orderNo'],
                    'status' => (string) $order['status'],
                    'invoice_number' => (string) ($order['invoiceNbr'] ?? ''),
                    'sales_person' => (string) ($order['salesPerson']['id'] ?? ''),
                    'date' => (string) $order['date'],
                    'customer' => (string) ($order['customer']['internalId'] ?? ''),
                    'currency' => (string) $order['currency'],
                    'order_total' => (float) $order['orderTotal'],
                    'exchange_rate' => (float) $order['exchangeRate'],
                    'note' => (string) ($order['note'] ?? ''),
                    'lines' => [],
                ];

                foreach (($order['lines'] ?? []) as $line) {
                    $orderData['lines'][] = [
                        'line_number' => $line['lineNbr'],
                        'article_number' => $line['inventory']['number'],
                        'invoice_number' => (string) ($line['invoiceNbr'] ?? ''),
                        'sales_person' => ($line['salesPerson']['id'] ?? ''),
                        'quantity' => (int) $line['quantity'],
                        'unit_cost' => (float) $line['unitCost'],
                        'unit_price' => (float) $line['unitPrice'],
                        'description' => $line['lineDescription'],
                    ];
                }

                $response = $salesOrderController->get(new Request([
                    'order_type' => (string) $order['orderType'],
                    'order_number' => $orderData['order_number'],
                ]));

                $existingSalesOrder = ApiResponseController::getDataFromResponse($response);

                if ($existingSalesOrder) {
                    // Create new sales order
                    $salesOrderController->store(new Request($orderData));
                }
                else {
                    // Update existing sales order
                    $salesOrder = SalesOrder::find($existingSalesOrder['id']);

                    $salesOrderController->update(new Request($orderData), $salesOrder);
                }
            }
        }

        ConfigController::setConfigs(['vismanet_last_sales_orders_fetch' => $fetchTime]);
    }
}
