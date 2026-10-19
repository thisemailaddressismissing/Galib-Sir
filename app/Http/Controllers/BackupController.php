<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    public function __construct(
        protected BackupService $backupService
    ) {}

    /**
     * Display the backups management page.
     */
    public function index(): View
    {
        $connection = $this->backupService->testConnection();
        $backups = $this->backupService->listBackups();
        $lastBackup = cache()->get('office_cash_last_backup');

        return view('backups.index', compact('connection', 'backups', 'lastBackup'));
    }

    /**
     * Trigger a manual backup and upload to Cloudflare R2.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $result = $this->backupService->createBackup('manual');

            return redirect()
                ->route('backups.index')
                ->with('success', "Full backup '{$result['filename']}' ({$result['size_formatted']}) created and uploaded to Cloudflare R2!");
        } catch (Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Download a specific backup archive from Cloudflare R2.
     */
    public function download(string $filename): StreamedResponse
    {
        return $this->backupService->downloadBackup($filename);
    }

    /**
     * Delete a backup archive from Cloudflare R2.
     */
    public function destroy(string $filename): RedirectResponse
    {
        try {
            $this->backupService->deleteBackup($filename);

            return redirect()
                ->route('backups.index')
                ->with('success', "Backup '{$filename}' was deleted from Cloudflare R2.");
        } catch (Exception $e) {
            return redirect()
                ->route('backups.index')
                ->with('error', 'Failed to delete backup: ' . $e->getMessage());
        }
    }
}
