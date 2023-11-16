<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SalesVolumeCalculator
{
    public function calculateSalesVolume(string $startDate, string $endDate): array
    {
        // Calculate the number of days between start and end date
        $startDate = new \DateTime($startDate);
        $endDate = new \DateTime($endDate);
        $days = $endDate->diff($startDate)->days;

        // Fetch all order lines for the period
        $orderLines = DB::table('customer_invoice_lines')
            ->leftJoin('customer_invoices', 'customer_invoices.id', '=', 'customer_invoice_lines.customer_invoice_id')
            ->where('customer_invoices.date', '>=', $startDate)
            ->where('customer_invoices.date', '<=', $endDate)
            ->get();

        $totalSales = (int) $orderLines->sum('quantity');

        $averageSales = $totalSales / $days;

        return [
            'total' => $totalSales,
            'average' => $averageSales,
        ];
    }
}
