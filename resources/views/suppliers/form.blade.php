@extends('layouts.app')

@section('title', $supplier->exists ? 'Edit Supplier' : 'Add Supplier')

@section('content')
    <div class="max-w-2xl">
        <div class="flex items-center justify-between mb-4">
            <h2 class="font-bold text-lg text-slate-800">{{ $supplier->exists ? 'Edit Supplier Details' : 'Add New Supplier / Vendor' }}</h2>
            <a href="{{ route('suppliers.index') }}" class="text-xs text-slate-500 hover:underline">← Back to Suppliers</a>
        </div>

        <form method="POST" action="{{ $supplier->exists ? route('suppliers.update', $supplier) : route('suppliers.store') }}"
            class="bg-white rounded-xl border border-slate-200 p-4 sm:p-6 shadow-sm space-y-4">
            @csrf
            @if ($supplier->exists) @method('PUT') @endif

            <div class="grid sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="name">Supplier / Company Name *</label>
                    <input type="text" id="name" name="name" value="{{ old('name', $supplier->name) }}" required
                        placeholder="e.g. Meghna Paper Mills, Rapid Logistics"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="designation">Contact Person / Dept</label>
                    <input type="text" id="designation" name="designation" value="{{ old('designation', $supplier->designation) }}"
                        placeholder="e.g. Sales Manager, Accounts"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="phone">Phone / Mobile</label>
                    <input type="text" id="phone" name="phone" value="{{ old('phone', $supplier->phone) }}"
                        placeholder="e.g. 017xxxxxxxx"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="email">Email Address</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $supplier->email) }}"
                        placeholder="supplier@example.com"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="nid_no">Trade License / Tax ID</label>
                    <input type="text" id="nid_no" name="nid_no" value="{{ old('nid_no', $supplier->nid_no) }}"
                        placeholder="e.g. BIN / Trade License #"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="address">Office / Warehouse Address</label>
                    <input type="text" id="address" name="address" value="{{ old('address', $supplier->address) }}"
                        placeholder="Street, City, Area"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">
                </div>

                <div class="sm:col-span-2">
                    <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1" for="notes">Notes / Terms</label>
                    <textarea id="notes" name="notes" rows="2" placeholder="Payment terms, bank details, contact notes…"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:outline-none focus:ring-1 focus:ring-emerald-500">{{ old('notes', $supplier->notes) }}</textarea>
                </div>

                <label class="sm:col-span-2 flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $supplier->exists ? $supplier->is_active : true)) class="w-4 h-4 rounded text-emerald-600 focus:ring-emerald-500">
                    <span class="font-medium">Active Supplier</span>
                </label>
            </div>

            <div class="flex items-center gap-2 pt-3 border-t border-slate-100">
                <button type="submit" class="px-5 py-2 rounded-lg bg-emerald-700 hover:bg-emerald-800 text-white text-sm font-medium shadow-sm">
                    {{ $supplier->exists ? 'Update Supplier' : 'Save Supplier' }}
                </button>
                <a href="{{ route('suppliers.index') }}" class="px-4 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-sm font-medium">Cancel</a>
            </div>
        </form>
    </div>
@endsection
