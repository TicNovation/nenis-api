<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MensajeDiario;
use Validator;

class MensajeDiarioController extends Controller
{
    /**
     * Listar todos los mensajes diarios (Admin)
     */
    public function listar()
    {
        $mensajes = MensajeDiario::orderBy('created_at', 'DESC')->get();
        return response()->json(['data' => $mensajes], 200);
    }

    /**
     * Encontrar un mensaje diario por ID (Admin)
     */
    public function encontrar(int $id)
    {
        $mensaje = MensajeDiario::find($id);

        if (!$mensaje) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        return response()->json(['data' => $mensaje], 200);
    }

    /**
     * Crear un nuevo mensaje (Admin)
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'tipo' => 'required|in:motivacional,espiritual',
            'titulo' => 'nullable|string|max:190',
            'contenido' => 'required|string',
            'autor' => 'nullable|string|max:190',
            'activo' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $mensaje = MensajeDiario::create($request->all());

        return response()->json(['message' => 'Mensaje creado exitosamente', 'data' => $mensaje], 201);
    }

    /**
     * Actualizar un mensaje existente (Admin)
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'tipo' => 'required|in:motivacional,espiritual',
            'titulo' => 'nullable|string|max:190',
            'contenido' => 'required|string',
            'autor' => 'nullable|string|max:190',
            'activo' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $mensaje = MensajeDiario::find($request->id);

        if (!$mensaje) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        $mensaje->update($request->all());

        return response()->json(['message' => 'Mensaje actualizado exitosamente', 'data' => $mensaje], 200);
    }

    /**
     * Eliminar (desactivar) un mensaje (Admin)
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $mensaje = MensajeDiario::find($request->id);

        if (!$mensaje) {
            return response()->json(['message' => 'Mensaje no encontrado'], 404);
        }

        $mensaje->activo = 0;
        $mensaje->save();

        return response()->json(['message' => 'Mensaje desactivado exitosamente'], 200);
    }
}
