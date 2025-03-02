<?php

namespace App\Http\Controllers\Api\Package;

use App\Http\Controllers\Controller;
use App\Models\StoragePackage;
use Illuminate\Http\Request;

class StoragePackageController extends Controller
{
    // Lấy danh sách tất cả các gói
    public function index()
    {
        return response()->json(StoragePackage::all(), 200);
    }

    // Tạo mới một gói
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'size' => 'required|integer',
            'price' => 'required|numeric',
            'duration' => 'required|integer',
        ]);

        $package = StoragePackage::create($validated);

        return response()->json($package, 201);
    }

    // Hiển thị chi tiết một gói
    public function show($id)
    {
        $package = StoragePackage::find($id);
    
        if (!$package) {
            return response()->json(['message' => 'Package not found'], 404);
        }
    
        return response()->json($package, 200);
    }
    

    // Cập nhật thông tin gói
    public function update(Request $request, $id)
    {
        $package = StoragePackage::find($id);
    
        if (!$package) {
            return response()->json(['message' => 'Package not found'], 404);
        }
    
        $validated = $request->validate([
            'name' => 'string|max:255',
            'size' => 'integer',
            'price' => 'numeric',
            'duration' => 'integer',
        ]);
    
        $package->update($validated);
    
        return response()->json($package, 200);
    }
    
    


    // Search
    public function search(Request $request)
    {
        $keyword = $request->input('keyword');
    
        $packages = StoragePackage::where('name', 'LIKE', "%$keyword%")->get();
    
        if ($packages->isEmpty()) {
            return response()->json(['message' => 'No packages found'], 404);
        }
    
        return response()->json($packages, 200);
    }
    
    


    // Xóa gói (xóa mềm)
    public function destroy($id)
    {
        $package = StoragePackage::find($id);
    
        if (!$package) {
            return response()->json(['message' => 'Package not found'], 404);
        }
    
        $package->delete(); // Xóa mềm
    
        return response()->json(['message' => 'Package soft-deleted'], 200);
    }
    

    public function forceDelete($id)
    {
        $package = StoragePackage::withTrashed()->find($id);
    
        if (!$package) {
            return response()->json(['message' => 'Package not found'], 404);
        }
    
        $package->forceDelete(); // Xóa cứng
    
        return response()->json(['message' => 'Package permanently deleted'], 200);
    }
    

}