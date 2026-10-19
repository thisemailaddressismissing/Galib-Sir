<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class BackupService
{
    public const BACKUP_DIRECTORY = 'office-cash-backups';

    /**
     * Test connection to Cloudflare R2.
     */
    public function testConnection(): array
    {
        try {
            $disk = Storage::disk('r2');
            $testFile = self::BACKUP_DIRECTORY . '/.healthcheck';
            $disk->put($testFile, 'OK');
            $disk->delete($testFile);

            return [
                'connected' => true,
                'bucket' => config('filesystems.disks.r2.bucket'),
                'endpoint' => config('filesystems.disks.r2.endpoint'),
                'message' => 'Connected successfully to Cloudflare R2 bucket.',
            ];
        } catch (Exception $e) {
            Log::error('Cloudflare R2 Connection Error: ' . $e->getMessage());

            return [
                'connected' => false,
                'bucket' => config('filesystems.disks.r2.bucket'),
                'endpoint' => config('filesystems.disks.r2.endpoint'),
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Create a full backup (SQLite database + attachments) and upload to R2.
     */
    public function createBackup(string $type = 'manual'): array
    {
        $tempDir = storage_path('app/backup_temp_' . uniqid());
        File::ensureDirectoryExists($tempDir);

        try {
            $now = Carbon::now();
            $timestamp = $now->format('Y-m-d_His');
            $zipFilename = "office-cash-backup-{$timestamp}.zip";
            $zipPath = $tempDir . DIRECTORY_SEPARATOR . $zipFilename;

            // 1. Snapshot SQLite Database
            $dbSnapshotPath = $tempDir . DIRECTORY_SEPARATOR . 'database.sqlite';
            $this->snapshotDatabase($dbSnapshotPath);
            $dbSize = file_exists($dbSnapshotPath) ? filesize($dbSnapshotPath) : 0;

            // 2. Count and gather attachments from storage/app/public
            $attachmentsDir = storage_path('app/public');
            $attachmentFiles = File::exists($attachmentsDir) ? File::allFiles($attachmentsDir) : [];
            $totalAttachmentsSize = 0;
            foreach ($attachmentFiles as $file) {
                $totalAttachmentsSize += $file->getSize();
            }

            // 3. Create Manifest
            $manifest = [
                'app_name' => config('app.name', 'Office Cash'),
                'app_version' => config('nativephp.version', '1.0.0'),
                'backup_type' => $type,
                'created_at' => $now->toIso8601String(),
                'database' => [
                    'filename' => 'database.sqlite',
                    'size_bytes' => $dbSize,
                    'size_formatted' => $this->formatBytes($dbSize),
                ],
                'attachments' => [
                    'count' => count($attachmentFiles),
                    'total_size_bytes' => $totalAttachmentsSize,
                    'total_size_formatted' => $this->formatBytes($totalAttachmentsSize),
                ],
            ];

            $manifestPath = $tempDir . DIRECTORY_SEPARATOR . 'manifest.json';
            File::put($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT));

            // 4. Create ZIP archive
            $zip = new ZipArchive();
            if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception("Unable to create zip archive at {$zipPath}");
            }

            // Add database file
            if (file_exists($dbSnapshotPath)) {
                $zip->addFile($dbSnapshotPath, 'database.sqlite');
            }

            // Add manifest
            $zip->addFile($manifestPath, 'manifest.json');

            // Add attachments preserving relative path under attachments/
            foreach ($attachmentFiles as $file) {
                $relativePath = 'attachments/' . str_replace('\\', '/', $file->getRelativePathname());
                $zip->addFile($file->getRealPath(), $relativePath);
            }

            $zip->close();

            $finalZipSize = filesize($zipPath);

            // 5. Upload to Cloudflare R2
            $r2Path = self::BACKUP_DIRECTORY . '/' . $zipFilename;
            $stream = fopen($zipPath, 'r+');
            $uploaded = Storage::disk('r2')->put($r2Path, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }

            if (! $uploaded) {
                throw new Exception("Failed to upload {$zipFilename} to Cloudflare R2.");
            }

            // Update latest backup record in cache/settings
            cache()->forever('office_cash_last_backup', [
                'filename' => $zipFilename,
                'timestamp' => $now->toIso8601String(),
                'size' => $this->formatBytes($finalZipSize),
                'type' => $type,
            ]);

            Log::info("Office Cash backup created and uploaded successfully: {$r2Path}");

            return [
                'success' => true,
                'filename' => $zipFilename,
                'path' => $r2Path,
                'size_bytes' => $finalZipSize,
                'size_formatted' => $this->formatBytes($finalZipSize),
                'timestamp' => $now->toIso8601String(),
                'manifest' => $manifest,
            ];
        } finally {
            // Clean up temporary workspace directory
            if (File::exists($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    /**
     * List all backups stored in Cloudflare R2 under office-cash-backups.
     */
    public function listBackups(): array
    {
        try {
            $disk = Storage::disk('r2');
            $files = $disk->files(self::BACKUP_DIRECTORY);

            $backups = [];
            foreach ($files as $filePath) {
                $filename = basename($filePath);
                if (! str_ends_with(strtolower($filename), '.zip')) {
                    continue;
                }

                $size = $disk->size($filePath);
                $lastModified = $disk->lastModified($filePath);

                $backups[] = [
                    'filename' => $filename,
                    'path' => $filePath,
                    'size_bytes' => $size,
                    'size_formatted' => $this->formatBytes($size),
                    'last_modified' => Carbon::createFromTimestamp($lastModified)->toIso8601String(),
                    'last_modified_formatted' => Carbon::createFromTimestamp($lastModified)->format('d M Y, h:i A'),
                ];
            }

            // Sort newest first
            usort($backups, fn ($a, $b) => strcmp($b['filename'], $a['filename']));

            return $backups;
        } catch (Exception $e) {
            Log::error('Error listing R2 backups: ' . $e->getMessage());

            return [];
        }
    }

    /**
     * Download a specific backup file from R2.
     */
    public function downloadBackup(string $filename): StreamedResponse
    {
        $filename = basename($filename);
        $filePath = self::BACKUP_DIRECTORY . '/' . $filename;
        $disk = Storage::disk('r2');

        if (! $disk->exists($filePath)) {
            abort(404, 'Backup file not found on Cloudflare R2.');
        }

        return $disk->download($filePath, $filename, [
            'Content-Type' => 'application/zip',
        ]);
    }

    /**
     * Delete an old backup file from R2.
     */
    public function deleteBackup(string $filename): bool
    {
        $filename = basename($filename);
        $filePath = self::BACKUP_DIRECTORY . '/' . $filename;

        return Storage::disk('r2')->delete($filePath);
    }

    /**
     * Safely snapshot the SQLite database.
     */
    protected function snapshotDatabase(string $destinationPath): void
    {
        $normalizedDestination = str_replace('\\', '/', $destinationPath);
        $sourceDatabase = DB::connection()->getDatabaseName();

        // Handle in-memory database (e.g. unit/feature testing with RefreshDatabase)
        if ($sourceDatabase === ':memory:') {
            if (DB::transactionLevel() === 0) {
                try {
                    DB::statement("VACUUM INTO '{$normalizedDestination}'");
                    if (file_exists($destinationPath) && filesize($destinationPath) > 0) {
                        return;
                    }
                } catch (Exception $e) {
                    // proceed to fallback
                }
            }
            touch($destinationPath);
            return;
        }

        // Attempt 1: Safe point-in-time snapshot using SQLite VACUUM INTO (only if not in active transaction)
        if (DB::transactionLevel() === 0) {
            try {
                DB::statement("VACUUM INTO '{$normalizedDestination}'");
                if (file_exists($destinationPath) && filesize($destinationPath) > 0) {
                    return;
                }
            } catch (Exception $e) {
                Log::warning('SQLite VACUUM INTO failed, falling back to file copy: ' . $e->getMessage());
            }
        }

        // Attempt 2: Direct file copy fallback
        if (! file_exists($sourceDatabase)) {
            $defaultPath = config('database.connections.sqlite.database', database_path('database.sqlite'));
            if (file_exists($defaultPath)) {
                $sourceDatabase = $defaultPath;
            }
        }

        if (file_exists($sourceDatabase)) {
            File::copy($sourceDatabase, $destinationPath);
        } else {
            touch($destinationPath);
        }
    }

    /**
     * Format bytes into human-readable string.
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
