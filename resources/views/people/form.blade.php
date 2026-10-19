@extends('layouts.app')

@section('title', $person->exists ? 'Edit person' : 'Add person')

@section('content')
    <form method="POST" action="{{ $person->exists ? route('people.update', $person) : route('people.store') }}"
        class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 max-w-2xl space-y-4">
        @csrf
        @if ($person->exists) @method('PUT') @endif

        <div class="grid sm:grid-cols-2 gap-4">
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="name">Name *</label>
                <input type="text" id="name" name="name" value="{{ old('name', $person->name) }}" required
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="type">Type *</label>
                <select id="type" name="type" required class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
                    @foreach (['employee' => 'Employee', 'vendor' => 'Vendor', 'client' => 'Client', 'other' => 'Other'] as $value => $label)
                        <option value="{{ $value }}" @selected(old('type', $person->type) === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="designation">Designation</label>
                <input type="text" id="designation" name="designation" value="{{ old('designation', $person->designation) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="phone">Phone</label>
                <input type="text" id="phone" name="phone" value="{{ old('phone', $person->phone) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="email">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email', $person->email) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="nid_no">NID no.</label>
                <input type="text" id="nid_no" name="nid_no" value="{{ old('nid_no', $person->nid_no) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div>
                <label class="block text-sm font-medium mb-1" for="address">Address</label>
                <input type="text" id="address" name="address" value="{{ old('address', $person->address) }}"
                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-sm font-medium mb-1" for="notes">Notes</label>
                <textarea id="notes" name="notes" rows="2" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">{{ old('notes', $person->notes) }}</textarea>
            </div>
            <label class="sm:col-span-2 flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $person->exists ? $person->is_active : true)) class="w-4 h-4">
                Active
            </label>
        </div>

        <div class="flex gap-2 pt-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm font-medium hover:bg-emerald-500">
                {{ $person->exists ? 'Update' : 'Save' }}
            </button>
            <a href="{{ route('people.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 text-sm font-medium hover:bg-slate-200">Cancel</a>
        </div>
    </form>
@endsection
