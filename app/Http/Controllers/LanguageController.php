<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LanguageController extends Controller
{
    const SUPPORTED_LANGUAGES = ['sv', 'en', 'da'];

    public function localeToTitle(string $locale): string
    {
        switch ($locale) {
            case 'sv':
                return 'Swedish';
            case 'en':
                return 'English';
            case 'da':
                return 'Danish';
            default:
                return '';
        }
    }
}
