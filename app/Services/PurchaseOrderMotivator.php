<?php

namespace App\Services;

use App\Models\PurchaseOrder;
use App\Services\AI\AIService;

class PurchaseOrderMotivator
{
    public function motivateQuantity(array $data): string
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        return 'demo content';

        $systemVariables = [
            'FORESIGHT_DAYS' => $data['foresight_days'] ?? 0,
            'SALES_LAST_7_DAYS' => $data['sales_last_7_days'] ?? 0,
            'SALES_LAST_30_DAYS' => $data['sales_last_30_days'] ?? 0,
            'SALES_LAST_90_DAYS' => $data['sales_last_90_days'] ?? 0,
            'SALES_LAST_YEAR' => $data['sales_last_year'] ?? 0,
            'WEIGHT_7_DAYS' => $data['weight_7_days'] ?? 0,
            'WEIGHT_30_DAYS' => $data['weight_30_days'] ?? 0,
            'WEIGHT_90_DAYS' => $data['weight_90_days'] ?? 0,
            'WEIGHT_YEAR' => $data['weight_year'] ?? 0,
            'CURRENT_STOCK' => $data['current_stock'] ?? 0,
            'VIP_QUANTITY' => $data['vip_quantity'] ?? 0,
            'USE_MASTER_BOX' => $data['use_master_box'] ? 'true' : 'false',
            'MASTER_BOX_QUANTITY' => $data['master_box'] ?? 0,
            'IS_NEW_ARTICLE' => $data['is_new_article'] ? 'true' : 'false',
        ];

        // Construct the system
        $system = '';
        foreach($systemVariables as $key => $value) {
            $system .= $key . ':' . PHP_EOL . $value . PHP_EOL . PHP_EOL;
        }

        // Construct the message
        $message = 'All values is for one specific product.

        USE_MASTER_BOX indicates if the product must be ordered in master boxes.
        MASTER_BOX_QUANTITY indicates the quantity of the product in a master box.

        FORESIGHT_DAYS indicates the number of days to look ahead.

        SALES_LAST_7_DAYS indicates the average quantity sold per day in the last 7 days.
        SALES_LAST_30_DAYS indicates the average quantity sold per day in the last 30 days.
        SALES_LAST_90_DAYS indicates the average quantity sold per day in the last 90 days.
        SALES_LAST_YEAR indicates the average quantity sold per day in the last 30 days one year ago.

        VIP_QUANTITY indicates the quantity of the product that is current on a open order that is not yet shipped to a VIP customer.

        We use the following formula to calculate the suggested quantity to order from the supplier.

        QUANTITY_TO_ORDER = SALES_LAST_7_DAYS * WEIGHT_7_DAYS * FORESIGHT_DAYS
        QUANTITY_TO_ORDER += SALES_LAST_30_DAYS * WEIGHT_30_DAYS * FORESIGHT_DAYS
        QUANTITY_TO_ORDER += SALES_LAST_90_DAYS * WEIGHT_90_DAYS * FORESIGHT_DAYS
        QUANTITY_TO_ORDER += SALES_LAST_YEAR * WEIGHT_YEAR * FORESIGHT_DAYS
        QUANTITY_TO_ORDER += VIP_QUANTITY
        QUANTITY_TO_ORDER -= CURRENT_STOCK

        If USE_MASTER_BOX is true, then QUANTITY_TO_ORDER is rounded to the nearest multiple of MASTER_BOX_QUANTITY.

        If IS_NEW_ARTICLE is true, then always order 1 master box, and if MASTER_BOX_QUANTITY is 0, then order 1 unit.

        Can you write a very short motivation/description for the suggested quantity to order? Maximum 100 characters.
        This will be shown to the purchase order manager. It should be very short and concise.';

        $AIService = new AIService();
        $motivation = $AIService->chatCompletion($system, $message);

        return $motivation;
    }
}
