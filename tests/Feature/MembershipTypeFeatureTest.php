<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Book;
use App\Models\BookCopy;
use App\Models\Borrowing;
use App\Models\Category;
use App\Models\Member;
use App\Models\MembershipType;
use App\Models\PradeshiyaSabha;
use App\Models\PradeshiyaSabhaAdminAssignment;
use App\Models\PradeshiyaSabhaSetting;
use App\Models\Publisher;
use App\Models\User;
use App\Services\BorrowingService;
use Database\Seeders\MembershipTypeSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class MembershipTypeFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertSame('library_management_test', DB::connection()->getDatabaseName());
        $this->seed(MembershipTypeSeeder::class);
    }

    public function test_membership_type_seeder_creates_only_children_and_elders_idempotently(): void
    {
        $this->seed(MembershipTypeSeeder::class);

        $types = MembershipType::query()->assignable()->orderByRaw('upper(name)')->get();

        $this->assertSame(2, $types->count());
        $this->assertSame(['CHILDREN', 'ELDERS'], $types->map(fn (MembershipType $type) => strtoupper($type->name))->all());
        $this->assertSame(2, (int) $types->firstWhere('name', 'Children')->getRawOriginal('max_books'));
        $this->assertSame(3, (int) $types->firstWhere('name', 'Elders')->getRawOriginal('max_books'));
        $this->assertFalse(Schema::hasColumn('membership_types', 'pradeshiya_sabha_id'));
        $this->assertFalse(Schema::hasColumn('membership_types', 'is_active'));
    }

    public function test_create_and_edit_member_persist_selected_membership_type(): void
    {
        $sabha = $this->createSabha('MTUI');
        $this->setSabhaSettings($sabha);
        $children = $this->type('CHILDREN');
        $elders = $this->type('ELDERS');
        $admin = $this->createSabhaAdmin($sabha);

        $this->actingAs($admin)
            ->get(route('admin.members.create'))
            ->assertOk()
            ->assertSee('Children')
            ->assertSee('Elders');

        $this->post(route('admin.members.store'), $this->memberPayload($elders, $sabha, 'elders-member@example.test'))
            ->assertRedirect();

        $member = Member::query()->where('email', 'elders-member@example.test')->firstOrFail();
        $this->assertSame($elders->id, (int) $member->membership_type_id);
        $this->assertSame('ELDERS', strtoupper($member->membershipType->name));

        $this->get(route('admin.members.edit', $member))
            ->assertOk()
            ->assertSee('value="'.$elders->id.'" selected', false);

        $this->put(route('admin.members.update', $member), $this->memberPayload($children, $sabha, 'elders-member@example.test'))
            ->assertRedirect();

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'membership_type_id' => $children->id,
        ]);
        $this->get(route('admin.members.show', $member))
            ->assertOk()
            ->assertSee('Membership Type:')
                ->assertSee('Children');
    }

            public function test_children_are_limited_to_two_active_books_and_return_reopens_a_slot(): void
    {
        $sabha = $this->createSabha('MTIND');
        $this->setSabhaSettings($sabha);
        $member = $this->createMember($this->type('CHILDREN'), $sabha, 'children-limit@example.test');
        $books = [$this->createBook($sabha, 'IND1'), $this->createBook($sabha, 'IND2'), $this->createBook($sabha, 'IND3')];
        $service = app(BorrowingService::class);

        $first = $service->issue($member, $books[0]);
        $service->issue($member, $books[1]);
        $this->assertSame(2, $service->activeBorrowingCount($member));

        try {
            $service->issue($member, $books[2]);
            $this->fail('A child member must not exceed two active borrowings.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('borrowing limit', $exception->getMessage());
        }

        $service->returnBook($first);
        $this->assertSame(1, $service->activeBorrowingCount($member));
        $service->issue($member, $books[2]);
        $this->assertSame(2, $service->activeBorrowingCount($member));
    }

    public function test_elders_members_are_limited_to_three_active_books(): void
    {
        $sabha = $this->createSabha('MTFAM');
        $this->setSabhaSettings($sabha);
        $member = $this->createMember($this->type('ELDERS'), $sabha, 'elders-limit@example.test');
        $books = [
            $this->createBook($sabha, 'FAM1'),
            $this->createBook($sabha, 'FAM2'),
            $this->createBook($sabha, 'FAM3'),
            $this->createBook($sabha, 'FAM4'),
        ];
        $service = app(BorrowingService::class);

        foreach (array_slice($books, 0, 3) as $book) {
            $service->issue($member, $book);
        }
        $this->assertSame(3, $service->activeBorrowingCount($member));

        try {
            $service->issue($member, $books[3]);
            $this->fail('An elder member must not exceed three active borrowings.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('borrowing limit', $exception->getMessage());
        }
    }

    public function test_changing_membership_type_changes_future_borrowing_limit(): void
    {
        $sabha = $this->createSabha('MTCHG');
        $this->setSabhaSettings($sabha);
        $children = $this->type('CHILDREN');
        $elders = $this->type('ELDERS');
        $member = $this->createMember($children, $sabha, 'type-change@example.test');
        $books = [
            $this->createBook($sabha, 'CHG1'),
            $this->createBook($sabha, 'CHG2'),
            $this->createBook($sabha, 'CHG3'),
            $this->createBook($sabha, 'CHG4'),
        ];
        $service = app(BorrowingService::class);
        $service->issue($member, $books[0]);
        $service->issue($member, $books[1]);

        $member->update(['membership_type_id' => $elders->id]);
        $service->issue($member->fresh(), $books[2]);
        $this->assertSame(3, $service->activeBorrowingCount($member->fresh()));

        try {
            $service->issue($member->fresh(), $books[3]);
            $this->fail('The updated elder type must still enforce its three-book limit.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('borrowing limit', $exception->getMessage());
        }
    }

    public function test_unsupported_membership_type_is_rejected_and_sabha_admin_cannot_edit_other_member(): void
    {
        $sabhaA = $this->createSabha('MTXA');
        $sabhaB = $this->createSabha('MTXB');
        $this->setSabhaSettings($sabhaA);
        $this->setSabhaSettings($sabhaB);
        $unsupported = MembershipType::create(['name' => 'PAUSED', 'max_books' => 1, 'description' => 'Unsupported type']);
        $memberB = $this->createMember($this->type('CHILDREN'), $sabhaB, 'cross-sabha-member@example.test');
        $adminA = User::factory()->create([
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabhaA->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabhaA->id,
            'user_id' => $adminA->id,
            'is_active' => true,
        ]);

        $this->actingAs($adminA)
            ->put(route('admin.members.update', $memberB), $this->memberPayload($unsupported, $sabhaB, 'cross-sabha-member@example.test'))
            ->assertNotFound();

        $this->assertSame($this->type('CHILDREN')->id, (int) $memberB->fresh()->membership_type_id);

        $this->actingAs($adminA)
            ->from(route('admin.members.create'))
            ->post(route('admin.members.store'), $this->memberPayload($unsupported, $sabhaA, 'unsupported-type@example.test'))
            ->assertSessionHasErrors('membership_type_id');

        $this->assertDatabaseMissing('members', ['email' => 'unsupported-type@example.test']);
    }

    private function type(string $name): MembershipType
    {
        return MembershipType::query()->whereRaw('upper(name) = ?', [strtoupper($name)])->firstOrFail();
    }

    private function createSabha(string $code): PradeshiyaSabha
    {
        return PradeshiyaSabha::create(['code' => $code, 'name' => $code.' Pradeshiya Sabha']);
    }

    private function createSabhaAdmin(PradeshiyaSabha $sabha): User
    {
        $admin = User::factory()->create([
            'role' => User::ROLE_PRADESHIYA_SABHA_ADMIN,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);
        PradeshiyaSabhaAdminAssignment::create([
            'pradeshiya_sabha_id' => $sabha->id,
            'user_id' => $admin->id,
            'is_active' => true,
        ]);

        return $admin;
    }

    private function setSabhaSettings(PradeshiyaSabha $sabha): void
    {
        foreach ([
            'registration_fee' => '10.00',
            'monthly_membership_fee' => '5.00',
            'late_renewal_fee' => '2.00',
            'late_renewal_penalty' => '1.00',
            'membership_duration_months' => '1',
            'individual_borrowing_limit' => '2',
            'family_borrowing_limit' => '3',
            'issue_period_days' => '14',
            'maximum_renewals' => '2',
            'overdue_fine_amount' => '1.00',
            'currency' => 'LKR',
            'timezone' => 'Asia/Colombo',
        ] as $key => $value) {
            PradeshiyaSabhaSetting::query()->updateOrCreate(
                ['pradeshiya_sabha_id' => $sabha->id, 'key' => $key],
                ['value' => $value]
            );
        }
    }

    private function createMember(MembershipType $type, PradeshiyaSabha $sabha, string $email): Member
    {
        $user = User::factory()->create([
            'role' => User::ROLE_MEMBER,
            'is_active' => true,
            'pradeshiya_sabha_id' => $sabha->id,
        ]);

        return Member::create([
            'user_id' => $user->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'membership_type_id' => $type->id,
            'full_name' => $user->name,
            'email' => $email,
            'nic' => 'NIC-'.$user->id,
            'phone' => '0771234567',
            'address' => 'Test address',
            'registration_fee' => '10.00',
            'registration_date' => today(),
            'membership_start_date' => today(),
            'next_renewal_date' => today()->addMonth(),
            'expiry_date' => today()->addMonth(),
            'status' => 'ACTIVE',
        ]);
    }

    private function createBook(PradeshiyaSabha $sabha, string $suffix): Book
    {
        $author = Author::create(['name' => 'Membership Type Author '.$suffix]);
        $publisher = Publisher::create(['name' => 'Membership Type Publisher '.$suffix]);
        $category = Category::create(['code' => 'MT'.uniqid(), 'name' => 'Membership Type Category '.$suffix]);
        $book = Book::create([
            'book_id' => 'MEMTYPE-'.$suffix.'-'.uniqid(),
            'pradeshiya_sabha_id' => $sabha->id,
            'title' => 'Membership Type Book '.$suffix,
            'author_id' => $author->id,
            'publisher_id' => $publisher->id,
            'category_id' => $category->id,
            'ddc_number' => '600.1',
            'status' => 'available',
        ]);
        BookCopy::create([
            'book_id' => $book->id,
            'pradeshiya_sabha_id' => $sabha->id,
            'accession_number' => 'MEMTYPE-COPY-'.$suffix.'-'.uniqid(),
            'status' => 'AVAILABLE',
            'condition' => 'GOOD',
            'acquisition_date' => today(),
        ]);

        return $book;
    }

    private function memberPayload(MembershipType $type, PradeshiyaSabha $sabha, string $email): array
    {
        $idSuffix = strtoupper(substr(sha1($email), 0, 8));

        return [
            'full_name' => 'Membership Feature Member '.$idSuffix,
            'email' => $email,
            'password' => 'membership-test-password',
            'password_confirmation' => 'membership-test-password',
            'nic' => 'MT-'.$idSuffix,
            'phone' => '0771234567',
            'address' => 'Feature test address',
            'membership_type_id' => $type->id,
            'sabha_id' => $sabha->id,
            'status' => 'ACTIVE',
        ];
    }
}