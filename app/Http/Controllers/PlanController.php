<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use Validator;

class PlanController extends Controller
{
    /**
     * Listar todos los planes (ruta compartida)
     */
    public function listar()
    {
        $planes = Plan::all();
        return response()->json(['data' => $planes], 200);
    }

    /**
     * Listar planes activos (específico para usuarios finales)
     */
    public function listarActivos()
    {
        $planes = Plan::where('activo', 1)->with('precios')->orderBy('precio_mensual', 'ASC')->get();
        return response()->json(['data' => $planes], 200);
    }

    /**
     * Encontrar un plan por ID
     */
    public function encontrar(int $id)
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        return response()->json(['data' => $plan], 200);
    }

    /**
     * Crear un nuevo plan (Admin solo)
     */
    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'nombre' => 'required|string|max:50',
            'stripe_precio_id' => 'nullable|string|max:255',
            'precio_mensual' => 'required|numeric|min:0',
            'max_negocios' => 'required|integer|min:1',
            'max_items' => 'required|integer|min:0',
            'max_ofertas_empleo_activas' => 'required|integer|min:0',
            'max_imagenes_item' => 'required|integer|min:0',
            'max_imagenes_negocio' => 'required|integer|min:0',
            'max_alcance_visibilidad' => 'required|in:pais,estado,ciudad',
            'permite_links_items' => 'required|boolean',
            'incluye_analytics' => 'required|boolean',
            'incluye_google_places' => 'required|boolean',
            'prioridad_busqueda' => 'required|integer|min:0',
            'destacado' => 'required|boolean',
            'activo' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $plan = Plan::create($request->all());

        return response()->json(['message' => 'Plan creado exitosamente', 'data' => $plan], 201);
    }

    /**
     * Actualizar un plan existente (Admin solo)
     */
    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'nombre' => 'required|string|max:50',
            'stripe_precio_id' => 'nullable|string|max:255',
            'precio_mensual' => 'required|numeric|min:0',
            'max_negocios' => 'required|integer|min:1',
            'max_items' => 'required|integer|min:0',
            'max_ofertas_empleo_activas' => 'required|integer|min:0',
            'max_imagenes_item' => 'required|integer|min:0',
            'max_imagenes_negocio' => 'required|integer|min:0',
            'max_alcance_visibilidad' => 'required|in:pais,estado,ciudad',
            'permite_links_items' => 'required|boolean',
            'incluye_analytics' => 'required|boolean',
            'incluye_google_places' => 'required|boolean',
            'prioridad_busqueda' => 'required|integer|min:0',
            'destacado' => 'required|boolean',
            'activo' => 'required|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $plan = Plan::find($request->id);

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $plan->update($request->all());

        return response()->json(['message' => 'Plan actualizado exitosamente', 'data' => $plan], 200);
    }

    /**
     * Eliminar (desactivar) un plan (Admin solo)
     */
    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $plan = Plan::find($request->id);

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $plan->activo = 0;
        $plan->save();

        return response()->json(['message' => 'Plan desactivado exitosamente'], 200);
    }

    /**
     * Restaurar (activar) un plan (Admin solo)
     */
    public function restaurar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $plan = Plan::find($request->id);

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        $plan->activo = 1;
        $plan->save();

        return response()->json(['message' => 'Plan activado exitosamente'], 200);
    }
}
