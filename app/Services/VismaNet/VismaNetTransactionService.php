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

            $debitAmount = $transaction['debitAmount'];
            $creditAmount = $transaction['creditAmount'];

            if ($transaction['currDebitAmount']) {
                $currencyRate = $transaction['debitAmount'] / $transaction['currDebitAmount'];
            }
            elseif ($transaction['currCreditAmount']) {
                $currencyRate = $transaction['creditAmount'] / $transaction['currCreditAmount'];
            }
            else {
                $currencyRate = 0;
            }

            $transactionService->saveTransaction([
                'transaction_id' => $transaction['lineNumber'] . '_' . $transaction['batchNumber'] . '_' . $accountNumber . '_' . $transaction['tranDate'],
                'date' => date('Y-m-d', strtotime($transaction['tranDate'])),
                'account' => (string) $accountNumber,
                'debit' => (float) $debitAmount,
                'credit' => (float) $creditAmount,
                'currency' => $transaction['currency'],
                'currency_rate' => $currencyRate,
            ]);
        }
    }

}
