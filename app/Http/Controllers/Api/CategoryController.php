<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CategoryController extends Controller
{
    // ✅ Listar categorias
    public function index()
    {
        $categories = Category::latest()->get()->map(function ($category) {
            $category->image_url = $category->image_path
                ? asset('storage/' . $category->image_path)
                : null;

            return $category;
        });

        return CategoryResource::collection($categories);
    }

    // ✅ Criar categoria
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        if ($request->hasFile('image')) {
            $validated['image_path'] = $request->file('image')
                ->store('category_images', 'public');
        }

        $category = Category::create($validated);

        return new CategoryResource($category);
    }

    // ✅ Mostrar categoria
    public function show(Category $category)
    {
        $category->image_url = $category->image_path
            ? asset('storage/' . $category->image_path)
            : null;

        return new CategoryResource($category);
    }

    // ✅ Atualizar categoria
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate([

            'name' => 'sometimes|string|max:255|unique:categories,name,' . $category->id,
            'image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048'
        ]);

        if ($request->hasFile('image')) {

            // 🔥 Apaga imagem antiga se existir
            if ($category->image_path) {
                Storage::disk('public')->delete($category->image_path);
            }

            $validated['image_path'] = $request->file('image')
                ->store('category_images', 'public');
        }

        $category->update($validated);

        return new CategoryResource($category);
    }

    // ✅ Deletar categoria
    public function destroy(Category $category)
    {
        if ($category->image_path) {
            Storage::disk('public')->delete($category->image_path);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully'
        ]);
    }
}
