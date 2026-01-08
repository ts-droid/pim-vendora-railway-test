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
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_shipment_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-1 minutes', strtotime($updatedAfter)));
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
                $articleNumber = (string) ($shipmentLine['inventoryNumber'] ?? '');

                $shipmentData['lines'][] = [
                    'line_number' => (int) ($shipmentLine['lineNumber'] ?? 0),
                    'order_number' => (string) ($shipmentLine['orderNbr'] ?? ''),
                    'order_line_number' => (string) ($shipmentLine['orderLineNbr'] ?? ''),
                    'article_number' => $articleNumber,
                    'description' => (string) ($shipmentLine['description'] ?? ''),
                    'quantity' => (int) ($shipmentLine['orderedQty'] ?? 0),
                    'shipped_quantity' => (int) ($shipmentLine['shippedQty'] ?? 0),
                ];

                $shipmentData['order_numbers'][] = (string) ($shipmentLine['orderNbr'] ?? '');

                trigger_stock_sync($articleNumber);
            }
        }

        $shipmentData['order_numbers'] = array_filter(array_unique($shipmentData['order_numbers']));

        $shipmentService = new ShipmentService();
        $shipmentService->createShipment($shipmentData);
    }

    public function completeShipment(Shipment $shipment, bool $isDirectDelivery = false): array
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $currentTime = microtime(true);
        $milliseconds = sprintf("%03d", ($currentTime - floor($currentTime)) * 1000);
        $date = date('Y-m-d\TH:i:s');

        $response = $this->callAPI('GET', '/v1/shipment/' . $shipment->number);
        $vismaShipment = $response['response'] ?? null;

        if (!$vismaShipment) {
            return [
                'success' => false,
                'message' => 'Failed to fetch shipment from Visma.net (' . json_encode($response['response']) . ')'
            ];
        }

        // Update picked quantities
        $updateData = [
            'shipmentDate' => ['value' => $date . '.' . $milliseconds . 'Z'],
            'shipmentDetailLines' => []
        ];

        foreach ($shipment->lines as $line) {
            $lineData = [
                'operation' => 'Update',
                'lineNumber' => ['value' => $line->line_number],
                'shippedQty' => ['value' => $line->picked_quantity],
                'warehouse' => ['value' => ($isDirectDelivery ? self::WAREHOUSE_DIRECT_ID : self::WAREHOUSE_ID)]
            ];

            if ($line->serial_number) {
                // Only add serial numbers if the article also has serial number management in Visma.net
                $vismaResponse = $this->callAPI('GET', '/v1/inventory/' . $line->article_number);
                $vismaHasSerialNumber = ($vismaResponse['response']['lotSerialClass']['id'] ?? '0') != '0';

                if ($vismaHasSerialNumber) {
                    $serialNumbers = explode(',', $line->serial_number);
                    $serialNumbers = array_map('trim', $serialNumbers);

                    // Make sure the serial numbers are unique
                    $checkedSerialNumbers = [];
                    for ($i = 0;$i < count($serialNumbers);$i++) {
                        $newSerialNumber = $serialNumbers[$i];

                        if (in_array($newSerialNumber, $checkedSerialNumbers)) {
                            $newSerialNumber .= str_replace('.', '', microtime(true)) . rand(1, 9_999_999_999);
                        }

                        $serialNumbers[$i] = $newSerialNumber;
                        $checkedSerialNumbers[] = $newSerialNumber;
                    }

                    $allocations = [];
                    $lineNbr = 0;

                    // Update existing allocations
                    foreach ($vismaShipment['shipmentDetailLines'] as $vismaLine) {
                        if ($vismaLine['lineNumber'] != $line->line_number) {
                            continue;
                        }

                        foreach (($vismaLine['allocations'] ?? []) as $existingAllocation) {
                            $allocations[] = [
                                'operation' => $existingAllocation['lotSerialNumber'] ? 'Update' : 'Insert',
                                'lineNbr' => ['value' => $existingAllocation['lineNbr']],
                                'quantity' => ['value' => 1],
                                'lotSerialNumber' => ['value' => ($serialNumbers[0] ?? '')],
                            ];

                            if ($existingAllocation['lineNbr'] > $lineNbr) {
                                $lineNbr = $existingAllocation['lineNbr'];
                            }

                            array_shift($serialNumbers);
                        }

                        break;
                    }

                    // Add new serial numbers
                    for ($i = 0;$i < ($line->picked_quantity - count($allocations));$i++) {
                        $lineNbr++;

                        $allocations[] = [
                            'operation' => 'Insert',
                            'lineNbr' => ['value' => $lineNbr],
                            'quantity' => ['value' => 1],
                            'lotSerialNumber' => ['value' => ($serialNumbers[0] ?? '')],
                        ];

                        array_shift($serialNumbers);
                    }

                    if (count($allocations) > 0) {
                        $lineData['allocations'] = $allocations;
                    }
                }
            }

            $updateData['shipmentDetailLines'][] = $lineData;
        }

        $response = $this->callAPI('PUT', '/v1/shipment/' . $shipment->number, $updateData);
        if (!$response['success']) {

            // Try again and add dummy data to serial numbers
            foreach ($updateData['shipmentDetailLines'] as &$lineData) {
                if (!($lineData['allocations'] ?? null)) {
                    continue;
                }

                for ($i = 0;$i < count($lineData['allocations']);$i++) {
                    $lineData['allocations'][$i]['lotSerialNumber']['value'] .= str_replace('.', '', microtime(true)) . rand(1, 9_999_999_999);
                }
            }

            $response = $this->callAPI('PUT', '/v1/shipment/' . $shipment->number, $updateData);

            if (!$response['success']) {

                $log = [
                    'post' => $updateData,
                    'response' => $response['response']
                ];

                log_data(json_encode($log));

                return [
                    'success' => false,
                    'message' => 'Failed to update shipment in Visma.net (' . json_encode($response['response']) . ')'
                ];
            }
        }

        // Confirm shipment
        $response = $this->callAPI('POST', '/v1/shipment/' . $shipment->number . '/action/confirmShipment');
        if (!$response['success']) {
            return [
                'success' => false,
                'message' => 'Failed to confirm shipment in Visma.net (' . json_encode($response['response']) . ')'
            ];
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    public function cancelShipment(Shipment $shipment)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $fetchResponse = $this->callAPI('GET', '/v1/shipment/' . $shipment->number);

        if (empty($fetchResponse['response']['shipmentNumber'])) {
            return [
                'success' => false,
                'message' => 'Shipment not found in Visma.net.'
            ];
        }

        $cancelResponse = $this->callAPI('POST', '/v1/shipment/' . $shipment->number . '/action/cancelShipment');

        if (!($cancelResponse['success'] ?? false)) {
            return [
                'success' => false,
                'message' => 'Failed to cancel shipment in Visma.net'
            ];
        }

        return [
            'success' => true,
            'message' => '',
        ];
    }

    public function deleteIfDeleted(Shipment $shipment)
    {
        $__serviceLogContext = [
            'service' => static::class,
            'method' => __FUNCTION__,
            'args' => func_get_args(),
        ];
        action_log('Invoked service method.', $__serviceLogContext);

        $response = $this->callAPI('GET', '/v1/shipment/' . $shipment->number);

        if (isset($response['response']['shipmentNumber'])) {
            return [
                'success' => false,
                'message' => 'The shipment still exists in Visma.net.'
            ];
        }

        // Make sure the API says the shipment does not exist
        $responseMessage = mb_strtolower($response['response']['message'] ?? '');
        if (!str_contains($responseMessage, 'could not be found')) {
            // The API request is probably faulty because the message does not contain the expected text
            return [
                'success' => false,
                'message' => 'Unexpected response from Visma.net, keep the shipment.'
            ];
        }

        $shipmentLines = ShipmentLine::where('shipment_id', $shipment->id)->get();

        foreach ($shipmentLines as $shipmentLine) {
            if ($shipmentLine->investigation_sound_path) {
                DoSpacesController::delete($shipmentLine->investigation_sound_path);
            }

            $shipmentLine->delete();
        }

        $shipment->delete();

        log_data('Deleted shipment ' . $shipment->number . ' locally. Could not find shipment in Visma.net');

        return [
            'success' => true,
            'message' => 'The shipment have been deleted locally.'
        ];
    }
}
