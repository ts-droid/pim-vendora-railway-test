<!DOCTYPE html>
<html>

<head>
    <title>{{ __('request_review_title') }}</title>

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        * {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 1rem;
        }
        body {
            background-color: #F8F8F8;
        }

        .container {
            width: 100%;
            max-width: 450px;
            margin: 35px auto;
        }
        .image-holder {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 35px;
        }
        .image-holder img {
            margin-right: 20px;
        }

        .stars {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-bottom: 35px;
        }
        .stars__star {
            cursor: pointer;
        }
        .stars__start__empty {
            display: block;
        }
        .stars__start__full {
            display: none;
        }
        .stars__star.active .stars__start__empty {
            display: none;
        }
        .stars__star.active .stars__start__full {
            display: block;
        }

        .input {
            margin-bottom: 20px;
        }
        .input label {
            display: block;
            font-size: 15px;
            margin-bottom: 6px;
        }
        .input input,
        .input textarea{
            width: 100%;
            outline: none !important;
            box-shadow: none !important;
            border: 1px solid #000000;
            font-size: 1rem;
            border-radius: 6px;
            padding: 10px;
            background-color: #FFFFFF;
            box-sizing: border-box;
        }

        button {
            width: 100%;
            background: #000000;
            color: #FFFFFF;
            border-radius: 50px;
            border: none;
            outline: none !important;
            box-shadow: none !important;
            padding: 8px;
            cursor: pointer;
        }

    </style>
</head>

<body>

<div class="container">
    <div class="image-holder">
        <img src="{{ $article->getMainImage() }}" style="height: 65px;width: 65px;" height="65" width="65" />
        <div>{{ $article->description }}</div>
    </div>

    <form method="POST" action="{{ route('customer.review.submit', ['lang' => app()->getLocale()]) }}">
        @csrf

        <input type="hidden" id="article-id" name="article_id" value="{{ $article->id }}">
        <input type="hidden" id="rating" name="rating" value="{{ $rating }}">

        <div class="stars">
            @for($i = 1;$i <= 5;$i++)
                <div class="stars__star {{ $rating >= $i ? 'active' : '' }}">
                    <div class="stars__start__empty">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-star" viewBox="0 0 16 16">
                            <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.56.56 0 0 0-.163-.505L1.71 6.745l4.052-.576a.53.53 0 0 0 .393-.288L8 2.223l1.847 3.658a.53.53 0 0 0 .393.288l4.052.575-2.906 2.77a.56.56 0 0 0-.163.506l.694 3.957-3.686-1.894a.5.5 0 0 0-.461 0z"/>
                        </svg>
                    </div>
                    <div class="stars__start__full">
                        <svg xmlns="http://www.w3.org/2000/svg" width="26" height="26" fill="currentColor" class="bi bi-star-fill" viewBox="0 0 16 16">
                            <path d="M3.612 15.443c-.386.198-.824-.149-.746-.592l.83-4.73L.173 6.765c-.329-.314-.158-.888.283-.95l4.898-.696L7.538.792c.197-.39.73-.39.927 0l2.184 4.327 4.898.696c.441.062.612.636.282.95l-3.522 3.356.83 4.73c.078.443-.36.79-.746.592L8 13.187l-4.389 2.256z"/>
                        </svg>
                    </div>
                </div>
            @endfor
        </div>

        <div class="input">
            <label>{{ __('request_review_name_label') }}</label>
            <input type="text" id="name" name="name" required>
        </div>

        <div class="input">
            <label>{{ __('request_review_review_label') }}</label>
            <textarea id="review" name="review" rows="4" placeholder="{{ __('request_review_review_placeholder') }}"></textarea>
        </div>

        <button type="submit">{{ __('request_review_review_button') }}</button>
    </form>
</div>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        const stars = document.querySelectorAll('.stars__star');
        const ratingInput = document.getElementById('rating');

        stars.forEach((star, index) => {
            star.addEventListener('click', () => {
                const rating = index + 1;

                // Update input value
                ratingInput.value = rating;

                // Toggle active class
                stars.forEach((s, i) => {
                    if (i < rating) {
                        s.classList.add('active');
                    } else {
                        s.classList.remove('active');
                    }
                });
            });
        });
    });
</script>

</body>
