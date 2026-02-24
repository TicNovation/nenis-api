<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlanPrecio;
use Validator;

class PlanPrecioController extends Controller
{
    public function listarPorPlan($id_plan)
    {
        $precios = PlanPrecio::where('id_plan', $id_plan)->get();
        return response()->json(['data' => $precios]);
    }

    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_plan' => 'required|exists:planes,id',
            'meses' => 'required|integer|min:1',
            'precio' => 'required|numeric|min:0',
            'stripe_price_id' => 'nullable|string|unique:plan_precios,stripe_price_id',
            'etiqueta' => 'nullable|string|max:100',
            'ahorro_texto' => 'nullable|string|max:100',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $precio = PlanPrecio::create($request->all());

        return response()->json(['message' => 'Precio creado exitosamente', 'data' => $precio], 201);
    }

    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|exists:plan_precios,id',
            'meses' => 'sometimes|integer|min:1',
            'precio' => 'sometimes|numeric|min:0',
            'stripe_price_id' => 'nullable|string|unique:plan_precios,stripe_price_id,' . $request->id,
            'etiqueta' => 'nullable|string|max:100',
            'ahorro_texto' => 'nullable|string|max:100',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $precio = PlanPrecio::find($request->id);
        $precio->update($request->all());

        return response()->json(['message' => 'Precio actualizado exitosamente', 'data' => $precio]);
    }

    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|exists:plan_precios,id',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        PlanPrecio::destroy($request->id);

        return response()->json(['message' => 'Precio eliminado exitosamente']);
    }
}
