<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccessLevel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccessLevelController extends Controller
{
    public function index(): JsonResponse
    {
        $accessLevels = AccessLevel::latest()->paginate(10);

        return response()->json($accessLevels);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:access_levels,name'],
        ]);

        $accessLevel = AccessLevel::create($validated);

        return response()->json([
            'message' => 'Nível de acesso criado com sucesso.',
            'data' => $accessLevel,
        ], 201);
    }

    public function show(string $id): JsonResponse
    {
        $accessLevel = AccessLevel::findOrFail($id);

        return response()->json($accessLevel);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        // FIX: era Access_levelResource::findOrFail() — bug crítico (classe errada)
        $accessLevel = AccessLevel::findOrFail($id);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:access_levels,name,' . $id],
        ]);

        $accessLevel->update($validated);

        return response()->json([
            'message' => 'Nível de acesso actualizado com sucesso.',
            'data' => $accessLevel,
        ]);
    }

    public function destroy(string $id): JsonResponse
    {
        $accessLevel = AccessLevel::findOrFail($id);

        // Impede remoção se existirem utilizadores associados
        if ($accessLevel->users()->exists()) {
            return response()->json([
                'message' => 'Não é possível remover um nível com utilizadores associados.',
            ], 409);
        }

        $accessLevel->delete();

        return response()->json([
            'message' => 'Nível de acesso removido com sucesso.',
        ]);
    }
}