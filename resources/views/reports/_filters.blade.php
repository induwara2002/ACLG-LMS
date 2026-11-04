<form method="GET" action="{{ route($routePrefix.'.reports.show', $report) }}" class="row g-3 align-items-end mb-4">
    @if (auth()->user()?->isMainAdmin())
        <div class="col-md-3"><label class="form-label">{{ __('Pradeshiya Sabha') }}</label><select class="form-select" name="sabha_id"><option value="">{{ __('All Sabhas') }}</option>@foreach ($sabhas as $sabha)<option value="{{ $sabha->id }}" @selected((string) ($filters['sabha_id'] ?? '') === (string) $sabha->id)>{{ $sabha->name }}</option>@endforeach</select></div>
    @endif
    @if (in_array($report, ['books', 'members', 'borrowings', 'overdue', 'returns', 'lost-books', 'damaged-books', 'fines', 'payments', 'compensation'], true))
        <div class="col-md-4"><label class="form-label">{{ __('Search') }}</label><input class="form-control" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="{{ __('Search records') }}"></div>
    @endif
    @if (in_array($report, ['members', 'borrowings', 'overdue', 'returns', 'lost-books', 'damaged-books', 'fines', 'payments', 'compensation'], true))
        <div class="col-md-3"><label class="form-label">{{ __('Member') }}</label><select name="member_id" class="form-select"><option value="">{{ __('All members') }}</option>@foreach ($members as $member)<option value="{{ $member->id }}" @selected(($filters['member_id'] ?? null) == $member->id)>{{ $member->member_id }} - {{ $member->full_name }}</option>@endforeach</select></div>
    @endif
    @if (in_array($report, ['borrowings', 'returns'], true))
        <div class="col-md-3"><label class="form-label">{{ __('Book') }}</label><select name="book_id" class="form-select"><option value="">{{ __('All books') }}</option>@foreach ($books as $book)<option value="{{ $book->id }}" @selected(($filters['book_id'] ?? null) == $book->id)>{{ $book->title }}</option>@endforeach</select></div>
    @endif
    @if ($report === 'books')
        <div class="col-md-3"><label class="form-label">{{ __('Availability') }}</label><select name="availability" class="form-select"><option value="">{{ __('All') }}</option>@foreach (['AVAILABLE','ISSUED','OVERDUE','LOST','DAMAGED','REPAIR'] as $status)<option value="{{ $status }}" @selected(($filters['availability'] ?? null) === $status)>{{ __($status) }}</option>@endforeach</select></div>
        <div class="col-md-3"><label class="form-label">{{ __('DDC') }}</label><input class="form-control" name="ddc" value="{{ $filters['ddc'] ?? '' }}"></div><div class="col-md-3"><label class="form-label">{{ __('Language') }}</label><input class="form-control" name="language" value="{{ $filters['language'] ?? '' }}"></div>
    @endif
    @if (in_array($report, ['borrowings', 'overdue'], true))
        <div class="col-md-3"><label class="form-label">{{ __('Status') }}</label><input class="form-control" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="{{ __('ISSUED, RETURNED') }}"></div>
    @endif
    @if ($report === 'members')
        <div class="col-md-3"><label class="form-label">{{ __('Membership type') }}</label><select name="membership_type_id" class="form-select"><option value="">{{ __('All') }}</option>@foreach ($membershipTypes as $type)<option value="{{ $type->id }}" @selected(($filters['membership_type_id'] ?? null) == $type->id)>{{ $type->name }}</option>@endforeach</select></div>
    @endif
    @if (in_array($report, ['payments', 'compensation'], true))
        <div class="col-md-3"><label class="form-label">{{ __('Payment type') }}</label><input class="form-control" name="payment_type" value="{{ $filters['payment_type'] ?? '' }}"></div><div class="col-md-3"><label class="form-label">{{ __('Payment status') }}</label><input class="form-control" name="status" value="{{ $filters['status'] ?? '' }}" placeholder="{{ __('PAID') }}"></div>
    @endif
    @if ($report === 'fines')
        <div class="col-md-3"><label class="form-label">{{ __('Fine type') }}</label><input class="form-control" name="fine_type" value="{{ $filters['fine_type'] ?? '' }}"></div><div class="col-md-3"><label class="form-label">{{ __('Status') }}</label><input class="form-control" name="status" value="{{ $filters['status'] ?? '' }}"></div>
    @endif
    @if ($report === 'damaged-books')
        <div class="col-md-3"><label class="form-label">{{ __('Damage type') }}</label><select name="damage_type" class="form-select"><option value="">{{ __('All') }}</option><option value="MINOR" @selected(($filters['damage_type'] ?? null) === 'MINOR')>{{ __('MINOR') }}</option><option value="MAJOR" @selected(($filters['damage_type'] ?? null) === 'MAJOR')>{{ __('MAJOR') }}</option></select></div>
    @endif
    <div class="col-md-3"><label class="form-label">{{ __('Date from') }}</label><input type="date" name="date_from" class="form-control" value="{{ $filters['date_from'] ?? '' }}"></div><div class="col-md-3"><label class="form-label">{{ __('Date to') }}</label><input type="date" name="date_to" class="form-control" value="{{ $filters['date_to'] ?? '' }}"></div>
    <div class="col-md-2 d-grid"><button class="btn btn-primary" type="submit">{{ __('Filter') }}</button></div><div class="col-md-2 d-grid"><a class="btn btn-outline-secondary" href="{{ route($routePrefix.'.reports.show', $report) }}">{{ __('Clear') }}</a></div>
</form>
