<?php

namespace App\Services\WGR;

use Illuminate\Support\Facades\Http;

class WGROrderQueueService
{
    public function getQuantityInQueue(): array
    {
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
        $orderQueue = $this->getOrderQueue();

        $quantityInQueue = [];

        foreach ($orderQueue['queue'] as $order) {
            foreach ($order['items'] as $item) {
                if (!isset($quantityInQueue[$item['articleNumber']])) {
                    $quantityInQueue[$item['articleNumber']] = [];
                }

                if (!isset($quantityInQueue[$item['articleNumber']][$order['deliveryDate']])) {
                    $quantityInQueue[$item['articleNumber']][$order['deliveryDate']] = 0;
                }

                $quantityInQueue[$item['articleNumber']][$order['deliveryDate']] += $item['quantity'];
            }
        }

        return $quantityInQueue;
    }

    public function getOrderQueue(): array
    {
        try {
            $response = Http::timeout(10)
                ->get('https://www.reseller.vendora.se/ajax/?action=order-queue');
        }
        catch (\Exception $e) {
            return [];
        }

        return $response->json();
    }
}
