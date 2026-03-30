<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RawImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_guest_cannot_access_transactions(): void
    {
        $this->get(route('transactions.index'))->assertRedirect(route('login'));
    }

    public function test_guest_cannot_create_transaction(): void
    {
        $this->post(route('transactions.store'), [])->assertRedirect(route('login'));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_sees_only_their_transactions(): void
    {
        [$user, $other] = User::factory(2)->create();
        $import = $this->makeImport($user);
        $otherImport = $this->makeImport($other);

        Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'date' => '2026-03-01', 'description' => 'Mine', 'amount' => 100, 'type' => 'debit',
        ]);
        Transaction::create([
            'user_id' => $other->id, 'raw_import_id' => $otherImport->id,
            'date' => '2026-03-01', 'description' => 'Theirs', 'amount' => 999, 'type' => 'debit',
        ]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('transactions/index')
                ->where('total', 1)
                ->where('transactions.0.description', 'Mine')
            );
    }

    public function test_index_paginates_correctly(): void
    {
        $user = User::factory()->create();
        $import = $this->makeImport($user);

        for ($i = 0; $i < 30; $i++) {
            Transaction::create([
                'user_id' => $user->id, 'raw_import_id' => $import->id,
                'date' => '2026-03-01', 'description' => "T{$i}", 'amount' => 10, 'type' => 'debit',
            ]);
        }

        $this->actingAs($user)
            ->get(route('transactions.index').'?page=1')
            ->assertInertia(fn ($page) => $page
                ->where('total', 30)
                ->where('current_page', 1)
                ->where('has_more', true)
                ->has('transactions', 25)
            );

        $this->actingAs($user)
            ->get(route('transactions.index').'?page=2')
            ->assertInertia(fn ($page) => $page
                ->where('has_more', false)
                ->has('transactions', 5)
            );
    }

    public function test_index_includes_categories(): void
    {
        $user = User::factory()->create();
        Category::create(['user_id' => $user->id, 'name' => 'Alimentação', 'color' => '#f97316', 'keywords' => []]);

        $this->actingAs($user)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page
                ->has('categories', 1)
                ->where('categories.0.name', 'Alimentação')
            );
    }

    // ─── Store ────────────────────────────────────────────────────────────────

    public function test_user_can_create_debit_transaction(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'date' => '2026-03-15',
                'description' => 'Supermercado',
                'amount' => '125.50',
                'type' => 'debit',
                'category_id' => null,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'description' => 'Supermercado',
            'amount' => 125.50,
            'type' => 'debit',
        ]);
    }

    public function test_user_can_create_credit_transaction(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'date' => '2026-03-01',
                'description' => 'Salário',
                'amount' => '5000.00',
                'type' => 'credit',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => 'credit',
            'amount' => 5000.00,
        ]);
    }

    public function test_user_can_create_transaction_with_category(): void
    {
        $user = User::factory()->create();
        $cat = Category::create(['user_id' => $user->id, 'name' => 'Lazer', 'color' => '#3b82f6', 'keywords' => []]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'date' => '2026-03-10',
                'description' => 'Cinema',
                'amount' => '35.00',
                'type' => 'debit',
                'category_id' => $cat->id,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'category_id' => $cat->id,
        ]);
    }

    // ─── Store validation ─────────────────────────────────────────────────────

    public function test_store_requires_date(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), ['description' => 'X', 'amount' => 10, 'type' => 'debit'])
            ->assertSessionHasErrors('date');
    }

    public function test_store_requires_description(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), ['date' => '2026-03-01', 'amount' => 10, 'type' => 'debit'])
            ->assertSessionHasErrors('description');
    }

    public function test_store_rejects_zero_amount(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), ['date' => '2026-03-01', 'description' => 'X', 'amount' => 0, 'type' => 'debit'])
            ->assertSessionHasErrors('amount');
    }

    public function test_store_rejects_invalid_type(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('transactions.store'), ['date' => '2026-03-01', 'description' => 'X', 'amount' => 10, 'type' => 'unknown'])
            ->assertSessionHasErrors('type');
    }

    public function test_store_rejects_category_from_another_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $cat = Category::create(['user_id' => $other->id, 'name' => 'Hack', 'color' => '#000', 'keywords' => []]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'date' => '2026-03-01',
                'description' => 'Tentativa',
                'amount' => '10',
                'type' => 'debit',
                'category_id' => $cat->id,
            ])
            ->assertSessionHasErrors('category_id');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_can_delete_their_transaction(): void
    {
        $user = User::factory()->create();
        $import = $this->makeImport($user);
        $tx = Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'date' => '2026-03-01', 'description' => 'To delete', 'amount' => 50, 'type' => 'debit',
        ]);

        $this->actingAs($user)
            ->delete(route('transactions.destroy', $tx))
            ->assertRedirect();

        $this->assertDatabaseMissing('transactions', ['id' => $tx->id]);
    }

    public function test_user_cannot_delete_another_users_transaction(): void
    {
        [$owner, $other] = User::factory(2)->create();
        $import = $this->makeImport($owner);
        $tx = Transaction::create([
            'user_id' => $owner->id, 'raw_import_id' => $import->id,
            'date' => '2026-03-01', 'description' => 'Secret', 'amount' => 999, 'type' => 'credit',
        ]);

        $this->actingAs($other)
            ->delete(route('transactions.destroy', $tx))
            ->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $tx->id]);
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function makeImport(User $user): RawImport
    {
        return RawImport::create([
            'user_id' => $user->id,
            'filename' => 'test.csv',
            'type' => 'csv',
            'path' => 'imports/test.csv',
            'status' => 'done',
        ]);
    }
}
