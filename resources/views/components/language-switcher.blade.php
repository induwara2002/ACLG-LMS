<form method="POST" action="{{ route('language.switch') }}" class="language-switcher" aria-label="{{ __('navigation.language') }}">
    @csrf
    <span class="language-switcher-label"><span aria-hidden="true">🌐</span> {{ __('navigation.language') }}</span>
    <label class="visually-hidden" for="application-locale">{{ __('navigation.language') }}</label>
    <select id="application-locale" name="locale" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="si" @selected(app()->getLocale() === 'si')>{{ __('navigation.sinhala') }}</option>
        <option value="en" @selected(app()->getLocale() === 'en')>{{ __('navigation.english') }}</option>
    </select>
</form>
