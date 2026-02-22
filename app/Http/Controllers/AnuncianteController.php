<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Anunciante;
use Illuminate\Support\Facades\Validator;

class AnuncianteController extends Controller
{
    /**
     * Listar todos los anunciantes.
     */
    public function listar()
    {
        $anunciantes = Anunciante::all();
        return response()->json(['data' => $anunciantes], 200);
    }

    /**
     * Encontrar un anunciante por ID.
     */
    public function encontrar($id)
    {
        $anunciante = Anunciante::find($id);

        if (!$anunciante) {
            return response()->json(['message' => 'Anunciante no encontrado'], 404);
        }

        return response()->json(['data' => $anunciante], 200);
    }

    /**
     * Crear un nuevo anunciante.
     */
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:190',
            'correo' => 'required|email|unique:anunciantes,correo',
            'telefono' => 'nullable|string|max:30',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $anunciante = Anunciante::create($request->all());

        return response()->json(['data' => $anunciante], 201);
    }

    /**
     * Actualizar un anunciante existente.
     */
    public function actualizar(Request $request, $id)
    {
        $anunciante = Anunciante::find($id);

        if (!$anunciante) {
            return response()->json(['message' => 'Anunciante no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombre' => 'sometimes|string|max:190',
            'correo' => 'sometimes|email|unique:anunciantes,correo,' . $id,
            'telefono' => 'sometimes|nullable|string|max:30',
            'activo' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $anunciante->update($request->all());

        return response()->json(['data' => $anunciante], 200);
    }

    /**
     * Borrar (soft-delete) un anunciante.
     */
    public function borrar($id)
    {
        $anunciante = Anunciante::find($id);

        if (!$anunciante) {
            return response()->json(['message' => 'Anunciante no encontrado'], 404);
        }

        $anunciante->delete();

        return response()->json(['message' => 'Anunciante borrado exitosamente'], 200);
    }
}
