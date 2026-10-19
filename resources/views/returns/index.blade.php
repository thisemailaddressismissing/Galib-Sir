@extends('layouts.app')

@section('title', 'Returns')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $returns->total() }} returns</p>
        <a href="{{ route('returns.create') }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Record return</a>
    </div>

    <form method="GET" action="{{ route('returns.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap gap-2">
        <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Filter</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">Export CSV</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2 px-3 font-medium">Returned at</th>
                    <th class="py-2 px-3 font-medium">Advance</th>
                    <th class="py-2 px-3 font-medium">Notes</th>
                    <th class="py-2 px-3 font-medium text-right">Amount</th>
                    <th class="py-2 px-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($returns as $return)
                    <tr>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $return->returned_at->format('d M Y, h:i A') }}</td>
                        <td class="py-2 px-3">
                            <a href="{{ route('advances.show', $return->advance) }}" class="hover:underline">#{{ $return->advance->id }} — {{ $return->advance->person->name ?? '—' }}</a>
                        </td>
                        <td class="py-2 px-3 text-slate-600">{{ $return->notes ?? '—' }}</td>
                        <td class="py-2 px-3 text-right font-semibold whitespace-nowrap">৳{{ number_format($return->amount, 2) }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('returns.destroy', $return) }}" class="inline" onsubmit="return confirm('Delete this return?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 px-3 text-slate-500">No returns found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $returns->links() }}
@endsection
