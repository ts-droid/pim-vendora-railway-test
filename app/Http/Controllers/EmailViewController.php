<?php

namespace App\Http\Controllers;

use App\Enums\LaravelQueues;
use App\Mail\ResendEmail;
use App\Models\Email;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class EmailViewController extends Controller
{
    public function viewEmail(Email $email)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        return view('emailViewer', compact('email'));
    }

    public function sendEmail(Email $email)
    {
        if ($this->shouldLogControllerMethod()) {

            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());

            action_log('Invoked controller method.', $__controllerLogContext);

        }

        $to = explode(',', $email->to);
        $to = array_filter($to);

        $cc = explode(',', $email->cc);
        $cc = array_filter($cc);

        $bcc = explode(',', $email->bcc);
        $bcc = array_filter($bcc);

        Mail::to($to)
            ->cc($cc)
            ->bcc($bcc)
            ->queue((new ResendEmail(
                $email->subject,
                $email->body,
                $email->attachments ?: []
            ))->onQueue(LaravelQueues::MAIL->value));

        echo('Email queued successfully!');
        die();
    }
}
