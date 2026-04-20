@extends('layouts.pricing')

@section('title', 'GS1 · Integrationer')

@section('content')
@include('admin._header', ['rightLabel' => '<span class="text-gray-500">Integrationer · GS1</span>'])
@include('admin._nav', ['activeNav' => $activeNav, 'apiKey' => $apiKey])

<div class="max-w-4xl mx-auto p-6">

    @if (session('saved'))
        <div class="bg-green-50 border border-green-200 text-green-800 rounded p-3 mb-4 text-sm">
            {{ session('saved') }}
        </div>
    @endif

    <div class="mb-6">
        <h1 class="text-xl font-semibold text-gray-800">GS1 Validoo-integration</h1>
        <p class="text-sm text-gray-500 mt-1">
            OAuth2 password-grant mot
            <code class="bg-gray-100 px-1 rounded">validoopwe-apimanagement.azure-api.net</code>.
            Credentials hämtas från MyGS1 → Technical Integration.
        </p>
        <div class="mt-3 text-sm">
            Status:
            @if ($isConfigured)
                <span class="bg-green-100 text-green-800 rounded-full px-2 py-0.5 text-xs">✓ Konfigurerat</span>
            @else
                <span class="bg-amber-100 text-amber-800 rounded-full px-2 py-0.5 text-xs">Ej konfigurerat — fyll i alla *-fält</span>
            @endif
        </div>
    </div>

    <form method="POST" action="/admin/integrations/gs1?api_key={{ urlencode($apiKey) }}" class="bg-white border rounded p-6 space-y-4">

        @foreach ([
            'client_id'      => ['label' => 'Client ID *',      'hint' => 'Från MyGS1 → Technical Integration',               'secret' => false],
            'client_secret'  => ['label' => 'Client secret *',  'hint' => 'Lämna tom för att behålla befintligt värde',         'secret' => true],
            'username'       => ['label' => 'API-användare *',  'hint' => 'Det användarnamn som finns i MyGS1',                'secret' => false],
            'password'       => ['label' => 'API-lösenord *',   'hint' => 'Skickades via SMS vid setup',                        'secret' => true],
            'company_prefix' => ['label' => 'Company prefix *', 'hint' => 'T.ex. 735016797 — ska matcha din GS1-prefix',        'secret' => false],
            'scope'          => ['label' => 'Scope',            'hint' => 'Default: numberseries tradeitem offline_access',     'secret' => false],
            'token_url'      => ['label' => 'Token URL',        'hint' => 'Default: https://identity.validoo.se/connect/token', 'secret' => false],
            'environment'    => ['label' => 'Environment *',    'hint' => 'Vanligtvis "Production". GS1 Support anger vilken miljö er nyckel är för.', 'secret' => false],
        ] as $key => $meta)
            @php
                $source = $sources[$key] ?? 'none';
                $val = $values[$key] ?? '';
                $isSecret = $meta['secret'];
                $isSet = $val === '***SET***';
            @endphp
            <div>
                <label class="flex justify-between items-center text-xs text-gray-500 uppercase font-semibold mb-1">
                    <span>{{ $meta['label'] }}</span>
                    <span class="normal-case font-normal text-gray-400">
                        Källa:
                        @if ($source === 'db') <span class="text-blue-600">databas</span>
                        @elseif ($source === 'env') <span class="text-purple-600">env</span>
                        @elseif ($source === 'db-broken') <span class="text-red-600">kan ej dekrypteras</span>
                        @else —
                        @endif
                    </span>
                </label>
                @if ($isSecret)
                    <input type="password" name="{{ $key }}"
                           placeholder="{{ $isSet ? '•••••••• (satt — fyll i för att ändra)' : '—' }}"
                           value="{{ $isSet ? '***KEEP***' : '' }}"
                           onfocus="if(this.value==='***KEEP***'){this.value='';}"
                           class="border rounded px-3 py-2 text-sm w-full font-mono">
                @else
                    <input type="text" name="{{ $key }}"
                           value="{{ $val }}"
                           class="border rounded px-3 py-2 text-sm w-full font-mono">
                @endif
                <div class="text-xs text-gray-500 mt-1">{{ $meta['hint'] }}</div>
            </div>
        @endforeach

        <div class="flex justify-between gap-2 pt-4 border-t">
            <a href="/admin/integrations/gs1/test?api_key={{ urlencode($apiKey) }}"
               onclick="event.preventDefault(); document.getElementById('gs1-test-form').submit();"
               class="border rounded text-sm px-4 py-2 text-gray-700 hover:bg-gray-50 {{ $isConfigured ? '' : 'opacity-50 cursor-not-allowed' }}">
                Testa anslutning
            </a>
            <button type="submit" class="bg-green-600 hover:bg-green-700 text-white text-sm px-5 py-2 rounded">
                Spara
            </button>
        </div>
    </form>

    <form id="gs1-test-form" method="POST" action="/admin/integrations/gs1/test?api_key={{ urlencode($apiKey) }}" class="hidden"></form>

    <div class="text-xs text-gray-500 mt-4 p-3 bg-gray-50 border rounded">
        <strong>Hur det lagras:</strong> Client secret och password sparas Crypt-krypterade i
        <code class="bg-white px-1 rounded border">configs</code>-tabellen (nycklar
        <code>gs1_client_secret</code> / <code>gs1_password</code>). Övriga värden ligger i klartext.
        Om DB-värde saknas används <code>GS1_*</code>-miljövariabler från Railway som fallback.
        <br>
        <strong>Vid spara:</strong> cachade OAuth-tokens rensas så nästa anrop autentiserar om.
    </div>

</div>
@endsection
