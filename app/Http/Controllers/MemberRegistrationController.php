<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class MemberRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'sabhas' => PradeshiyaSabha::query()->where('is_active', true)->orderBy('name')->get(),
            'membershipTypes' => MembershipType::query()->assignable()->orderBy('name')->get(),
        ]);
    }

    public function store(RegisterRequest $request)
    {
        $data = $request->validated();
        DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['full_name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'role' => User::ROLE_MEMBER,
                'is_active' => true,
                'pradeshiya_sabha_id' => $data['pradeshiya_sabha_id'],
            ]);

            Member::create([
                'user_id' => $user->id,
                'pradeshiya_sabha_id' => $data['pradeshiya_sabha_id'],
                'membership_type_id' => $data['membership_type_id'],
                'full_name' => $data['full_name'],
                'email' => $data['email'],
                'nic' => $data['nic'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'registration_date' => today(),
                'membership_start_date' => today(),
                'status' => 'PENDING',
            ]);
        });

        return redirect()->route('register.submitted')->with('registration_submitted', true);
    }

    public function submitted(): View
    {
        return view('auth.registration-submitted');
    }
}
