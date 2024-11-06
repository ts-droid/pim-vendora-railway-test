<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Models\Shipment;
use App\Services\AddressService;
use App\Services\ShipmentService;

class VismaNetShipmentService extends VismaNetApiService
{
    public function fetchShipments(string $updatedAfter = ''): void
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_shipment_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = $updatedAfter;
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $shipments = $this->getPagedResult('/v1/shipment', $params);

        if ($shipments) {
            foreach ($shipments as $shipment) {
                $fetchedData = true;

                if (!$shipment || !is_array($shipment)) {
                    continue;
                }

                $this->importShipment($shipment);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_shipment_fetch' => $fetchTime]);
        }
    }

    private function importShipment(array $shipment): void
    {
        $addressService = new AddressService();
        $deliveryAddress = $addressService->createAddress([
            'street_line_1' => (string) ($shipment['deliveryAddress']['addressLine1'] ?? ''),
            'street_line_2' => (string) ($shipment['deliveryAddress']['addressLine2'] ?? ''),
            'postal_code' => (string) ($shipment['deliveryAddress']['postalCode'] ?? ''),
            'city' => (string) ($shipment['deliveryAddress']['city'] ?? ''),
            'country_code' => (string) ($shipment['deliveryAddress']['country']['id'] ?? ''),
        ]);

        $shipmentData = [
            'number' => (string) ($shipment['shipmentNumber'] ?? ''),
            'type' => (string) ($shipment['shipmentType'] ?? ''),
            'status' => (string) ($shipment['status'] ?? ''),
            'on_hold' => (bool) ($shipment['hold'] ?? false),
            'date' => date('Y-m-d', strtotime(($shipment['shipmentDate'] ?? ''))),
            'customer_number' => (string) ($shipment['customer']['number'] ?? ''),
            'delivery_address_id' => $deliveryAddress->id,
            'name' => (string) ($shipment['deliveryContact']['name'] ?? ''),
            'attention' => (string) ($shipment['deliveryContact']['attention'] ?? ''),
            'email' => (string) ($shipment['deliveryContact']['email'] ?? ''),
            'phone' => (string) ($shipment['deliveryContact']['phone1'] ?? ''),
            'order_numbers' => [],
            'lines' => [],
        ];

        if ($shipment['shipmentDetailLines'] ?? null) {
            foreach ($shipment['shipmentDetailLines'] as $shipmentLine) {
                $shipmentData['lines'][] = [
                    'line_number' => (int) ($shipmentLine['lineNumber'] ?? 0),
                    'order_number' => (string) ($shipmentLine['orderNbr'] ?? ''),
                    'order_line_number' => (string) ($shipmentLine['orderLineNbr'] ?? ''),
                    'article_number' => (string) ($shipmentLine['inventoryNumber'] ?? ''),
                    'description' => (string) ($shipmentLine['description'] ?? ''),
                    'quantity' => (int) ($shipmentLine['orderedQty'] ?? 0),
                    'shipped_quantity' => (int) ($shipmentLine['shippedQty'] ?? 0),
                ];

                $shipmentData['order_numbers'][] = (string) ($shipmentLine['orderNbr'] ?? '');
            }
        }

        $shipmentData['order_numbers'] = array_filter(array_unique($shipmentData['order_numbers']));

        $shipmentService = new ShipmentService();
        $shipmentService->createShipment($shipmentData);
    }
}
