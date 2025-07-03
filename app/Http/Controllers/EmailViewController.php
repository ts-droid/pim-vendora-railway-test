<?php

namespace App\Http\Controllers;

use App\Mail\ResendEmail;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailViewController extends Controller
{
    public function viewEmail(Email $email)
    {
        return view('emailViewer', compact('email'));
    }

    public function sendEmail(Email $email)
    {
        $to = explode(',', $email->to);
        $to = array_filter($to);

        $cc = explode(',', $email->cc);
        $cc = array_filter($cc);

        $bcc = explode(',', $email->bcc);
        $bcc = array_filter($bcc);

        $to = ['anton@scriptsector.se'];
        $cc = [];
        $bcc = [];

        Mail::to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->queue(new ResendEmail(
                $email->subject,
                $email->body,
                $email->attachments ?: []
            ));

        echo('Email sent successfully!');
        die();
    }
}
