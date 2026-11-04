<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePublisherRequest;
use App\Http\Requests\UpdatePublisherRequest;
use App\Models\Publisher;
use Illuminate\Http\Request;

class AdminPublisherController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $publishers = Publisher::withCount('books')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%");
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('admin.publishers.index', compact('publishers', 'search'));
    }

    public function create()
    {
        return view('admin.publishers.create');
    }

    public function store(StorePublisherRequest $request)
    {
        $publisher = Publisher::create($request->validated());

        if ($request->expectsJson()) {
            return response()->json(['id' => $publisher->id, 'name' => $publisher->name], 201);
        }

        return redirect()->route('admin.publishers.index')->with('success', 'Publisher created successfully.');
    }

    public function show(Publisher $publisher)
    {
        $publisher->load('books');

        return view('admin.publishers.show', compact('publisher'));
    }

    public function edit(Publisher $publisher)
    {
        return view('admin.publishers.edit', compact('publisher'));
    }

    public function update(UpdatePublisherRequest $request, Publisher $publisher)
    {
        $publisher->update($request->validated());

        return redirect()->route('admin.publishers.show', $publisher)->with('success', 'Publisher updated successfully.');
    }

    public function destroy(Publisher $publisher)
    {
        if ($publisher->books()->exists()) {
            return redirect()->route('admin.publishers.index')->with('error', 'Cannot delete this publisher because books are associated with this publisher.');
        }

        $publisher->delete();

        return redirect()->route('admin.publishers.index')->with('success', 'Publisher deleted successfully.');
    }
}
