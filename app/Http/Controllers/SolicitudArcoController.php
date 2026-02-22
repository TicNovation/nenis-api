<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SolicitudArco;
use Validator;

class SolicitudArcoController extends Controller
{
    /**
     * Crear una solicitud ARCO para el usuario autenticado.
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'tipo' => 'required|string|in:acceso,rectificacion,cancelacion,oposicion',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario = $this->obtenerUsuarioId($request, null);

        if (!$id_usuario) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        // Evitar spam de solicitudes (opcional: verificar si tiene una pendiente del mismo tipo)
        $existePendiente = SolicitudArco::where('id_usuario', $id_usuario)
            ->where('tipo', $request->tipo)
            ->where('estatus', 'pendiente')
            ->exists();

        if ($existePendiente) {
            return response()->json(['message' => 'Ya tienes una solicitud de este tipo pendiente de revisión'], 400);
        }

        $solicitud = SolicitudArco::create([
            'id_usuario' => $id_usuario,
            'tipo' => $request->tipo,
            'estatus' => 'pendiente'
        ]);

        return response()->json(['message' => 'Solicitud ARCO creada exitosamente. Te notificaremos sobre su avance.', 'data' => $solicitud], 201);
    }

    /**
     * Listar las solicitudes del usuario (solo sus propias solicitudes).
     */
    public function misSolicitudes(Request $request)
    {
        $id_usuario = $this->obtenerUsuarioId($request, null);

        if (!$id_usuario) {
            return response()->json(['message' => 'Usuario no autenticado'], 401);
        }

        $solicitudes = SolicitudArco::where('id_usuario', $id_usuario)
            ->orderBy('created_at', 'DESC')
            ->get();

        return response()->json(['data' => $solicitudes], 200);
    }

    /**
     * Listar todas las solicitudes (Admin solo).
     */
    public function listar(Request $request)
    {
        $query = SolicitudArco::with('usuario')->orderBy('created_at', 'DESC');

        if ($request->has('estatus')) {
            $query->where('estatus', $request->query('estatus'));
        }

        $solicitudes = $query->get();

        return response()->json(['data' => $solicitudes], 200);
    }

    /**
     * Encontrar el detalle de una solicitud por ID (Admin).
     */
    public function encontrar(int $id)
    {
        $solicitud = SolicitudArco::with('usuario')->find($id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        return response()->json(['data' => $solicitud], 200);
    }

    /**
     * Actualizar el estatus de una solicitud (Admin solo).
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'estatus' => 'required|string|in:pendiente,procesando,completado,rechazado',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $solicitud = SolicitudArco::find($request->id);

        if (!$solicitud) {
            return response()->json(['message' => 'Solicitud no encontrada'], 404);
        }

        $solicitud->estatus = $request->estatus;
        $solicitud->save();

        return response()->json(['message' => 'Estatus de la solicitud actualizado', 'data' => $solicitud], 200);
    }
}
