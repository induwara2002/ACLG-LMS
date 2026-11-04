<div class="page-loader" data-page-loader aria-hidden="true">
    <div class="page-loader-content" role="status" aria-live="polite" aria-atomic="true">
        <header class="page-loader-brand">
            <img src="{{ asset('images/government-logo.png') }}" alt="ශ්‍රී ලංකා රාජ්‍ය ලාංඡනය" width="58" height="58">
            <p class="page-loader-title" lang="si">ප්‍රාදේශීය සභා පුස්තකාල කළමනාකරණ පද්ධතිය</p>
        </header>

        <div class="page-loader-scene" aria-hidden="true">
            <div class="page-loader-shelves">
                @for ($shelf = 0; $shelf < 3; $shelf++)
                    <div class="page-loader-shelf">
                        @for ($book = 0; $book < 9; $book++)
                            <span class="page-loader-shelf-book"></span>
                        @endfor
                    </div>
                @endfor
            </div>
            <div class="page-loader-open-book">
                <span class="page-loader-open-book-page page-loader-open-book-page-left"></span>
                <span class="page-loader-open-book-page page-loader-open-book-page-right"></span>
                <span class="page-loader-open-book-spine"></span>
            </div>
        </div>

        <div class="page-loader-status">
            <p class="page-loader-label" lang="si">පිටුව පූරණය වෙමින් පවතී...</p>
            <div class="page-loader-progress" aria-hidden="true"><span></span></div>
        </div>
    </div>
</div>
