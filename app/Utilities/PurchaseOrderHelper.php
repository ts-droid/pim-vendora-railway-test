<?php

namespace App\Utilities;

use App\Http\Controllers\ConfigController;

class PurchaseOrderHelper
{
    public static function getCCRecipients()
    {
        $string = (string) ConfigController::getConfig('purchase_system_cc_emails');

        $emails = explode(',', $string);
        $emails = array_map('trim', $emails);

        return array_filter($emails, function($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
    }
}
