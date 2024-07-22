<?php

namespace App\Services;

use App\Models\CreditNote;
use App\Models\CreditNoteLine;

class CreditNoteService
{
    public function storeCreditNote(array $data): ?CreditNote
    {
        $creditNote = CreditNote::create([
            'credit_number' => (string) ($data['credit_number'] ?? ''),
            'date' => (string) ($data['date'] ?? ''),
            'status' => (string) ($data['status'] ?? ''),
            'customer_number' => (string) ($data['customer_number'] ?? ''),
            'currency' => (string) ($data['currency'] ?? ''),
            'amount' => (float) ($data['amount'] ?? 0),
        ]);

        if ($data['lines'] ?? false) {
            foreach ($data['lines'] as $line) {
                CreditNoteLine::create([
                    'credit_note_id' => $creditNote->id,
                    'line_key' => (string) ($line['line_key'] ?? ''),
                    'article_number' => (string) ($line['article_number'] ?? ''),
                    'description' => (string) ($line['description'] ?? ''),
                    'order_number' => (string) ($line['order_number'] ?? ''),
                    'shipment_number' => (string) ($line['shipment_number'] ?? ''),
                    'quantity' => (int) ($line['quantity'] ?? ''),
                    'unit_price' => (float) ($line['unit_price'] ?? ''),
                    'amount' => (float) ($line['amount'] ?? ''),
                    'cost' => (float) ($line['cost'] ?? ''),
                ]);
            }
        }

        return $creditNote;
    }

    public function updateCreditNote(CreditNote $creditNote, array $data): CreditNote
    {
        $fillables = (new CreditNote)->getFillable();
        $fillablesLine = (new CreditNoteLine)->getFillable();

        // Update the credit note
        foreach ($data as $key => $value) {
            if (in_array($key, $fillables)) {
                $creditNote->{$key} = $value;
            }
        }
        $creditNote->save();

        // Update the credit note lines
        foreach (($data['lines'] ?? []) as $line) {
            $creditNoteLine = CreditNoteLine::where([
                ['credit_note_id', '=', $creditNote->id],
                ['line_key', '=', $line['line_key']],
            ])->first();

            if ($creditNoteLine) {
                foreach ($line as $key => $value) {
                    if (in_array($key, $fillablesLine)) {
                        $creditNoteLine->{$key} = $value;
                    }
                }
                $creditNoteLine->save();
            }
        }

        return $creditNote;
    }
}
