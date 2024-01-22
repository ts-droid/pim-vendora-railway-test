<?php

namespace App\Services\Reports;

class SalesDashboardReporter
{
    public function getSummary(): array
    {
        $summary = [
            'turnover' => [
                'month' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'year' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ],
            'margin' => [
                'month' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'year' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ],
            'profit' => [
                'month' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'year' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ],
        ];

        return $summary;
    }

    public function getTopBrands(): array
    {
        $topBrands = [
            'brands' => [],
            'summary' => [
                'units' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'revenue' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ],
        ];

        for ($i = 0;$i < 10;$i++) {
            $topBrands['brands'][] = [
                'name' => 'Satechi',
                'units' => [
                    'amount' => 0,
                    'change' => 0,
                ],
                'revenue' => [
                    'amount' => 0,
                    'change' => 0,
                ],
            ];
        }

        return $topBrands;
    }

    public function getTopCustomers(): array
    {
        $topCustomers = [];

        for ($i = 0;$i < 10;$i++) {
            $topCustomers[] = [
                'name' => 'Elkjöp Nordic AS',
                'country' => 'NO',
                'amount' => 0,
                'change' => 0,
            ];
        }

        return $topCustomers;
    }

    public function getOrderPipeline(): array
    {
        $orderPipeline = [];

        for ($i = 0;$i < 3;$i++) {
            $orderPipeline[] = [
                'customer' => 'Play Distrubution',
                'value' => 0,
                'shipping_date' => date('Y-m-d'),
            ];
        }

        return $orderPipeline;
    }
}
