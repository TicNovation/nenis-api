<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\AuditoriaEliminacion;
use Validator;

class AuditoriaEliminacionController extends Controller
{
    /**
     * Registrar una acción de eliminación o intento de eliminación (Usuarios o Admins).
     */
    public function registrar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'tipo_objetivo' => 'required|string|max:50',
            'id_objetivo' => 'required|integer',
            'accion' => 'required|string|max:50', // e.g., 'eliminacion_logica', 'eliminacion_fisica', 'intento_eliminacion'
            'motivo' => 'nullable|string',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $type = $request->get('auth_type');
        $usuario_id = null;
        $admin_id = null;

        if ($type === 'admin') {
            $admin = $request->get('user');
            $admin_id = $admin->id ?? null;
        } else if ($type === 'usuario') {
            $user = $request->get('user');
            $usuario_id = $user->id ?? null;
        }

        $auditoria = AuditoriaEliminacion::create([
            'tipo_objetivo' => $request->tipo_objetivo,
            'id_objetivo' => $request->id_objetivo,
            'accion' => $request->accion,
            'motivo' => $request->motivo,
            'id_usuario_autor' => $usuario_id,
            'id_admin_autor' => $admin_id,
        ]);

        return response()->json(['message' => 'Auditoría registrada exitosamente', 'data' => $auditoria], 201);
    }

    /**
     * Listar todos los registros de auditoría de eliminación (Solo Admin).
     */
    public function listar(Request $request)
    {
        $query = AuditoriaEliminacion::orderBy('created_at', 'DESC');

        if ($request->has('tipo_objetivo')) {
            $query->where('tipo_objetivo', $request->query('tipo_objetivo'));
        }

        if ($request->has('accion')) {
            $query->where('accion', $request->query('accion'));
        }

        $auditorias = $query->get();

        return response()->json(['data' => $auditorias], 200);
    }

    /**
     * Encontrar el detalle de un registro de auditoría (Solo Admin).
     */
    public function encontrar(int $id)
    {
        $auditoria = AuditoriaEliminacion::find($id);

        if (!$auditoria) {
            return response()->json(['message' => 'Registro de auditoría no encontrado'], 404);
        }

        return response()->json(['data' => $auditoria], 200);
    }
}
