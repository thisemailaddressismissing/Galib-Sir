@extends('layouts.app')

@section('title', $category->exists ? 'Edit category' : 'Add category')

@section('content')
    <form method="POST" action="{{ $category->exists ? route('categories.update', $category) : route('categories.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf
        @if ($category->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium mb-1" for="name">Name *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="type">Type *</label>
                <select id="type" name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @foreach (['income' => 'Transfer in', 'expense' => 'Transfer out', 'both' => 'Both'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $category->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="description">Description</label>
                <textarea id="description" name="description" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('description', $category->description) }}</textarea>
            </div>
            <label class="sm:col-span-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $category->exists ? $category->is_active : true)) class="w-4 h-4">
                Active
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                {{ $category->exists ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route('categories.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
