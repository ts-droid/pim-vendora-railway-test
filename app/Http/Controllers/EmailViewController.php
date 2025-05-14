<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Http\Request;

class EmailViewController extends Controller
{
    public function viewEmail(Email $email)
    {
        return view('emailViewer', compact('email'));
    }
}
