{{--
    Global admin-nav, inkluderas alltid direkt efter _header.blade.php.
    Required:
      $activeNav   (string) — 'articles' | 'suppliers' | 'customers' | 'brands'
      $apiKey      (string)
--}}
<div class="bg-white border-b">
    <div class="max-w-6xl mx-auto px-6 flex gap-1">
        @php
            $links = [
                'articles'  => ['label' => 'Artiklar',      'href' => '/admin/articles'],
                'suppliers' => ['label' => 'Leverantörer',  'href' => '/admin/suppliers'],
                'customers' => ['label' => 'Kunder',        'href' => '/admin/customers'],
                'brands'    => ['label' => 'Varumärken',    'href' => '/admin/brands'],
            ];
        @endphp
        @foreach ($links as $key => $l)
            @php $active = ($activeNav ?? '') === $key; @endphp
            <a href="{{ $l['href'] }}?api_key={{ urlencode($apiKey) }}"
               class="px-4 py-3 text-sm whitespace-nowrap border-b-2 {{ $active ? 'border-blue-600 text-blue-600 font-semibold' : 'border-transparent text-gray-600 hover:text-gray-900' }}">
                {{ $l['label'] }}
            </a>
        @endforeach
    </div>
</div>
