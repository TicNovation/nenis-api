<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Reporte;
use Validator;

class ReporteController extends Controller
{
    /**
     * Crear un nuevo reporte (Ruta pública accesible por cualquier persona).
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'objetivo_tipo' => 'required|in:usuario,negocio,item,sucursal',
            'id_objetivo' => 'required|integer',
            'motivo' => 'required|string|max:190',
            'descripcion' => 'required|string',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $reporte = Reporte::create([
            'objetivo_tipo' => $request->objetivo_tipo,
            'id_objetivo' => $request->id_objetivo,
            'motivo' => $request->motivo,
            'descripcion' => $request->descripcion,
            'estatus' => 'pendiente' // Por defecto
        ]);

        return response()->json(['message' => 'Reporte enviado exitosamente. Gracias por ayudarnos a mantener la comunidad segura.', 'data' => $reporte], 201);
    }

    /**
     * Listar todos los reportes (Admin solo).
     */
    public function listar(Request $request)
    {
        $query = Reporte::orderBy('created_at', 'DESC');

        // Filtrado opcional por estatus
        if ($request->has('estatus')) {
            $query->where('estatus', $request->query('estatus'));
        }

        $reportes = $query->get();
        return response()->json(['data' => $reportes], 200);
    }

    /**
     * Encontrar el detalle de un reporte por ID (Admin solo).
     */
    public function encontrar(int $id)
    {
        $reporte = Reporte::find($id);

        if (!$reporte) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        return response()->json(['data' => $reporte], 200);
    }

    /**
     * Actualizar el estatus de un reporte (Admin solo).
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'estatus' => 'required|in:pendiente,revision,resuelto,descartado',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $reporte = Reporte::find($request->id);

        if (!$reporte) {
            return response()->json(['message' => 'Reporte no encontrado'], 404);
        }

        $reporte->estatus = $request->estatus;
        $reporte->save();

        return response()->json(['message' => 'Estatus del reporte actualizado exitosamente', 'data' => $reporte], 200);
    }
}
