<?php

namespace App\Http\Controllers;

use App\Models\CurrencyRate;
use Illuminate\Http\Request;

class CurrencyConvertController extends Controller
{
    private array $currencyRates = [];


    public function convertArray(array &$array, array $keys, string $fromCurrency, string $toCurrency, string $date = ''): array
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

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
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        if (!$date) {
            $date = date('Y-m-d');
        }

        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency);

        if ($fromCurrency == $toCurrency) {
            return $value;
        }

        // Start by converting into SEK it not already in SEK
        if ($fromCurrency != 'SEK') {
            $currencyRate = $this->getCurrencyRate($fromCurrency, $date);
            if (!$currencyRate) {
                return 0;
            }

            if ($currencyRate->mult_div == 'Multiply') {
                $value = $value * $currencyRate->rate;
            }
            else {
                $value = $value / $currencyRate->rate;
            }
        }

        if ($toCurrency == 'SEK') {
            return $value;
        }

        // Convert from SEK to requested currency
        $currencyRate = $this->getCurrencyRate($toCurrency, $date);
        if (!$currencyRate) {
            return 0;
        }

        if ($currencyRate->mult_div == 'Multiply') {
            $value = $value / $currencyRate->rate;
        }
        else {
            $value = $value * $currencyRate->rate;
        }

        return $value;
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
