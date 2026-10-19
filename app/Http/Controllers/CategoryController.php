<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Support\CsvExport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class CategoryController extends Controller
{
    public function index(Request $request): View|Response
    {
        $query = Category::query();
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->get('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->get('to'));
        }

        if ($request->get('export') === 'csv') {
            $rows = (clone $query)->latest()->get()->map(fn ($c) => [
                $c->name,
                $c->typeLabel(),
                $c->description ?? '',
                $c->is_active ? 'Active' : 'Inactive',
                $c->created_at->format('Y-m-d'),
            ]);

            return CsvExport::response(
                'categories-'.now()->format('Ymd-His').'.csv',
                ['Name', 'Type', 'Description', 'Status', 'Created'],
                $rows
            );
        }

        $categories = $query->latest()->paginate(20)->withQueryString();

        return view('categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('categories.form', ['category' => new Category()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,both',
            'description' => 'nullable|string',
        ]);
        $data['is_active'] = $request->boolean('is_active', true);
        Category::create($data);

        return redirect()->route('categories.index')->with('success', 'Category created.');
    }

    public function edit(Category $category): View
    {
        return view('categories.form', compact('category'));
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:income,expense,both',
            'description' => 'nullable|string',
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $category->update($data);

        return redirect()->route('categories.index')->with('success', 'Category updated.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()->route('categories.index')->with('success', 'Category deleted.');
    }
}
