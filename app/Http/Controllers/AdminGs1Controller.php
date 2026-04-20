<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\Config;
use App\Services\GS1\Gs1ValidooService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\View;

/**
 * GS1 Validoo integrations-inställningar.
 *
 * Skrivs till configs-tabellen (nyckel → content). Secret-fält
 * krypteras med Laravel Crypt innan de läggs ner. Gs1ValidooService
 * läser sedan dessa och faller tillbaka till miljövariabler om inget
 * finns i DB:n.
 */
class AdminGs1Controller extends Controller
{
    private const KEYS = [
        'client_id'      => 'GS1_CLIENT_ID',
        'client_secret'  => 'GS1_CLIENT_SECRET',
        'username'       => 'GS1_USERNAME',
        'password'       => 'GS1_PASSWORD',
        'company_prefix' => 'GS1_COMPANY_PREFIX',
        'scope'          => 'GS1_SCOPE',
        'token_url'      => 'GS1_TOKEN_URL',
        'environment'    => 'GS1_ENVIRONMENT',
    ];
    private const SECRET_KEYS = ['client_secret', 'password'];

    public function show(Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $values = [];
        $sources = [];
        foreach (self::KEYS as $key => $envName) {
            [$val, $source] = $this->readSetting($key, $envName);
            $values[$key] = $val;
            $sources[$key] = $source;
        }

        return View::make('admin.gs1', [
            'apiKey' => $apiKey,
            'activeNav' => 'gs1',
            'values' => $values,
            'sources' => $sources,
            'secretKeys' => self::SECRET_KEYS,
            'isConfigured' => app(Gs1ValidooService::class)->isConfigured(),
        ]);
    }

    public function save(Request $request)
    {
        $apiKey = $this->requireApiKey($request);

        $validated = $request->validate([
            'client_id' => 'nullable|string|max:255',
            'client_secret' => 'nullable|string|max:500',
            'username' => 'nullable|string|max:255',
            'password' => 'nullable|string|max:500',
            'company_prefix' => 'nullable|string|max:32',
            'scope' => 'nullable|string|max:255',
            'token_url' => 'nullable|string|max:500',
            'environment' => 'nullable|string|max:32',
        ]);

        foreach (self::KEYS as $key => $envName) {
            $value = $validated[$key] ?? '';
            // Lämna tomt = radera rad (så env-fallback kan ta över).
            // '***KEEP***' betyder att secret inte ändrades från UI.
            if ($value === '' || $value === '***KEEP***') {
                if ($value === '') {
                    Config::where('config', 'gs1_' . $key)->delete();
                }
                continue;
            }
            $stored = in_array($key, self::SECRET_KEYS, true)
                ? Crypt::encryptString($value)
                : $value;
            Config::updateOrCreate(
                ['config' => 'gs1_' . $key],
                ['content' => $stored]
            );
        }

        // Rensa cachade tokens så att nästa anrop autentiserar om med nya creds.
        Cache::forget('gs1_validoo_token');

        return redirect('/admin/integrations/gs1?api_key=' . urlencode($apiKey))
            ->with('saved', 'GS1-inställningar sparade. Cache rensad.');
    }

    public function test(Request $request, Gs1ValidooService $gs1)
    {
        $apiKey = $this->requireApiKey($request);
        $msg = 'GS1 ej konfigurerat — fyll i client_id, client_secret, username, password och company_prefix.';

        if ($gs1->isConfigured()) {
            try {
                Cache::forget('gs1_validoo_token'); // force fresh auth
                $keys = $gs1->generateGTIN(1);
                $msg = '✓ Anslutningstest lyckades. Fick test-GTIN: ' . $keys[0];
            } catch (\Throwable $e) {
                $msg = '✗ GS1-fel: ' . $e->getMessage();
            }
        }

        return redirect('/admin/integrations/gs1?api_key=' . urlencode($apiKey))
            ->with('saved', $msg);
    }

    private function readSetting(string $key, string $envName): array
    {
        $row = Config::where('config', 'gs1_' . $key)->first();
        if ($row && $row->content !== '') {
            if (in_array($key, self::SECRET_KEYS, true)) {
                try {
                    Crypt::decryptString($row->content);
                    // Returnera inte faktiskt värde till UI — bara "satt"-markör.
                    return ['***SET***', 'db'];
                } catch (\Throwable $e) {
                    // Skadad rad — behandla som osatt
                    return ['', 'db-broken'];
                }
            }
            return [$row->content, 'db'];
        }
        $envVal = (string) env($envName, '');
        if ($envVal !== '') {
            if (in_array($key, self::SECRET_KEYS, true)) {
                return ['***SET***', 'env'];
            }
            return [$envVal, 'env'];
        }
        return ['', 'none'];
    }

    private function requireApiKey(Request $request): string
    {
        $apiKey = (string) $request->input('api_key', '');
        abort_if(!$apiKey, 403, 'api_key query parameter required');
        abort_if(!ApiKey::where('api_key', $apiKey)->exists(), 403, 'Invalid api_key');
        return $apiKey;
    }
}
