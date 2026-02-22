<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Membresia;
use App\Models\Usuario;

class MembresiaController extends Controller
{
    /**
     * Listar el historial de membresías de un usuario.
     */
    public function listar(Request $request, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        if (!$id_usuario) {
            return response()->json(['message' => 'ID de usuario no identificado'], 400);
        }

        $historial = Membresia::where('id_usuario', $id_usuario)
            ->with('plan')
            ->orderBy('created_at', 'DESC')
            ->get();

        return response()->json(['data' => $historial], 200);
    }

    /**
     * Obtener el detalle de una membresía específica.
     */
    public function encontrar(Request $request, int $id, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        if (!$id_usuario) {
            return response()->json(['message' => 'ID de usuario no identificado'], 400);
        }

        $membresia = Membresia::where('id', $id)
            ->where('id_usuario', $id_usuario)
            ->with('plan')
            ->first();

        if (!$membresia) {
            return response()->json(['message' => 'Registro de membresía no encontrado'], 404);
        }

        return response()->json(['data' => $membresia], 200);
    }
}
