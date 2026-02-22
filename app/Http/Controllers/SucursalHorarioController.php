<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SucursalHorario;
use App\Models\Sucursal;
use Validator;

class SucursalHorarioController extends Controller
{
    /**
     * Listar horarios de una sucursal específica.
     */
    public function listar(Request $request, int $id_sucursal, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);

        if (!$id_usuario) {
            return response()->json(['message' => 'ID de usuario no identificado'], 400);
        }

        $sucursal = Sucursal::with('negocio')
            ->where('id', $id_sucursal)
            ->first();

        // Validar propiedad de la sucursal (a través de su negocio)
        if (!$sucursal || $sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Sucursal no encontrada o no autorizada'], 404);
        }

        $horarios = SucursalHorario::where('id_sucursal', $id_sucursal)
            ->orderBy('dia_semana', 'ASC')
            ->get();

        return response()->json(['data' => $horarios], 200);
    }

    /**
     * Encontrar horario por ID.
     */
    public function encontrar(Request $request, int $id, ?int $usuario_id = null)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $usuario_id);
        
        $horario = SucursalHorario::where('id', $id)
            ->with('sucursal.negocio')
            ->first();

        if (!$horario || $horario->sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Horario no encontrado o no autorizado'], 404);
        }

        return response()->json(['data' => $horario], 200);
    }

    /**
     * Crear nuevo horario (para un día específico).
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_sucursal' => 'required|integer',
            'dia_semana' => 'required|integer|min:1|max:7', // 1=Lunes, 7=Domingo (ejemplo)
            'hora_apertura' => 'nullable|string|max:8',
            'hora_cierre' => 'nullable|string|max:8',
            'es_cerrado' => 'required|boolean',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        $sucursal = Sucursal::with('negocio')
            ->where('id', $request->id_sucursal)
            ->first();

        if (!$sucursal || $sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Sucursal no encontrada o no autorizada'], 404);
        }

        // Verificar si ya existe horario para este día en la sucursal
        $existe_horario = SucursalHorario::where('id_sucursal', $request->id_sucursal)
            ->where('dia_semana', $request->dia_semana)
            ->first();

        if ($existe_horario) {
            return response()->json(['message' => 'Ya existe un horario configurado para este día en esta sucursal. Utilice actualizar.'], 400);
        }

        $horario = SucursalHorario::create([
            'id_sucursal' => $request->id_sucursal,
            'dia_semana' => $request->dia_semana,
            'hora_apertura' => $request->hora_apertura,
            'hora_cierre' => $request->hora_cierre,
            'es_cerrado' => $request->es_cerrado,
        ]);

        return response()->json(['message' => 'Horario creado exitosamente', 'data' => $horario], 201);
    }

    /**
     * Actualizar horario existente.
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'hora_apertura' => 'nullable|string|max:8',
            'hora_cierre' => 'nullable|string|max:8',
            'es_cerrado' => 'sometimes|boolean',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        $horario = SucursalHorario::where('id', $request->id)
            ->with('sucursal.negocio')
            ->first();

        if (!$horario || $horario->sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Horario no encontrado o no autorizado'], 404);
        }

        if ($request->has('hora_apertura')) {
            $horario->hora_apertura = $request->hora_apertura;
        }

        if ($request->has('hora_cierre')) {
            $horario->hora_cierre = $request->hora_cierre;
        }

        if ($request->has('es_cerrado')) {
            $horario->es_cerrado = $request->es_cerrado;
        }

        $horario->save();

        return response()->json(['message' => 'Horario actualizado exitosamente', 'data' => $horario], 200);
    }

    /**
     * Eliminar horario.
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'id_usuario' => 'sometimes|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $id_usuario_req = $request->input('id_usuario') ?? $request->query('id_usuario');
        $id_usuario = $this->obtenerUsuarioId($request, $id_usuario_req);

        $horario = SucursalHorario::where('id', $request->id)
            ->with('sucursal.negocio')
            ->first();

        if (!$horario || $horario->sucursal->negocio->id_usuario != $id_usuario) {
            return response()->json(['message' => 'Horario no encontrado o no autorizado'], 404);
        }

        $horario->delete();

        return response()->json(['message' => 'Horario eliminado exitosamente'], 200);
    }
}
