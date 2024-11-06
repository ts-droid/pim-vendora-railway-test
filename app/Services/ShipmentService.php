<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\ShipmentLine;

class ShipmentService
{
    public function createShipment(array $data): Shipment
    {
        $existingShipment = Shipment::where('number', $data['number'])->first();
        if ($existingShipment) {
            return $this->updateShipment($existingShipment, $data);
        }

        $shipment = Shipment::create([
            'number' => $data['number'],
            'type' => $data['type'] ?? '',
            'status' => $data['status'] ?? '',
            'on_hold' => ($data['on_hold'] ?? false) ? 1 : 0,
            'date' => $data['date'] ?? '',
            'customer_number' => $data['customer_number'] ?? '',
            'delivery_address_id' => $data['delivery_address_id'] ?? 0,
            'name' => $data['name'] ?? '',
            'attention' => $data['attention'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
            'order_numbers' => $data['order_numbers'] ?? [],
        ]);

        if (isset($data['lines'])) {
            foreach ($data['lines'] as $line) {
                ShipmentLine::create([
                    'shipment_id' => $shipment->id,
                    'line_number' => $line['line_number'],
                    'order_number' => $line['order_number'] ?? '',
                    'order_line_number' => $line['order_line_number'] ?? '',
                    'article_number' => $line['article_number'] ?? '',
                    'description' => $line['description'] ?? '',
                    'quantity' => $line['quantity'] ?? 0,
                    'shipped_quantity' => $line['shipped_quantity'] ?? 0,
                ]);
            }
        }

        return $shipment;
    }

    public function updateShipment(Shipment $shipment, array $data): Shipment
    {
        $shipment->update([
            'type' => $data['type'] ?? $shipment->type,
            'status' => $data['status'] ?? $shipment->status,
            'on_hold' => isset($data['on_hold']) ? ($data['on_hold'] ? 1 : 0) : $shipment->on_hold,
            'date' => $data['date'] ?? $shipment->date,
            'customer_number' => $data['customer_number'] ?? $shipment->customer_number,
            'delivery_address_id' => $data['delivery_address_id'] ?? $shipment->delivery_address_id,
            'name' => $data['name'] ?? $shipment->name,
            'attention' => $data['attention'] ?? $shipment->attention,
            'email' => $data['email'] ?? $shipment->email,
            'phone' => $data['phone'] ?? $shipment->phone,
            'order_numbers' => $data['order_numbers'] ?? $shipment->order_numbers,
        ]);

        if (isset($data['lines'])) {
            foreach ($data['lines'] as $line) {
                $shipmentLine = ShipmentLine::where('shipment_id', $shipment->id)
                    ->where('line_number', $line['line_number'])
                    ->first();

                $shipmentLine = $shipmentLine ?: new ShipmentLine();

                $shipmentLine->order_number = $line['order_number'] ?? $shipmentLine->order_number;
                $shipmentLine->order_line_number = $line['order_line_number'] ?? $shipmentLine->order_line_number;
                $shipmentLine->article_number = $line['article_number'] ?? $shipmentLine->article_number;
                $shipmentLine->description = $line['description'] ?? $shipmentLine->description;
                $shipmentLine->quantity = $line['quantity'] ?? $shipmentLine->quantity;
                $shipmentLine->shipped_quantity = $line['shipped_quantity'] ?? $shipmentLine->shipped_quantity;


                if ($line['delete'] ?? false) {
                    $shipmentLine->delete();
                }
                else {
                    $shipmentLine->save();
                }
            }
        }

        return $shipment;
    }
}
