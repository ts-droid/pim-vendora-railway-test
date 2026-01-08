<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use DateTime;

class EcbService
{
    public function convertCurrency(float $number, string $fromCurrency, string $toCurrency, string $date = ''): float
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        if (!$date) {
            $date = date('Y-m-d');
        }

        $cacheKey = 'currency_rates_'. $date;
        $rates = Cache::remember($cacheKey, now()->addHours(10), function() use ($date) {
           return $this->getRatesForDate($date);
        });

        if ($fromCurrency != 'EUR' && !isset($rates[$fromCurrency])) {
            throw new \Exception('Unsupported currency rate ' . $fromCurrency);
        }
        if ($toCurrency != 'EUR' && !isset($rates[$toCurrency])) {
            throw new \Exception('Unsupported currency rate ' . $fromCurrency);
        }

        // Convert to EUR
        if ($fromCurrency != 'EUR') {
            $number = $number / $rates[$fromCurrency];
        }

        // Convert to target currency
        if ($toCurrency != 'EUR') {
            $number = $number * $rates[$toCurrency];
        }

        return $number;
    }

    private function getRatesForDate(string $date): array
    {
        if ($date == date('Y-m-d')) {
            $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml';
        }
        elseif ($date >= date('Y-m-d', strtotime('-80 days'))) { // The file includes 90 days, but add some margin that some days are missing
            $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist-90d.xml';
        }
        else {
            $url = 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-hist.xml';
        }

        $xml = simplexml_load_file($url);

        $searchDateObj = new DateTime($date);
        $closestDate = $this->findClosestDate($xml, $searchDateObj);
        if (!$closestDate) {
            throw new \Exception('No rates found for the date ' . $date);
        }

        $rates = [];
        foreach ($xml->Cube->Cube as $dailyCube) {
            if ((string) $dailyCube['time'] === $closestDate) {
                foreach ($dailyCube->Cube as $currencyRate) {
                    $currency = (string) $currencyRate['currency'];
                    $rate = (float) $currencyRate['rate'];
                    $rates[$currency] = $rate;
                }
                break;
            }
        }

        return $rates;
    }

    private function findClosestDate($xml, DateTime $searchDateObj)
    {
        foreach ($xml->Cube->Cube as $dailyCube) {
            $currentDate = new DateTime((string) $dailyCube['time']);

            if ($currentDate <= $searchDateObj) {
                return $currentDate->format('Y-m-d');
            }
        }

        return null;
    }
}
