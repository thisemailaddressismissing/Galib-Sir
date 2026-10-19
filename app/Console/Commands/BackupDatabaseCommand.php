<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class BackupDatabaseCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:backup-to-r2 {--type=scheduled : The type of backup (manual or scheduled)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup Office Cash SQLite database and uploaded attachments to Cloudflare R2';

    /**
     * Execute the console command.
     */
    public function handle(BackupService $backupService): int
    {
        $this->info('Starting Office Cash Cloudflare R2 backup...');

        $connection = $backupService->testConnection();
        if (! ($connection['connected'] ?? false)) {
            $this->error('Failed to connect to Cloudflare R2: ' . ($connection['message'] ?? 'Unknown error'));
            return self::FAILURE;
        }

        $this->line("Connected to Cloudflare R2 bucket: <comment>{$connection['bucket']}</comment>");

        try {
            $type = (string) $this->option('type');
            $result = $backupService->createBackup($type);

            $this->info('Backup created and uploaded successfully!');
            $this->table(
                ['Property', 'Value'],
                [
                    ['File', $result['filename']],
                    ['R2 Path', $result['path']],
                    ['Archive Size', $result['size_formatted']],
                    ['Database Size', $result['manifest']['database']['size_formatted'] ?? 'N/A'],
                    ['Attachments Count', (string) ($result['manifest']['attachments']['count'] ?? 0)],
                    ['Timestamp', $result['timestamp']],
                ]
            );

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
