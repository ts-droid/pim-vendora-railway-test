<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CurrencyController extends Controller
{
    const SUPPORTED_CURRENCIES = ['SEK', 'EUR', 'DKK', 'NOK'];
}
