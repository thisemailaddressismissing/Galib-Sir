<?php

namespace Tests\Feature;

use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_backups_page_renders_successfully(): void
    {
        Storage::fake('r2');

        $response = $this->get(route('backups.index'));

        $response->assertStatus(200);
        $response->assertSee('Online Database Backups');
        $response->assertSee('Backup Now to Cloud');
    }

    public function test_manual_backup_creation_and_upload(): void
    {
        Storage::fake('r2');
        Storage::fake('public');

        // Create a dummy file in storage/app/public to test attachment archiving
        Storage::disk('public')->put('attachments/bills/voucher.pdf', 'dummy voucher content');

        $response = $this->post(route('backups.store'));

        $response->assertRedirect(route('backups.index'));
        $response->assertSessionHas('success');

        // Verify file uploaded to R2
        $files = Storage::disk('r2')->files(BackupService::BACKUP_DIRECTORY);
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.zip', $files[0]);
    }

    public function test_backup_download_and_deletion(): void
    {
        Storage::fake('r2');

        $filename = 'office-cash-backup-2026-10-17_000000.zip';
        $r2Path = BackupService::BACKUP_DIRECTORY . '/' . $filename;
        Storage::disk('r2')->put($r2Path, 'PK dummy zip content');

        // Download
        $downloadResponse = $this->get(route('backups.download', $filename));
        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString($filename, (string) $downloadResponse->headers->get('content-disposition'));

        // Delete
        $deleteResponse = $this->delete(route('backups.destroy', $filename));
        $deleteResponse->assertRedirect(route('backups.index'));
        $deleteResponse->assertSessionHas('success');

        $this->assertFalse(Storage::disk('r2')->exists($r2Path));
    }

    public function test_backup_artisan_command_executes_successfully(): void
    {
        Storage::fake('r2');
        Storage::fake('public');

        $this->artisan('app:backup-to-r2 --type=test')
            ->expectsOutputToContain('Starting Office Cash Cloudflare R2 backup')
            ->expectsOutputToContain('Backup created and uploaded successfully!')
            ->assertExitCode(0);
    }
}
