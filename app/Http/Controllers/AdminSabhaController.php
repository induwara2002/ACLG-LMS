<?php

namespace App\Http\Controllers;

use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\User;
use App\Services\DashboardService;
use App\Services\PradeshiyaSabhaContext;
use App\Services\PradeshiyaSabhaSettingsService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminSabhaController extends Controller
{
    public function create(): View
    {
        return view('admin.sabhas.create');
    }

    public function index(): View
    {
        $sabhas = PradeshiyaSabha::query()
            ->with('adminAssignment.user')
            ->withCount([
                'members',
                'books as books_count',
                'borrowings as active_borrowings_count' => fn ($query) => $query->whereNull('return_date'),
                'borrowings as overdue_count' => fn ($query) => $query->whereNull('return_date')->whereDate('due_date', '<', today()),
                'payments',
            ])
            ->orderBy('name')
            ->paginate(20);

        return view('admin.sabhas.index', compact('sabhas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12', 'alpha_dash', $this->uniqueSabhaValue('code')],
            'name' => ['required', 'string', 'max:255', $this->uniqueSabhaValue('name')],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $sabha = PradeshiyaSabha::create($validated);

        return redirect()->route('admin.sabhas.show', $sabha)
            ->with('success', 'Pradeshiya Sabha created. Assign its administrator and logo to complete setup.');
    }

    public function update(Request $request, PradeshiyaSabha $sabha): RedirectResponse
    {
        $request->merge(['code' => strtoupper(trim((string) $request->input('code')))]);
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:12', 'alpha_dash', $this->uniqueSabhaValue('code', $sabha->id)],
            'name' => ['required', 'string', 'max:255', $this->uniqueSabhaValue('name', $sabha->id)],
            'address' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
        ]);

        $sabha->update($validated);

        return back()->with('success', 'Pradeshiya Sabha details updated.');
    }

    public function show(PradeshiyaSabha $sabha): View
    {
        $sabha->load(['settings', 'adminAssignment.user']);
        $settingsStatus = app(PradeshiyaSabhaSettingsService::class)->configurationStatus($sabha);

        return view('admin.sabhas.show', compact('sabha', 'settingsStatus'));
    }

    public function dashboard(PradeshiyaSabha $sabha, DashboardService $dashboardService, PradeshiyaSabhaContext $context): View
    {
        abort_unless($sabha->is_active, 404);
        request()->session()->put('active_pradeshiya_sabha_id', $sabha->id);
        $data = $context->runFor($sabha, fn () => $dashboardService->admin((int) request()->query('year', now()->year)));
        $data['pradeshiyaSabha'] = $sabha;
        $data['sabhas'] = collect();
        $data['sabhaTotals'] = [
            'total' => PradeshiyaSabha::query()->count(),
            'active' => PradeshiyaSabha::query()->where('is_active', true)->count(),
            'libraries' => PradeshiyaSabha::query()
                ->whereHas('settings', fn ($query) => $query
                    ->where('key', 'library_name')
                    ->whereNotNull('value')
                    ->where('value', '<>', ''))
                ->count(),
            'activeAdmins' => PradeshiyaSabhaAdminAssignment::query()
                ->where('is_active', true)
                ->whereHas('user', fn ($query) => $query->where('is_active', true)->where('role', 'PRADESHIYA_SABHA_ADMIN'))
                ->count(),
        ];
        $data['managementUrl'] = route('admin.sabhas.show', $sabha);

        return view('admin.dashboard', $data);
    }

    public function updateStatus(Request $request, PradeshiyaSabha $sabha): RedirectResponse
    {
        $validated = $request->validate(['is_active' => ['required', 'boolean']]);
        $sabha->update(['is_active' => (bool) $validated['is_active']]);

        return back()->with('success', 'Pradeshiya Sabha status updated.');
    }

    public function updateSettings(Request $request, PradeshiyaSabha $sabha): RedirectResponse
    {
        $allowedKeys = array_merge(
            PradeshiyaSabhaSettingsService::REQUIRED_SETTINGS,
            PradeshiyaSabhaSettingsService::OPTIONAL_SETTINGS
        );
        $submittedKeys = array_diff(array_keys($request->except(['_token', '_method'])), $allowedKeys);
        if ($submittedKeys !== []) {
            return back()->withErrors(['settings' => 'Unsupported library setting submitted.'])->withInput();
        }

        if (is_string($request->input('currency'))) {
            $request->merge(['currency' => strtoupper(trim($request->input('currency')))]);
        }

        $rules = [];
        foreach ($allowedKeys as $key) {
            $rules[$key] = match ($key) {
                'registration_fee', 'monthly_membership_fee', 'late_renewal_fee', 'late_renewal_penalty', 'overdue_fine_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99', 'decimal:0,2'],
                'membership_duration_months' => ['required', 'integer', 'min:1', 'max:120'],
                'individual_borrowing_limit', 'family_borrowing_limit' => ['required', 'integer', 'min:1', 'max:100'],
                'issue_period_days' => ['required', 'integer', 'min:1', 'max:365'],
                'maximum_renewals' => ['required', 'integer', 'min:0', 'max:50'],
                'currency' => ['required', 'string', 'alpha', 'size:3', 'regex:/^[A-Z]{3}$/', function ($attribute, $value, $fail) {
                    if (! app(PradeshiyaSabhaSettingsService::class)->isValidCurrencyCode($value)) {
                        $fail('Select a valid ISO currency code.');
                    }
                }],
                'timezone' => ['required', 'timezone'],
                'name_si' => ['nullable', 'string', 'max:255'],
                'library_name' => ['nullable', 'string', 'max:255'],
                'library_address' => ['nullable', 'string', 'max:2000'],
                'contact_number' => ['nullable', 'string', 'max:50'],
                'library_email' => ['nullable', 'email', 'max:255'],
                'opening_hours' => ['nullable', 'string', 'max:1000'],
            };
        }

        $validated = $request->validate($rules);

        DB::transaction(function () use ($validated, $sabha) {
            foreach ($validated as $key => $value) {
                PradeshiyaSabhaSetting::query()->updateOrCreate(
                    ['pradeshiya_sabha_id' => $sabha->id, 'key' => $key],
                    ['value' => $value === null ? null : (string) $value]
                );
            }
        });

        $status = app(PradeshiyaSabhaSettingsService::class)->configurationStatus($sabha->fresh());

        return back()->with('success', $status['complete']
            ? 'Sabha settings saved. Configuration complete.'
            : 'Sabha settings saved. Configuration incomplete.');
    }

    public function uploadLogo(Request $request, PradeshiyaSabha $sabha): RedirectResponse
    {
        $validated = $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ]);

        $extension = strtolower($validated['logo']->extension());
        $path = $validated['logo']->storeAs(
            'pradeshiya-sabhas/'.$sabha->id,
            'logo.'.$extension,
            'local'
        );
        $oldPath = $sabha->logo_path;
        $sabha->update(['logo_path' => $path]);

        if ($oldPath && $oldPath !== $path) {
            Storage::disk('local')->delete($oldPath);
        }
        Storage::disk('public')->delete('pradeshiya-sabhas/'.$sabha->code.'/logo.png');
        Storage::disk('public')->delete('pradeshiya-sabhas/'.$sabha->code.'/logo.jpg');
        Storage::disk('public')->delete('pradeshiya-sabhas/'.$sabha->code.'/logo.jpeg');
        Storage::disk('public')->delete('pradeshiya-sabhas/'.$sabha->code.'/logo.webp');

        return back()->with('success', 'Sabha logo updated.');
    }

    public function removeLogo(PradeshiyaSabha $sabha): RedirectResponse
    {
        if ($sabha->logo_path) {
            Storage::disk('local')->delete($sabha->logo_path);
            $sabha->update(['logo_path' => null]);
        }

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            Storage::disk('public')->delete('pradeshiya-sabhas/'.$sabha->code.'/logo.'.$extension);
        }

        return back()->with('success', 'Sabha logo removed.');
    }

    public function updateAdmin(Request $request, PradeshiyaSabha $sabha): RedirectResponse
    {
        $assignment = $sabha->adminAssignment()->with('user')->firstOrFail();
        $user = $assignment->user;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'string', 'min:12', 'confirmed'],
            'is_active' => ['required', 'boolean'],
        ]);

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->is_active = (bool) $validated['is_active'];
        if (! empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();
        $assignment->is_active = (bool) $validated['is_active'];
        $assignment->save();

        return back()->with('success', 'Sabha administrator updated.');
    }

    public function storeAdmin(Request $request, PradeshiyaSabha $sabha): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12', 'confirmed'],
        ]);

        DB::transaction(function () use ($validated, $sabha) {
            $lockedSabha = PradeshiyaSabha::query()->lockForUpdate()->findOrFail($sabha->id);
            abort_if($lockedSabha->adminAssignment()->exists(), 409, 'This Sabha already has an administrator assignment.');

            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => User::ROLE_SABHA_ADMIN,
                'is_active' => true,
                'pradeshiya_sabha_id' => $lockedSabha->id,
            ]);

            $lockedSabha->adminAssignment()->create([
                'user_id' => $user->id,
                'is_active' => true,
            ]);
        });

        return back()->with('success', 'Sabha administrator created.');
    }

    public function updateOwnSettings(Request $request): RedirectResponse
    {
        $sabha = $request->user()->pradeshiyaSabha()->where('is_active', true)->firstOrFail();

        return $this->updateSettings($request, $sabha);
    }

    private function uniqueSabhaValue(string $column, ?int $ignoreId = null): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail) use ($column, $ignoreId): void {
            $query = PradeshiyaSabha::query()
                ->whereRaw('LOWER('.$column.') = ?', [mb_strtolower(trim((string) $value))]);

            if ($ignoreId !== null) {
                $query->whereKeyNot($ignoreId);
            }

            if ($query->exists()) {
                $fail('The '.$attribute.' has already been taken.');
            }
        };
    }
}
