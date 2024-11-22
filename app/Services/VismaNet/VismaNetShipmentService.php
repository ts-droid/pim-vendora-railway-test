<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use App\Http\Controllers\DoSpacesController;
use App\Models\Shipment;
use App\Models\ShipmentLine;
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
            'operation' => (string) ($shipment['operation'] ?? ''),
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

    public function completeShipment(Shipment $shipment): array
    {
        $currentTime = microtime(true);
        $milliseconds = sprintf("%03d", ($currentTime - floor($currentTime)) * 1000);
        $date = date('Y-m-d\TH:i:s');

        // Update picked quantities
        $updateData = [
            'shipmentDate' => $date . '.' . $milliseconds . 'Z',
            'shipmentDetailLines' => []
        ];

        foreach ($shipment->lines as $line) {
            $updateData['shipmentDetailLines'][] = [
                'operation' => 'Update',
                'lineNumber' => ['value' => $line->line_number],
                'shippedQty' => ['value' => $line->picked_quantity]
            ];
        }

        $response = $this->callAPI('PUT', '/v1/shipment/' . $shipment->number, $updateData);
        if (!$response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to update shipment in Visma.net (' . json_encode($response['response']) . ')'
            ];
        }

        // Confirm shipment
        $response = $this->callAPI('POST', '/v1/shipment/' . $shipment->number . '/action/confirmShipment');
        if (!$response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to confirm shipment in Visma.net'
            ];
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    public function deleteIfDeleted(Shipment $shipment)
    {
        $response = $this->callAPI('GET', '/v1/shipment/' . $shipment->number);

        if (isset($response['response']['shipmentNumber'])) {
            return [
                'success' => false,
                'message' => 'The shipment still exists in Visma.net.'
            ];
        }

        $shipmentLines = ShipmentLine::where('shipment_id', $shipment->id)->get();

        foreach ($shipmentLines as $shipmentLine) {
            if ($shipmentLines->investigation_sound_path) {
                DoSpacesController::delete($shipmentLines->investigation_sound_path);
            }

            $shipmentLine->delete();
        }

        $shipment->delete();

        return [
            'success' => true,
            'message' => 'The shipment have been deleted locally.'
        ];
    }
}
