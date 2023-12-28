<?php

namespace App\Services\VismaNet;

use Illuminate\Support\Facades\DB;

class VismaNetQueueService extends VismaNetApiService
{
    const TYPE_ORDER = 'sales_order';

    public function queue(string $type, string $orderNumber, string $externalOrderNumber, string $method, string $endpoint, string $body, string $processAt)
    {
        DB::table('vismanet_queue')->insert([
            'type' => $type,
            'order_number' => $orderNumber,
            'external_order_number' => $externalOrderNumber,
            'method' => $method,
            'endpoint' => $endpoint,
            'body' => $body,
            'process_at' => $processAt,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    public function processQueue()
    {
        $rows = DB::table('vismanet_queue')->where('process_at', '<=', now())
            ->orderBy('process_at', 'asc')
            ->limit(10)
            ->get();

        if (!$rows) {
            return;
        }

        foreach ($rows as $row) {
            $this->processRow($row);
        }
    }

    private function processRow($row)
    {
        $this->callAPI($row->method, $row->endpoint, json_decode($row->body, true));
    }
}
