<?php

namespace App\Services;

use App\Models\LedgerTransaction;
use Illuminate\Support\Facades\Validator;

class TransactionService
{
    /**
     * Returns a summary of transactions for a specific account and period.
     *
     * @param string $accountNumber
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getPeriodSummary(string $accountNumber, string $startDate, string $endDate): array
    {
        $transactions = LedgerTransaction::where('account', $accountNumber)
            ->where('date', '>=', $startDate)
            ->where('date', '<=', $endDate)
            ->get();

        $debit = 0;
        $credit = 0;

        if ($transactions) {
            foreach ($transactions as $transaction) {
                $debit += $transaction->debit;
                $credit += $transaction->credit;
            }
        }

        return [
            'debit' => $debit,
            'credit' => $credit,
        ];
    }

    /**
     * Create or update a transaction.
     *
     * @param $transactionData
     * @return array
     */
    public function saveTransaction($transactionData): array
    {
        $validator = Validator::make($transactionData, [
            'transaction_id' => 'required|string',
            'date' => 'required|string',
            'account' => 'required|string',
            'debit' => 'required',
            'credit' => 'required',
            'currency' => 'required|string',
            'currency_rate' => 'required',
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();

            return [
                'success' => false,
                'message' => $errors[0] ?? 'Validation failed.',
            ];
        }

        $existingTransaction = LedgerTransaction::where('transaction_id', $transactionData['transaction_id'])->first();

        if ($existingTransaction) {
            // Update transaction
            $existingTransaction->update([
                'date' => $transactionData['date'],
                'account' => $transactionData['account'],
                'debit' => $transactionData['debit'],
                'credit' => $transactionData['credit'],
                'currency' => $transactionData['currency'],
                'currency_rate' => $transactionData['currency_rate'],
            ]);
        }
        else {
            // Create new transaction
            LedgerTransaction::create([
                'transaction_id' => $transactionData['transaction_id'],
                'date' => $transactionData['date'],
                'account' => $transactionData['account'],
                'debit' => $transactionData['debit'],
                'credit' => $transactionData['credit'],
                'currency' => $transactionData['currency'],
                'currency_rate' => $transactionData['currency_rate'],
            ]);
        }

        return [
            'success' => true,
            'message' => 'Transaction saved.',
        ];
    }
}
