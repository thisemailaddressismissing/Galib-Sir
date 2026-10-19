@extends('layouts.app')

@section('title', 'People')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $people->total() }} people</p>
        <a href="{{ route('people.create') }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Add person</a>
    </div>

    <form method="GET" action="{{ route('people.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap gap-2">
        <input type="text" name="search" value="{{ request('search') }}" placeholder="Search name, phone, email…"
            class="flex-1 min-w-48 px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <select name="type" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All types</option>
            @foreach (['employee' => 'Employee', 'vendor' => 'Vendor', 'client' => 'Client', 'other' => 'Other'] as $value => $label)
                <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Filter</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">Export CSV</a>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'pdf']) }}" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">Export PDF</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2 px-3 font-medium">Name</th>
                    <th class="py-2 px-3 font-medium">Type</th>
                    <th class="py-2 px-3 font-medium">Phone</th>
                    <th class="py-2 px-3 font-medium">Email</th>
                    <th class="py-2 px-3 font-medium">Status</th>
                    <th class="py-2 px-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($people as $person)
                    <tr>
                        <td class="py-2 px-3 font-medium">{{ $person->name }}
                            @if ($person->designation)<span class="block text-xs font-normal text-slate-500">{{ $person->designation }}</span>@endif
                        </td>
                        <td class="py-2 px-3">{{ ucfirst($person->type) }}</td>
                        <td class="py-2 px-3">{{ $person->phone ?? '—' }}</td>
                        <td class="py-2 px-3">{{ $person->email ?? '—' }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $person->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $person->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <a href="{{ route('people.report', array_filter(['person' => $person->id, 'from' => request('from'), 'to' => request('to')])) }}" class="text-slate-600 hover:underline mr-2" title="Download PDF{{ request('from') || request('to') ? ' for current date filter' : '' }}">PDF</a>
                            <button type="button" onclick="openPdfModal({{ $person->id }}, '{{ addslashes($person->name) }}')" class="text-slate-500 hover:text-emerald-700 hover:underline mr-3 text-xs" title="Export PDF with date range">📅 Date range</button>
                            <a href="{{ route('people.edit', $person) }}" class="text-emerald-700 hover:underline mr-3">Edit</a>
                            <form method="POST" action="{{ route('people.destroy', $person) }}" class="inline" onsubmit="return confirm('Delete this person?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-4 px-3 text-slate-500">No people found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $people->links() }}

    <!-- Date Range Export Modal -->
    <div id="pdfModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/50 flex items-center justify-center p-4">
        <div class="bg-white rounded-xl max-w-md w-full p-5 shadow-xl border border-slate-200">
            <div class="flex items-center justify-between pb-3 border-b border-slate-100 mb-4">
                <h3 class="font-semibold text-base text-slate-800" id="modalPersonName">Export PDF Report</h3>
                <button type="button" onclick="closePdfModal()" class="text-slate-400 hover:text-slate-600 text-xl leading-none">&times;</button>
            </div>
            <form id="pdfForm" method="GET" action="" class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">From Date (optional)</label>
                    <input type="date" id="modalFrom" name="from" value="{{ request('from') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-slate-600 mb-1">To Date (optional)</label>
                    <input type="date" id="modalTo" name="to" value="{{ request('to') }}" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                </div>
                <p class="text-xs text-slate-500">Leave dates blank to export the person's complete history.</p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button type="button" onclick="closePdfModal()" class="px-3 py-2 text-sm text-slate-600 hover:text-slate-800">Cancel</button>
                    <button type="button" onclick="downloadAllTime()" class="px-3 py-2 rounded-lg bg-slate-100 text-slate-700 text-sm font-medium hover:bg-slate-200">All-time PDF</button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-700">Download PDF</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const reportBaseUrl = "{{ url('people') }}";
        function openPdfModal(personId, personName) {
            document.getElementById('modalPersonName').innerText = 'Export PDF: ' + personName;
            document.getElementById('pdfForm').action = reportBaseUrl + '/' + personId + '/report';
            document.getElementById('pdfModal').classList.remove('hidden');
        }
        function closePdfModal() {
            document.getElementById('pdfModal').classList.add('hidden');
        }
        function downloadAllTime() {
            document.getElementById('modalFrom').value = '';
            document.getElementById('modalTo').value = '';
            document.getElementById('pdfForm').submit();
            closePdfModal();
        }
    </script>
@endsection
