<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\NegocioCategoria;
use App\Models\Negocio;
use Validator;

class NegocioCategoriaController extends Controller
{
    /**
     * Agregar una categoría secundaria a un negocio.
     */
    public function agregar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_negocio' => 'required|integer',
            'id_categoria' => 'required|integer',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        // Validar propiedad del negocio
        $negocio = Negocio::where('id', $request->input('id_negocio'))
            ->where('id_usuario', $id_usuario)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado o no autorizado'], 404);
        }

        if ($negocio->id_categoria_principal == $request->input('id_categoria')) {
            return response()->json(['message' => 'Esta categoría ya es la categoría principal del negocio'], 400);
        }

        $existe = NegocioCategoria::where('id_negocio', $request->input('id_negocio'))
            ->where('id_categoria', $request->input('id_categoria'))
            ->exists();

        if ($existe) {
            return response()->json(['message' => 'El negocio ya tiene esta categoría asignada'], 400);
        }

        // Usamos insert o attach, o creando un nuevo registro Pivot
        NegocioCategoria::insert([
            'id_negocio' => $request->input('id_negocio'),
            'id_categoria' => $request->input('id_categoria'),
            'created_at' => now(), // Asegurar timestamp si no se llena automáticamente en insert
        ]);

        return response()->json(['message' => 'Categoría agregada exitosamente'], 201);
    }

    /**
     * Eliminar una categoría secundaria de un negocio.
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_negocio' => 'required|integer',
            'id_categoria' => 'required|integer',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        // Validar propiedad del negocio
        $negocio = Negocio::where('id', $request->input('id_negocio'))
            ->where('id_usuario', $id_usuario)
            ->first();

        if (!$negocio) {
            return response()->json(['message' => 'Negocio no encontrado o no autorizado'], 404);
        }

        $borrados = NegocioCategoria::where('id_negocio', $request->input('id_negocio'))
            ->where('id_categoria', $request->input('id_categoria'))
            ->delete();

        if ($borrados == 0) {
            return response()->json(['message' => 'La categoría no estaba asignada a este negocio'], 404);
        }

        return response()->json(['message' => 'Categoría eliminada exitosamente'], 200);
    }
}
