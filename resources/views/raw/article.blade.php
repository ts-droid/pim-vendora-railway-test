<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex,nofollow">
</head>
<body>

<h1>{{ $article->shop_title_sv }}</h1>

<section id="description">
    {!! $article->shop_description_sv !!}
</section>

@if($faqEntries)
    <section id="faq">
        @foreach($faqEntries as $faqEntry)
            <div>
                <h3>{{ $faqEntry->question_sv }}</h3>
                <p>{{ $faqEntry->answer_sv }}</p>
            </div>
        @endforeach
    </section>
@endif

</body>
</html>
