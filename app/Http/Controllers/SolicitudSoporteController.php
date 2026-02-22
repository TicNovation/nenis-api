<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudSoporte;
use Validator;

class SolicitudSoporteController extends Controller
{
    /**
     * Crear una solicitud de soporte (Usuario).
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'asunto' => 'required|string|max:150',
            'mensaje' => 'required|string',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario = $this->obtenerUsuarioId($request, null);

        if (!$id_usuario) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $solicitud = SolicitudSoporte::create([
            'id_usuario' => $id_usuario,
            'asunto' => $request->asunto,
            'mensaje' => $request->mensaje,
            'estatus' => 'solicitado',
            'activo' => 1,
        ]);

        return response()->json(['message' => 'Solicitud de soporte creada exitosamente', 'data' => $solicitud], 201);
    }

    /**
     * Listar las solicitudes de soporte (Admin puede ver todas mediante jwt.admin, 
     * o el usuario puede ver las suyas desde jwt.auth).
     */
    public function listar(Request $request)
    {
        $type = $request->attributes->get('auth_type');
        
        $query = SolicitudSoporte::where('activo', 1)->orderBy('created_at', 'DESC');

        if ($type === 'admin') {
            // El admin puede filtrar por estatus u ordenar, y se traen los usuarios y admin responsable
            $query->with(['usuario', 'admin']);
            if ($request->has('estatus')) {
                $query->where('estatus', $request->query('estatus'));
            }
        } else if ($type === 'usuario') {
            // El usuario solo ve sus propias solicitudes
            $id_usuario = $this->obtenerUsuarioId($request, null);
            $query->where('id_usuario', $id_usuario);
        } else {
            return response()->json(['message' => 'Acceso denegado'], 403);
        }

        $solicitudes = $query->get();

        return response()->json(['data' => $solicitudes], 200);
    }

    /**
     * Encontrar el detalle de una solicitud.
     */
    public function encontrar(Request $request, int $id)
    {
        $type = $request->attributes->get('auth_type');
        $query = SolicitudSoporte::where('id', $id)->where('activo', 1)->with(['usuario', 'admin']);

        // Si es usuario, asegurar que solo pueda ver su propia solicitud
        if ($type === 'usuario') {
            $id_usuario = $this->obtenerUsuarioId($request, null);
            $query->where('id_usuario', $id_usuario);
        }

        $solicitud = $query->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud de soporte no encontrada'], 404);
        }

        return response()->json(['data' => $solicitud], 200);
    }

    /**
     * Actualizar el estatus de la solicitud (Solo Admin).
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'estatus' => 'required|in:solicitado,cancelado,completado',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $solicitud = SolicitudSoporte::find($request->id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud de soporte no encontrada'], 404);
        }

        // Registrar qué administrador atendió/cambió el estatus
        $tipo_usuario = $request->get('auth_type');
        if ($tipo_usuario === 'admin') {
            $admin = $request->get('user');
            $solicitud->id_admin = $admin->id ?? null;
        }

        $solicitud->estatus = $request->estatus;
        $solicitud->save();

        return response()->json(['message' => 'Estatus de la solicitud actualizado correctamente', 'data' => $solicitud], 200);
    }

    /**
     * Cancelar una solicitud por parte del usuario, o desactivarla (Admin).
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $type = $request->get('auth_type');
        $solicitud = SolicitudSoporte::where('id', $request->id)->where('activo', 1)->first();

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud de soporte no encontrada'], 404);
        }

        if ($type === 'usuario') {
            $id_usuario = $this->obtenerUsuarioId($request, null);
            if ($solicitud->id_usuario != $id_usuario) {
                return response()->json(['message' => 'No autorizado'], 403);
            }
            // El usuario solo la cancela
            $solicitud->estatus = 'cancelado';
        } else if ($type === 'admin') {
            // El admin la borra/desactiva totalmente si necesita
            $solicitud->activo = 0;
        }

        $solicitud->save();

        return response()->json(['message' => 'Operación realizada exitosamente'], 200);
    }
}
