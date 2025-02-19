<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Color;
use Illuminate\Support\Facades\Log;

class ColorController extends Controller
{
    public function index()
    {
        try {
            $colors = Color::withTrashed()->get();
            return view('admin.colors.index', compact('colors'));
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while retrieving colors');
        }
    }

    public function create()
    {
        return view('admin.colors.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:colors,name',
            'code' => 'required|string|size:7|unique:colors,code',
        ]);

        try {
            Color::create($validated);
            session()->flash('success', 'Color added successfully!');
            return redirect()->route('colors.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while creating color');
        }
    }


    public function show($id)
    {
        $color = Color::findOrFail($id);
        return view('admin.colors.show', compact('color'));
    }

    public function edit($id)
    {
        $color = Color::findOrFail($id);
        return view('admin.colors.edit', compact('color'));
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'name' => 'required|string|unique:colors,name,' . $id,
            'code' => 'required|string|size:7|unique:colors,code,' . $id,
        ]);

        try {
            $color = Color::withTrashed()->findOrFail($id);
            $color->update($validated);
            session()->flash('success', 'Color updated successfully!');
            return redirect()->route('colors.index');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while updating color');
        }
    }


    public function destroy($id)
    {
        try {
            $color = Color::findOrFail($id);
            $color->delete(); 
            return redirect()->route('admin.colors.index')->with('success', 'Color deleted successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while deleting color');
        }
    }

    public function restore($id)
    {
        try {
            $color = Color::onlyTrashed()->findOrFail($id);
            $color->restore(); 
            return redirect()->route('admin.colors.index')->with('success', 'Color restored successfully');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while restoring color');
        }
    }

    public function forceDelete($id)
    {
        try {
            $color = Color::withTrashed()->findOrFail($id);
            $color->forceDelete(); 
            return redirect()->route('colors.index')->with('success', 'Color permanently deleted');
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred while permanently deleting color');
        }
    }
}
