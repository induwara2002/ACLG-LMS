<div class="library-splash" data-library-splash data-login-url="{{ route('login') }}" aria-hidden="true">
    <div class="library-splash-stage">
        <div class="library-splash-ambient" aria-hidden="true"></div>
        <div class="library-splash-content">
            <header class="library-splash-heading">
                <img src="{{ asset('images/government-logo.png') }}" alt="{{ __('Sri Lankan government emblem') }}" width="74" height="74">
                <p class="library-splash-authority" lang="si">ප්‍රාදේශීය සභාව</p>
                <h1 lang="si">පුස්තකාල කළමනාකරණ පද්ධතිය</h1>
                <p class="library-splash-english">PRADESHIYA SABHA LIBRARY MANAGEMENT SYSTEM</p>
            </header>

            <div class="library-splash-library" aria-hidden="true">
                <div class="library-splash-shelves">
                    @for ($shelf = 0; $shelf < 3; $shelf++)
                        <div class="library-splash-shelf">
                            @for ($book = 0; $book < 12; $book++)
                                <span class="library-splash-shelf-book"></span>
                            @endfor
                        </div>
                    @endfor
                </div>
                <div class="library-splash-particles">
                    @for ($particle = 0; $particle < 12; $particle++)
                        <i></i>
                    @endfor
                </div>
                <div class="library-splash-desk"></div>
                <div class="library-splash-open-book">
                    <span class="library-splash-book-page library-splash-book-page-left"></span>
                    <span class="library-splash-book-page library-splash-book-page-right"></span>
                    <span class="library-splash-book-spine"></span>
                </div>
            </div>

            <div class="library-splash-loading">
                <div class="library-splash-progress-row">
                    <span class="library-splash-loading-label" lang="si" role="status" aria-live="polite">පද්ධතිය සූදානම් කරමින්...</span>
                    <span class="library-splash-percent" data-splash-percent aria-hidden="true">0%</span>
                </div>
                <div class="library-splash-progress" role="progressbar" aria-label="{{ __('Loading') }}" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-splash-progress>
                    <span></span>
                </div>
            </div>

            <footer class="library-splash-footer">
                <div class="library-splash-ornament" aria-hidden="true">
                    <span></span>
                    <svg viewBox="0 0 32 24" focusable="false"><path d="M16 6.2C12.7 3.8 8.4 3.5 3.5 5v14c4.9-1.5 9.2-1.2 12.5 1.2m0-14c3.3-2.4 7.6-2.7 12.5-1.2v14c-4.9-1.5-9.2-1.2-12.5 1.2m0-14v14"></path></svg>
                    <span></span>
                </div>
                <p>{{ __('Pradeshiya Sabha – Galle') }} <span aria-hidden="true">·</span> {{ __('Library') }}</p>
            </footer>
        </div>
    </div>
</div>
