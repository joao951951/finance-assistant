<?php

namespace Tests\Feature;

use App\Jobs\ProcessRawImport;
use App\Models\RawImport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── Auth guard ───────────────────────────────────────────────────────────

    public function test_guest_cannot_access_imports(): void
    {
        $this->get(route('imports.index'))->assertRedirect(route('login'));
    }

    // ─── Index ────────────────────────────────────────────────────────────────

    public function test_user_sees_their_imports(): void
    {
        $user  = User::factory()->create();
        $other = User::factory()->create();

        RawImport::create([
            'user_id'  => $user->id,
            'filename' => 'mine.csv',
            'type'     => 'csv',
            'path'     => 'imports/mine.csv',
            'status'   => 'done',
            'transactions_count' => 5,
        ]);
        RawImport::create([
            'user_id'  => $other->id,
            'filename' => 'theirs.csv',
            'type'     => 'csv',
            'path'     => 'imports/theirs.csv',
            'status'   => 'done',
            'transactions_count' => 3,
        ]);

        $this->actingAs($user)
            ->get(route('imports.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('imports/index')
                ->has('imports', 1)
                ->where('imports.0.filename', 'mine.csv')
            );
    }

    // ─── Store CSV ────────────────────────────────────────────────────────────

    public function test_user_can_upload_csv(): void
    {
        Bus::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->createWithContent(
            'extrato.csv',
            "Data,Descrição,Valor\n2026-03-01,Compra,-50.00"
        );

        $this->actingAs($user)
            ->post(route('imports.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('raw_imports', [
            'user_id'  => $user->id,
            'filename' => 'extrato.csv',
            'type'     => 'csv',
            'status'   => 'pending',
        ]);

        Bus::assertDispatched(ProcessRawImport::class);
    }

    public function test_user_can_upload_pdf(): void
    {
        Bus::fake();
        Storage::fake('local');

        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('extrato.pdf', 100, 'application/pdf');

        $this->actingAs($user)
            ->post(route('imports.store'), ['file' => $file])
            ->assertRedirect();

        $this->assertDatabaseHas('raw_imports', [
            'user_id' => $user->id,
            'type'    => 'pdf',
        ]);
    }

    public function test_upload_validates_file_type(): void
    {
        $user = User::factory()->create();
        $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

        $this->actingAs($user)
            ->post(route('imports.store'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    public function test_upload_validates_max_size(): void
    {
        $user = User::factory()->create();
        // 21 MB — exceeds 20 MB limit
        $file = UploadedFile::fake()->create('big.csv', 21 * 1024, 'text/csv');

        $this->actingAs($user)
            ->post(route('imports.store'), ['file' => $file])
            ->assertSessionHasErrors('file');
    }

    // ─── Destroy ──────────────────────────────────────────────────────────────

    public function test_user_can_delete_their_import(): void
    {
        Storage::fake('local');

        $user   = User::factory()->create();
        $import = RawImport::create([
            'user_id'  => $user->id,
            'filename' => 'old.csv',
            'type'     => 'csv',
            'path'     => 'imports/old.csv',
            'status'   => 'done',
        ]);

        $this->actingAs($user)
            ->delete(route('imports.destroy', $import))
            ->assertRedirect();

        $this->assertDatabaseMissing('raw_imports', ['id' => $import->id]);
    }

    public function test_user_cannot_delete_another_users_import(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $import = RawImport::create([
            'user_id'  => $owner->id,
            'filename' => 'secret.csv',
            'type'     => 'csv',
            'path'     => 'imports/secret.csv',
            'status'   => 'done',
        ]);

        $this->actingAs($other)
            ->delete(route('imports.destroy', $import))
            ->assertForbidden();
    }
}
