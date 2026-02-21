<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;
use App\Models\Usuario;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;

class UsuarioController extends Controller
{
    public function crear(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'correo' => 'required|email|unique:usuarios,correo',
            'telefono' => 'required|string|max:20',
            'pass' => 'required|string|min:6',
            'id_plan_activo' => 'nullable|integer|exists:planes,id',
            'activo' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $data = $request->all();
        if (isset($data['pass'])) {
            $data['pass'] = Hash::make($data['pass']);
        }

        $plan = Plan::find($request->id_plan_activo);

        $usuario = new Usuario();
        $usuario->correo = $request->correo;
        $usuario->telefono = $request->telefono;
        $usuario->pass = $request->pass;
        $usuario->id_plan_activo = $request->id_plan_activo;
        $usuario->activo = $request->activo;

        //Configuraciones de plan
        $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
        $usuario->total_negocios = 0;
        $usuario->total_items = 0;
        $usuario->priodidad_cache = $plan->prioridad_busqueda;

        $usuario->save();

        return response()->json($usuario, 201);
    }

    public function actualizar(Request $request, $id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'correo' => 'sometimes|email|unique:usuarios,correo,' . $id,
            'telefono' => 'sometimes|string|max:20',
            'pass' => 'sometimes|string|min:6',
            'id_plan_activo' => 'sometimes|nullable|integer|exists:planes,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $usuario = Usuario::find($id);
        $usuario->correo = $request->correo;
        $usuario->telefono = $request->telefono;
        $usuario->pass = isset($request->pass) ? Hash::make($request->pass) : $usuario->pass;

        if (isset($request->id_plan_activo) && $usuario->id_plan_activo != $request->id_plan_activo) {
            $plan = Plan::find($request->id_plan_activo);
            $usuario->id_plan_activo = $request->id_plan_activo;
            $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
            $usuario->priodidad_cache = $plan->prioridad_busqueda;
        }

        $usuario->save();

        return response()->json($usuario, 200);
    }

    public function borrar($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $usuario->delete();

        return response()->json(['message' => 'Usuario borrado exitosamente'], 200);
    }


    public function listar()
    {
        $usuarios = Usuario::all();
        return response()->json($usuarios, 200);
    }

    public function encontrar($id)
    {
        $usuario = Usuario::find($id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json($usuario, 200);
    }

}
