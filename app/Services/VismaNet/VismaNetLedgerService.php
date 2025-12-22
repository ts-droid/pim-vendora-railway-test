<?php

namespace App\Services\VismaNet;

use App\Http\Controllers\ConfigController;
use Illuminate\Support\Facades\DB;

class VismaNetLedgerService extends VismaNetApiService
{
    public function fetchTransactions(string $updatedAfter = '')
    {
        $fetchTime = date('Y-m-d H:i:s');
        $fetchedData = false;

        $params = [
            'pageSize' => 1000
        ];

        $updatedAfter = $updatedAfter ?: ConfigController::getConfig('vismanet_last_ledger_transactions_fetch');

        if ($updatedAfter) {
            $params['lastModifiedDateTime'] = date('Y-m-d H:i:s', strtotime('-10 minutes', strtotime($updatedAfter)));
            $params['lastModifiedDateTimeCondition'] = '>';
        }

        $transactions = $this->getPagedResult('/v1/GeneralLedgerTransactions', $params);

        if ($transactions) {
            foreach ($transactions as $transaction) {
                if (!$transaction || !is_array($transaction)) {
                    continue;
                }

                $fetchedData = true;

                $this->importTransaction($transaction);
            }
        }

        if ($fetchedData) {
            ConfigController::setConfigs(['vismanet_last_ledger_transactions_fetch' => $fetchTime]);
        }
    }

    public function importTransaction(array $transaction)
    {
        $this->importAccount($transaction);

        $externalId = ($transaction['refNumber'] ?? '') . '-' . ($transaction['lineNumber'] ?? '');

        $trans = DB::table('ledger_transactions')->where('external_id', $externalId)->first();

        $transactionData = [
            'external_id' => $externalId,
            'account_number' => (string) ($transaction['account']['number'] ?? ''),
            'date' => substr(($transaction['tranDate'] ?? ''), 0, 10),
            'period' => (string) ($transaction['period'] ?? ''),
            'module' => (string) ($transaction['module'] ?? ''),
            'description' => (string) ($transaction['description'] ?? ''),
            'debit' => (float) ($transaction['debitAmount'] ?? ''),
            'credit' => (float) ($transaction['creditAmount'] ?? ''),
            'currency' => (string) ($transaction['currency'] ?? ''),
            'updated_at' => now()
        ];

        if ($trans) {
            DB::table('ledger_transactions')
                ->where('external_id', $externalId)
                ->update($transactionData);
        } else {
            $transactionData['created_at'] = now();
            DB::table('ledger_transactions')->insert($transactionData);
        }
    }

    public function importAccount(array $transaction)
    {
        $accountNumber = (string) $transaction['account']['number'] ?? '';

        $account = DB::table('ledger_account')->where('number', $accountNumber)->first();

        $accountData = [
            'number' => $accountNumber,
            'type' => (string) ($transaction['account']['type'] ?? ''),
            'description' => (string) ($transaction['account']['description'] ?? ''),
            'updated_at' => now()
        ];

        if ($account) {
            DB::table('ledger_account')
                ->where('number', $accountNumber)
                ->update($accountData);
        } else {
            $accountData['created_at'] = now();
            DB::table('ledger_account')->insert($accountData);
        }
    }
}
