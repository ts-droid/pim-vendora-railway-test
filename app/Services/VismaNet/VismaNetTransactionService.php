<?php

namespace App\Services\VismaNet;

use App\Models\Article;
use App\Services\TransactionService;

class VismaNetTransactionService extends VismaNetApiService
{
    /**
     * Fetches all transactions from Visma.net.
     *
     * @return void
     */
    public function fetchTransactions(): void
    {
        $this->fetchTransactionsForAccount(4092);
    }

    /**
     * Fetches all transactions for a specific account.
     *
     * @param int $accountNumber
     * @return void
     */
    public function fetchTransactionsForAccount(int $accountNumber): void
    {
        $getParams = [
            'ledger' => 1, // Redovisning
            'fromPeriod' => '202001',
            'toPeriod' => date('Ym'),
            'account' => $accountNumber,
        ];

        $transactions = $this->getPagedResult('/v1/GeneralLedgerTransactions', $getParams);

        if (!$transactions) {
            return;
        }

        $transactionService = new TransactionService();

        foreach ($transactions as $transaction) {
            $accountNumber = $transaction['account']['number'] ?? '';
            $lineNumber = $transaction['lineNumber'] ?? '';
            $batchNumber = $transaction['batchNumber'] ?? '';
            $transactionDate = $transaction['tranDate'] ?? '';

            if (!$lineNumber || !$batchNumber || !$accountNumber || !$transactionDate) {
                continue;
            }

            $debitAmount = $transaction['debitAmount'] ?? 0;
            $creditAmount = $transaction['creditAmount'] ?? 0;

            if ($transaction['currDebitAmount'] ?? 0) {
                $currencyRate = $transaction['debitAmount'] / $transaction['currDebitAmount'];
            }
            elseif ($transaction['currCreditAmount'] ?? 0) {
                $currencyRate = $transaction['creditAmount'] / $transaction['currCreditAmount'];
            }
            else {
                $currencyRate = 0;
            }

            $transactionService->saveTransaction([
                'transaction_id' => $lineNumber . '_' . $batchNumber . '_' . $accountNumber . '_' . $transactionDate,
                'date' => date('Y-m-d', strtotime($transactionDate)),
                'account' => (string) $accountNumber,
                'debit' => (float) $debitAmount,
                'credit' => (float) $creditAmount,
                'currency' => ($transaction['currency'] ?? ''),
                'currency_rate' => $currencyRate,
            ]);
        }
    }

}
