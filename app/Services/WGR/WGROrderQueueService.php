<?php

namespace App\Services\WGR;

use Illuminate\Support\Facades\Http;

class WGROrderQueueService
{
    public function getQuantityInQueue(): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $orderQueue = $this->getOrderQueue();

        $quantityInQueue = [];

        foreach ($orderQueue['queue'] as $order) {
            foreach ($order['items'] as $item) {
                if (!isset($quantityInQueue[$item['articleNumber']])) {
                    $quantityInQueue[$item['articleNumber']] = 0;
                }

                $quantityInQueue[$item['articleNumber']] += $item['quantity'];
            }
        }

        return $quantityInQueue;
    }

    public function getQuantityInQueueByDate(): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $orderQueue = $this->getOrderQueue();

        $quantityInQueue = [];

        foreach ($orderQueue['queue'] as $order) {

            $date = $order['date'] ?? '';
            $deliveryData = $order['deliveryDate'];
            $customerName = $order['fullName'];

            foreach ($order['items'] as $item) {
                if (!isset($quantityInQueue[$item['articleNumber']])) {
                    $quantityInQueue[$item['articleNumber']] = [];
                }

                $entry = $date . ' - ' . $customerName . ' - ' . $item['quantity'] . 'pcs';

                if ($date != $deliveryData) {
                    $entry .= ' - ' . $deliveryData;
                }

                $quantityInQueue[$item['articleNumber']][] = $entry;
            }
        }

        return $quantityInQueue;
    }

    public function getOrderQueue(): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        try {
            $response = Http::timeout(10)
                ->get('https://www.reseller.vendora.se/ajax/?action=order-queue');
        }
        catch (\Exception $e) {
            return ['queue' => []];
        }

        if (!$response->successful()) {
            return ['queue' => []];
        }

        return $response->json();
    }
}
