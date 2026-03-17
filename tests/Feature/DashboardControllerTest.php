<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\RawImport;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_renders_with_empty_data(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->has('summary')
                ->has('spendingByCategory')
                ->has('monthlyTrend')
                ->has('recentTransactions')
                ->where('summary.total_spent', 0)
                ->where('summary.total_income', 0)
                ->where('summary.transactions_count', 0)
            );
    }

    public function test_summary_reflects_current_month_transactions(): void
    {
        $user   = User::factory()->create();
        $import = $this->makeImport($user);

        Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'date' => now()->startOfMonth()->format('Y-m-d'),
            'description' => 'Mercado', 'amount' => 150.00, 'type' => 'debit',
        ]);
        Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'date' => now()->startOfMonth()->addDay()->format('Y-m-d'),
            'description' => 'Salário', 'amount' => 3000.00, 'type' => 'credit',
        ]);
        // Last month — should NOT appear in summary
        Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'date' => now()->subMonth()->format('Y-m-d'),
            'description' => 'Old', 'amount' => 999.00, 'type' => 'debit',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.total_spent', 150)
                ->where('summary.total_income', 3000)
                ->where('summary.balance', 2850)
                ->where('summary.transactions_count', 2)
            );
    }

    public function test_spending_by_category_groups_correctly(): void
    {
        $user   = User::factory()->create();
        $import = $this->makeImport($user);

        $cat = Category::create([
            'user_id'  => $user->id,
            'name'     => 'Alimentação',
            'color'    => '#f97316',
            'keywords' => [],
        ]);

        Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'category_id' => $cat->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'iFood', 'amount' => 45.00, 'type' => 'debit',
        ]);
        Transaction::create([
            'user_id' => $user->id, 'raw_import_id' => $import->id,
            'category_id' => $cat->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Mercado', 'amount' => 120.00, 'type' => 'debit',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->has('spendingByCategory', 1)
                ->where('spendingByCategory.0.name', 'Alimentação')
                ->where('spendingByCategory.0.total', 165)
            );
    }

    public function test_recent_transactions_returns_at_most_ten(): void
    {
        $user   = User::factory()->create();
        $import = $this->makeImport($user);

        for ($i = 0; $i < 15; $i++) {
            Transaction::create([
                'user_id' => $user->id, 'raw_import_id' => $import->id,
                'date' => now()->format('Y-m-d'),
                'description' => "Compra {$i}", 'amount' => 10.00, 'type' => 'debit',
            ]);
        }

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->has('recentTransactions', 10));
    }

    public function test_user_only_sees_their_own_data(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();
        $import = $this->makeImport($other);

        Transaction::create([
            'user_id' => $other->id, 'raw_import_id' => $import->id,
            'date' => now()->format('Y-m-d'),
            'description' => 'Other', 'amount' => 9999.00, 'type' => 'debit',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.total_spent', 0)
                ->has('recentTransactions', 0)
            );
    }

    private function makeImport(User $user): RawImport
    {
        return RawImport::create([
            'user_id'  => $user->id,
            'filename' => 'test.csv',
            'type'     => 'csv',
            'path'     => 'imports/test.csv',
            'status'   => 'done',
        ]);
    }
}
