{{--
    Shared tab-bar.
    Required:
      $tabs        (array: key => label)
      $activeTab   (string)
      $queryPrefix (string)  The URL params before tab= e.g. "api_key=abc&"
--}}
<div class="flex border-b mb-6 overflow-x-auto">
    @foreach ($tabs as $key => $label)
        @php
            $isActive = $activeTab === $key;
        @endphp
        <a href="?{{ $queryPrefix }}tab={{ $key }}"
           class="px-4 py-2 text-sm whitespace-nowrap {{ $isActive ? 'border-b-2 border-blue-500 text-blue-600 font-semibold' : 'text-gray-500 hover:text-gray-900' }}">
            {{ $label }}
        </a>
    @endforeach
</div>
