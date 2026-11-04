@if (auth()->user()?->isMainAdmin())
    <div class="col-md-4">
        <label class="form-label" for="sabha_id">{{ __('Pradeshiya Sabha') }}</label>
        <select class="form-select" id="sabha_id" name="sabha_id">
            <option value="">{{ __('All Sabhas') }}</option>
            @foreach ($sabhas as $sabha)
                <option value="{{ $sabha->id }}" @selected((string) request()->query('sabha_id', '') === (string) $sabha->id)>{{ $sabha->name }}</option>
            @endforeach
        </select>
    </div>
@endif