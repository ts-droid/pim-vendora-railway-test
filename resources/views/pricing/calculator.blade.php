@extends('layouts.pricing')

@section('title', 'Priskalkylator · ' . $article->article_number)

@section('content')
<div class="max-w-5xl mx-auto p-6">

    {{-- Article header --}}
    <div class="mb-6">
        <h1 class="text-2xl font-bold">{{ $article->description }}</h1>
        <div class="text-sm text-gray-600 mt-1">
            {{ $article->article_number }}
            @if ($article->ean)
                &middot; EAN {{ $article->ean }}
            @endif
            @if ($article->article_type === 'Bundle')
                <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-800 rounded text-xs font-medium">Bundle</span>
            @endif
        </div>
    </div>

    @include('pricing._calculator')

</div>
@endsection
