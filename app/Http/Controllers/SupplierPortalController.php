<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SupplierPortalController extends Controller
{
    public function index()
    {
        return view('supplierPortal.pages.index');
    }
}
