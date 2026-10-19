@extends('layouts.app')

@section('title', 'Advances')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $advances->total() }} advances</p>
        <a href="{{ route('advances.create') }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Issue advance</a>
    </div>

    <form method="GET" action="{{ route('advances.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap gap-2">
        <select name="status" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All statuses</option>
            @foreach (['pending' => 'Pending', 'partial' => 'Partial', 'settled' => 'Settled', 'overdue' => 'Overdue', 'cancelled' => 'Cancelled'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="person_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
            <option value="">All people</option>
            @foreach ($people as $person)
                <option value="{{ $person->id }}" @selected((string) request('person_id') === (string) $person->id)>{{ $person->name }}</option>
            @endforeach
        </select>
        <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Filter</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">Export CSV</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2 px-3 font-medium">#</th>
                    <th class="py-2 px-3 font-medium">Person</th>
                    <th class="py-2 px-3 font-medium text-right">Amount</th>
                    <th class="py-2 px-3 font-medium text-right">Spent</th>
                    <th class="py-2 px-3 font-medium text-right">Returned</th>
                    <th class="py-2 px-3 font-medium text-right">Balance</th>
                    <th class="py-2 px-3 font-medium">Status</th>
                    <th class="py-2 px-3 font-medium">Due</th>
                    <th class="py-2 px-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($advances as $advance)
                    @php
                        $spent = (float) $advance->expenses->sum('amount');
                        $returned = (float) $advance->returns->sum('amount');
                        $balance = (float) $advance->amount - $spent - $returned;
                    @endphp
                    <tr>
                        <td class="py-2 px-3">{{ $advance->id }}</td>
                        <td class="py-2 px-3 font-medium">
                            <a href="{{ route('advances.show', $advance) }}" class="hover:underline">{{ $advance->person->name ?? '—' }}</a>
                        </td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">৳{{ number_format($advance->amount, 2) }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">৳{{ number_format($spent, 2) }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">৳{{ number_format($returned, 2) }}</td>
                        <td class="py-2 px-3 text-right font-semibold whitespace-nowrap">৳{{ number_format($balance, 2) }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium
                                {{ $advance->status === 'settled' ? 'bg-emerald-100 text-emerald-800' : '' }}
                                {{ $advance->status === 'pending' ? 'bg-amber-100 text-amber-800' : '' }}
                                {{ $advance->status === 'partial' ? 'bg-blue-100 text-blue-800' : '' }}
                                {{ $advance->status === 'overdue' ? 'bg-red-100 text-red-800' : '' }}
                                {{ $advance->status === 'cancelled' ? 'bg-slate-200 text-slate-600' : '' }}">{{ ucfirst($advance->status) }}</span>
                        </td>
                        <td class="py-2 px-3 whitespace-nowrap">{{ $advance->due_date?->format('d M Y') ?? '—' }}</td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <a href="{{ route('advances.show', $advance) }}" class="text-slate-600 hover:underline mr-2">View</a>
                            <a href="{{ route('advances.settle', $advance) }}" class="text-emerald-700 hover:underline mr-2">Settle</a>
                            <a href="{{ route('advances.edit', $advance) }}" class="text-emerald-700 hover:underline mr-2">Edit</a>
                            <form method="POST" action="{{ route('advances.destroy', $advance) }}" class="inline" onsubmit="return confirm('Delete this advance?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-4 px-3 text-slate-500">No advances found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $advances->links() }}
@endsection
