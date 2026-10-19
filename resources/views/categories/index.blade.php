@extends('layouts.app')

@section('title', 'Categories')

@section('content')
    <div class="flex items-center justify-between">
        <p class="text-sm text-slate-500">{{ $categories->total() }} categories</p>
        <a href="{{ route('categories.create') }}" class="px-3 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">+ Add category</a>
    </div>

    <form method="GET" action="{{ route('categories.index') }}" class="bg-white rounded-xl border border-slate-200 p-3 flex flex-wrap gap-2">
        <input type="date" name="from" value="{{ request('from') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <input type="date" name="to" value="{{ request('to') }}" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
        <button type="submit" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Filter</button>
        <a href="{{ request()->fullUrlWithQuery(['export' => 'csv']) }}" class="px-4 py-2 rounded-lg bg-slate-900 text-white text-sm font-medium hover:bg-slate-700">Export CSV</a>
    </form>

    <div class="bg-white rounded-xl border border-slate-200 overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr class="text-left text-slate-500 border-b border-slate-200">
                    <th class="py-2 px-3 font-medium">Name</th>
                    <th class="py-2 px-3 font-medium">Type</th>
                    <th class="py-2 px-3 font-medium">Description</th>
                    <th class="py-2 px-3 font-medium">Status</th>
                    <th class="py-2 px-3 font-medium text-right">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categories as $category)
                    <tr>
                        <td class="py-2 px-3 font-medium">{{ $category->name }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $category->type === 'income' ? 'bg-emerald-100 text-emerald-800' : ($category->type === 'expense' ? 'bg-red-100 text-red-800' : 'bg-slate-200 text-slate-700') }}">{{ $category->typeLabel() }}</span>
                        </td>
                        <td class="py-2 px-3 text-slate-600">{{ $category->description ?? '—' }}</td>
                        <td class="py-2 px-3">
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium {{ $category->is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-600' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                        </td>
                        <td class="py-2 px-3 text-right whitespace-nowrap">
                            <a href="{{ route('categories.edit', $category) }}" class="text-emerald-700 hover:underline mr-3">Edit</a>
                            <form method="POST" action="{{ route('categories.destroy', $category) }}" class="inline" onsubmit="return confirm('Delete this category?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="text-red-600 hover:underline">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-4 px-3 text-slate-500">No categories found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $categories->links() }}
@endsection
