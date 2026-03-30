<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class VismaNetApiController extends Controller
{
    public function getShipment(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $shipmentNumber = $request->get('shipment_number');

        if (!$shipmentNumber) {
            return ApiResponseController::error('Missing parameter "shipment_number".');
        }

        $vismaNetController = new VismaNetController();
        $shipment = $vismaNetController->getShipment($shipmentNumber);

        return ApiResponseController::success($shipment);
    }

    public function getCustomer(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $customerNumber = $request->get('customer_number');

        if (!$customerNumber) {
            return ApiResponseController::error('Missing parameter "customer_number".');
        }

        $vismaNetController = new VismaNetController();
        $customer = $vismaNetController->getCustomer($customerNumber);

        return ApiResponseController::success($customer);
    }

    public function getInventoryItem(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $articleNumber = $request->get('article_number');

        if (!$articleNumber) {
            return ApiResponseController::error('Missing parameter "article_number".');
        }

        $vismaNetController = new VismaNetController();
        $article = $vismaNetController->getInventoryItem($articleNumber);

        return ApiResponseController::success($article);
    }

    public function getSalesOrder(Request $request)
    {
        if ($this->shouldLogControllerMethod()) {
            $__controllerLogContext = $this->controllerLogContext(__FUNCTION__, func_get_args());
            action_log('Invoked controller method.', $__controllerLogContext);
        }

        $orderType = $request->get('order_type');
        $orderNumber = $request->get('order_number');

        if (!$orderType) {
            return ApiResponseController::error('Missing parameter "order_type".');
        }
        if (!$orderNumber) {
            return ApiResponseController::error('Missing parameter "$orderNumber".');
        }

        $vismaNetController = new VismaNetController();
        $salesOrder = $vismaNetController->getSalesOrder($orderType, $orderNumber);

        return ApiResponseController::success($salesOrder);
    }
}
