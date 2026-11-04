<?php

namespace Tests\Feature;

use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase1SuperAdminArchitectureTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_create_and_update_sabha_records(): void
    {
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->post(route('admin.sabhas.store'), [
                'code' => 'northa',
                'name' => 'North Sabha',
                'address' => '1 Main Street',
                'phone' => '0111234567',
                'email' => 'north@example.test',
            ])
            ->assertRedirect();

        $sabha = PradeshiyaSabha::query()->where('code', 'NORTHA')->firstOrFail();
        $this->assertDatabaseHas('pradeshiya_sabhas', [
            'id' => $sabha->id,
            'address' => '1 Main Street',
            'phone' => '0111234567',
            'email' => 'north@example.test',
        ]);

        $this->put(route('admin.sabhas.update', $sabha), [
            'code' => 'NORTHB',
            'name' => 'North Sabha Updated',
            'address' => '2 Main Street',
            'phone' => '0117654321',
            'email' => 'updated@example.test',
        ])->assertRedirect();

        $this->assertDatabaseHas('pradeshiya_sabhas', [
            'id' => $sabha->id,
            'code' => 'NORTHB',
            'name' => 'North Sabha Updated',
        ]);
    }

    public function test_super_admin_sabha_validation_rejects_duplicate_codes_and_names_case_insensitively(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'GAL-MC', 'name' => 'Galle Municipal Council']);
        $otherSabha = PradeshiyaSabha::create(['code' => 'HIK-MC', 'name' => 'Hikkaduwa Municipal Council']);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->post(route('admin.sabhas.store'), [
                'code' => 'gal-mc',
                'name' => 'Another Council',
            ])
            ->assertSessionHasErrors('code');

        $this->post(route('admin.sabhas.store'), [
            'code' => 'UNIQUE',
            'name' => 'galle municipal council',
        ])->assertSessionHasErrors('name');

        $this->put(route('admin.sabhas.update', $otherSabha), [
            'code' => 'gal-mc',
            'name' => 'Galle Municipal Council',
        ])->assertSessionHasErrors(['code', 'name']);

        $this->put(route('admin.sabhas.update', $sabha), [
            'code' => 'gal-mc',
            'name' => 'galle municipal council',
        ])->assertRedirect();

        $this->assertSame('GAL-MC', $sabha->fresh()->code);
        $this->assertSame('galle municipal council', $sabha->fresh()->name);
    }

    public function test_super_admin_can_provision_and_reset_exactly_one_sabha_admin(): void
    {
        $sabhaA = PradeshiyaSabha::create(['code' => 'ADM1', 'name' => 'Admin Sabha One']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'ADM2', 'name' => 'Admin Sabha Two']);
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->post(route('admin.sabhas.admin.store', $sabhaA), [
                'name' => 'Sabha One Admin',
                'email' => 'sabha-one-admin@example.test',
                'password' => 'initial-admin-password',
                'password_confirmation' => 'initial-admin-password',
            ])
            ->assertRedirect();

        $admin = User::query()->where('email', 'sabha-one-admin@example.test')->firstOrFail();
        $this->assertSame(User::ROLE_SABHA_ADMIN, $admin->role);
        $this->assertSame($sabhaA->id, (int) $admin->pradeshiya_sabha_id);
        $this->assertSame($sabhaA->id, (int) $admin->pradeshiyaSabhaAdminAssignment()->value('pradeshiya_sabha_id'));

        $this->post(route('admin.sabhas.admin.store', $sabhaB), [
            'name' => 'Duplicate Admin',
            'email' => 'sabha-one-admin@example.test',
            'password' => 'another-admin-password',
            'password_confirmation' => 'another-admin-password',
        ])->assertSessionHasErrors('email');

        $this->put(route('admin.sabhas.admin.update', $sabhaA), [
            'name' => 'Sabha One Admin Updated',
            'email' => 'sabha-one-admin@example.test',
            'password' => 'reset-admin-password',
            'password_confirmation' => 'reset-admin-password',
            'is_active' => '1',
        ])->assertRedirect();

        $admin->refresh();
        $this->assertSame('Sabha One Admin Updated', $admin->name);
        $this->assertTrue(Hash::check('reset-admin-password', $admin->password));
        $this->assertSame(1, PradeshiyaSabhaAdminAssignment::query()->where('user_id', $admin->id)->count());
    }

    public function test_sabha_admin_can_change_only_their_own_password(): void
    {
        $sabha = PradeshiyaSabha::create(['code' => 'PASS', 'name' => 'Password Sabha']);
        $admin = $this->createSabhaAdmin($sabha, 'password-admin@example.test', 'current-admin-password');

        $this->actingAs($admin)
            ->put(route('account.password.update'), [
                'current_password' => 'current-admin-password',
                'password' => 'replacement-admin-password',
                'password_confirmation' => 'replacement-admin-password',
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Password updated successfully.');

        $this->assertTrue(Hash::check('replacement-admin-password', $admin->fresh()->password));
    }

    public function test_private_logo_is_visible_only_to_users_of_its_sabha(): void
    {
        Storage::fake('local');
        $sabhaA = PradeshiyaSabha::create(['code' => 'LOGA', 'name' => 'Logo Sabha A']);
        $sabhaB = PradeshiyaSabha::create(['code' => 'LOGB', 'name' => 'Logo Sabha B']);
        $adminA = $this->createSabhaAdmin($sabhaA, 'logo-admin-a@example.test');
        $adminB = $this->createSabhaAdmin($sabhaB, 'logo-admin-b@example.test');
        $superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);

        $this->actingAs($superAdmin)
            ->post(route('admin.sabhas.logo.upload', $sabhaA), ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertRedirect();

        $this->actingAs($adminA)->get(route('sabhas.logo', $sabhaA))->assertOk();
        $this->get(route('sabhas.logo', $sabhaB))->assertNotFound();
        $this->actingAs($adminB)->get(route('sabhas.logo', $sabhaA))->assertNotFound();
        $this->actingAs($superAdmin)->get(route('sabhas.logo', $sabhaA))->assertOk();

        Storage::disk('local')->assertExists($sabhaA->fresh()->logo_path);
    }

    public function test_member_cannot_access_super_admin_routes(): void
    {
        $member = User::factory()->create(['role' => User::ROLE_MEMBER, 'is_active' => true]);

        $this->actingAs($member)
            ->get(route('admin.sabhas.index'))
            ->assertRedirect(route('member.dashboard'));

        $this->post(route('admin.sabhas.store'), [
            'code' => 'DENY',
            'name' => 'Denied Sabha',
        ])->assertRedirect(route('member.dashboard'));

        $this->assertDatabaseMissing('pradeshiya_sabhas', ['code' => 'DENY']);
    }

    private function createSabhaAdmin(PradeshiyaSabha $sabha, string $email, string $password = 'current-admin-password'): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'password' => Hash::make($password),
            'role' => User::ROLE_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $user->id,
            'is_active' => true,
        ]);

        return $user;
    }
}