<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;

class CurrencyConvertController extends Controller
{
    private array $currencyRates = [];


    public function convertArray(array &$array, array $keys, string $fromCurrency, string $toCurrency, string $date = ''): array
    {
        foreach ($array as $key => $value) {
            if (!in_array($key, $keys)) {
                continue;
            }

            $array[$key] = $this->convert(floatval($value), $fromCurrency, $toCurrency, $date);
        }

        return $array;
    }

    public function convert(float $value, string $fromCurrency, string $toCurrency, string $date = ''): float
    {
        if (!$date) {
            $date = date('Y-m-d');
        }

        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency == $toCurrency) {
            return $value;
        }

        $currencyRate = $this->getCurrencyRate($toCurrency, $date);

        if (!$currencyRate) {
            return 0;
        }

        if ($currencyRate->mult_div == 'Multiply') {
            $newValue = $value / $currencyRate->rate;
        }
        else {
            $newValue = $value * $currencyRate->rate;
        }

        return $newValue;
    }

    private function getCurrencyRate(string $currency, string $date)
    {
        $cacheKey = $currency . $date;

        if (isset($this->currencyRates[$cacheKey])) {
            return $this->currencyRates[$cacheKey];
        }

        $currencyRate = CurrencyRate::where('from_currency', $currency)
            ->where('date', '<=', $date)
            ->orderBy('date', 'DESC')
            ->first();

        $this->currencyRates[$cacheKey] = $currencyRate;

        return $currencyRate;
    }
}
