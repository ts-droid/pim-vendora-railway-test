<?php

namespace App\Utilities;

use App\Http\Controllers\ConfigController;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

class PurchaseOrderHelper
{
    public static function getArticleETA(string $articleNumber)
    {
        $__utilityLogContext = [
            'utility' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked utility static method.', $__utilityLogContext);

        return DB::table('purchase_order_lines')
            ->select(
                'promised_date',
                DB::raw('SUM(quantity - quantity_received) as quantity')
            )
            ->where('article_number', '=', $articleNumber)
            ->where('is_completed', '=', 0)
            ->where('is_canceled', '=', 0)
            ->where(function($query) {
                $query->where('promised_date', '')
                    ->orWhere('promised_date', '>', date('Y-m-d', strtotime('-2 years')));
            })
            ->orderByRaw("CASE WHEN promised_date IS NULL OR promised_date = '' THEN 1 ELSE 0 END ASC")
            ->orderBy('promised_date', 'asc')
            ->groupBy('promised_date')
            ->get();
    }

    public static function getCCRecipients()
    {
        $__utilityLogContext = [
            'utility' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked utility static method.', $__utilityLogContext);

        $string = (string)ConfigController::getConfig('purchase_system_cc_emails');

        $emails = explode(',', $string);
        $emails = array_map('trim', $emails);

        return array_filter($emails, function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });
    }

    public static function getSupplierPortalUrl(Supplier $supplier)
    {
        $__utilityLogContext = [
            'utility' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked utility static method.', $__utilityLogContext);

        return 'https://api.vendora.se/supplier-portal/purchase-order?access_key=' . $supplier->access_key;
    }
}
