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

    /**
     * Listar todas las membresías de la plataforma para administración.
     * Soporta filtros por rango de fecha, múltiples planes y búsqueda por folio/usuario.
     */
    public function listarAdmin(Request $request)
    {
        $query = Membresia::with(['plan', 'usuario']);

        // Filtro por rango de fecha (inicio_en)
        if ($request->has('fecha_inicio') && !empty($request->fecha_inicio)) {
            $query->whereDate('inicio_en', '>=', $request->fecha_inicio);
        }

        if ($request->has('fecha_fin') && !empty($request->fecha_fin)) {
            $query->whereDate('inicio_en', '<=', $request->fecha_fin);
        }

        // Filtro por múltiples planes
        if ($request->has('planes') && !empty($request->planes)) {
            $planes_ids = is_array($request->planes) ? $request->planes : explode(',', $request->planes);
            $query->whereIn('id_plan', $planes_ids);
        }

        // Búsqueda por folio o nombre de usuario
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('folio', 'LIKE', "%$search%")
                  ->orWhereHas('usuario', function($qu) use ($search) {
                      $qu->where('nombre', 'LIKE', "%$search%")
                         ->orWhere('correo', 'LIKE', "%$search%");
                  });
            });
        }

        // Clonar la query para calcular el ingreso total sin paginación
        $total_ingresos = (float) $query->sum('monto_pagado');

        // Paginación
        $page = $request->input('page', 1);
        $per_page = $request->input('per_page', 20);
        $historial = $query->orderBy('created_at', 'DESC')->paginate($per_page, ['*'], 'page', $page);

        return response()->json([
            'data' => $historial->items(),
            'total' => $historial->total(),
            'current_page' => $historial->currentPage(),
            'last_page' => $historial->lastPage(),
            'total_ingresos' => $total_ingresos
        ], 200);
    }
}
