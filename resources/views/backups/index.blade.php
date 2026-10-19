@extends('layouts.app')

@section('title', 'Database & Cloud Backups')

@section('content')
<div class="space-y-6">

    <!-- Header & Action -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-slate-900">Online Database Backups</h2>
            <p class="text-sm text-slate-500">Secure automated and manual full snapshots to Cloudflare R2 storage</p>
        </div>
        <form action="{{ route('backups.store') }}" method="POST" onsubmit="this.querySelector('button').disabled=true; this.querySelector('button').innerText='Creating & Uploading...';">
            @csrf
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-sm shadow-sm transition">
                <span>☁️</span>
                <span>Backup Now to Cloud</span>
            </button>
        </form>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- R2 Status -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-sm flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl {{ ($connection['connected'] ?? false) ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600' }} flex items-center justify-center font-bold text-lg">
                {{ ($connection['connected'] ?? false) ? '✓' : '✗' }}
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Cloudflare R2</p>
                <p class="font-bold text-slate-900 text-base truncate">
                    {{ ($connection['connected'] ?? false) ? 'Connected' : 'Disconnected' }}
                </p>
                <p class="text-xs text-slate-400 truncate">{{ $connection['bucket'] ?? 'N/A' }}</p>
            </div>
        </div>

        <!-- Total Backups -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-sm flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center font-bold text-lg">
                📦
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Cloud Backups</p>
                <p class="font-bold text-slate-900 text-base">{{ count($backups) }} Available</p>
                <p class="text-xs text-slate-400">Timestamped snapshots</p>
            </div>
        </div>

        <!-- Latest Backup -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-sm flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center font-bold text-lg">
                🕒
            </div>
            <div class="min-w-0">
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Last Backup</p>
                @if (!empty($backups))
                    <p class="font-bold text-slate-900 text-sm truncate">{{ $backups[0]['last_modified_formatted'] }}</p>
                    <p class="text-xs text-slate-400">{{ $backups[0]['size_formatted'] }}</p>
                @else
                    <p class="font-bold text-slate-900 text-base">Never</p>
                    <p class="text-xs text-slate-400">No backups yet</p>
                @endif
            </div>
        </div>

        <!-- Schedule -->
        <div class="p-4 rounded-2xl bg-white border border-slate-200 shadow-sm flex items-start gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center font-bold text-lg">
                ⚡
            </div>
            <div>
                <p class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Auto Schedule</p>
                <p class="font-bold text-slate-900 text-base">Daily at 02:00 AM</p>
                <p class="text-xs text-emerald-600 font-medium">Automatic active</p>
            </div>
        </div>
    </div>

    <!-- Backup Details Banner -->
    <div class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4 sm:p-5 flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <div class="space-y-1">
            <p class="font-bold text-blue-950 flex items-center gap-2">
                <span>🛡️</span> Complete Data Protection Included
            </p>
            <p class="text-xs sm:text-sm text-blue-800">
                Each snapshot creates a pristine point-in-time copy of the <strong>SQLite database</strong>, all uploaded <strong>voucher attachments and receipts</strong>, and a verification <strong>manifest</strong>.
            </p>
        </div>
        <div class="text-xs bg-white text-blue-900 px-3 py-1.5 rounded-lg border border-blue-200 font-mono shrink-0">
            Path: office-cash-backups/
        </div>
    </div>

    <!-- Backups Table -->
    <div class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
            <h3 class="font-bold text-slate-800 text-base">Backup History on Cloudflare R2</h3>
            <span class="text-xs text-slate-500">{{ count($backups) }} archives stored</span>
        </div>

        @if (empty($backups))
            <div class="p-12 text-center text-slate-400">
                <p class="text-4xl mb-3">☁️</p>
                <p class="font-medium text-slate-600">No backups stored in Cloudflare R2 yet</p>
                <p class="text-xs text-slate-400 mt-1">Click the "Backup Now to Cloud" button above to create your first cloud snapshot.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-sm">
                    <thead>
                        <tr class="bg-slate-50/80 border-b border-slate-200/80 text-xs text-slate-500 uppercase tracking-wider">
                            <th class="px-5 py-3 font-semibold">Backup File</th>
                            <th class="px-4 py-3 font-semibold">Size</th>
                            <th class="px-4 py-3 font-semibold">Created / Uploaded</th>
                            <th class="px-5 py-3 font-semibold text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($backups as $backup)
                            <tr class="hover:bg-slate-50/60 transition">
                                <td class="px-5 py-3.5">
                                    <div class="flex items-center gap-2.5">
                                        <span class="text-slate-400">📦</span>
                                        <div>
                                            <p class="font-semibold text-slate-800 font-mono text-xs sm:text-sm">{{ $backup['filename'] }}</p>
                                            <p class="text-xs text-slate-400">Full archive (SQLite + Attachments)</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3.5 font-medium text-slate-700 whitespace-nowrap">
                                    {{ $backup['size_formatted'] }}
                                </td>
                                <td class="px-4 py-3.5 text-slate-600 whitespace-nowrap text-xs sm:text-sm">
                                    {{ $backup['last_modified_formatted'] }}
                                </td>
                                <td class="px-5 py-3.5 text-right whitespace-nowrap space-x-2">
                                    <a href="{{ route('backups.download', $backup['filename']) }}" 
                                       class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-semibold transition"
                                       title="Download backup archive">
                                        <span>⬇️</span> Download
                                    </a>
                                    <form action="{{ route('backups.destroy', $backup['filename']) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this backup archive from Cloudflare R2?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" 
                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-semibold transition"
                                                title="Delete from R2">
                                            <span>🗑️</span> Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</div>
@endsection
