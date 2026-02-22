<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Admin;
use Hash;
use Firebase\JWT\JWT;
use Validator;
class AdminController extends Controller
{
    public function login(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'correo' => 'required|email',
                'pass' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $admin = Admin::where('correo', $request->correo)->first();

        if (!$admin) {
            return response()->json(['message' => 'Admin no encontrado'], 404);
        }

        if (!Hash::check($request->pass, $admin->pass)) {
            return response()->json(['message' => 'Contraseña incorrecta'], 401);
        }

        $token = JWT::encode(['sub' => $admin->id, 'nombre' => $admin->nombre, 'rol' => $admin->rol, 'exp' => time() + 86400], config('jwt.secret_admin'), 'HS256');

        return response()->json(['message' => 'Admin logueado exitosamente', 'data' => $admin, 'token' => $token]);
    }

    public function crear(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'nombre' => 'required',
                'correo' => 'required|email|unique:admins,correo',
                'pass' => 'required',
                'rol' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $admin = new Admin();
        $admin->nombre = $request->nombre;
        $admin->correo = $request->correo;
        $admin->pass = Hash::make($request->pass);
        $admin->rol = $request->rol;
        $admin->activo = 1;
        $admin->save();

        return response()->json(['message' => 'Admin creado exitosamente', 'data' => $admin]);
    }

    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'id' => 'required',
                'nombre' => 'required',
                'correo' => 'required|email',
                'pass' => 'required',
                'rol' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $admin = Admin::find($request->id);

        if (!$admin) {
            return response()->json(['message' => 'Admin no encontrado'], 404);
        }

        $admin->nombre = $request->nombre;
        $admin->correo = $request->correo;
        $admin->pass = Hash::make($request->pass);
        $admin->rol = $request->rol;
        $admin->save();

        return response()->json(['message' => 'Admin actualizado exitosamente']);
    }

    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'id' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $admin = Admin::find($request->id);

        if (!$admin) {
            return response()->json(['message' => 'Admin no encontrado'], 404);
        }

        $admin->activo = 0;
        $admin->save();

        return response()->json(['message' => 'Admin eliminado exitosamente']);
    }

    public function listar(Request $request)
    {
        $admins = Admin::get();
        return response()->json(['data' => $admins]);
    }

    public function restaurar(Request $request)
    {
        $validate = Validator::make($request->all(),
            [
                'id' => 'required',
            ]
        );

        if($validate->fails()){
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $admin = Admin::find($request->id);

        if (!$admin) {
            return response()->json(['message' => 'Admin no encontrado'], 404);
        }

        $admin->activo = 1;
        $admin->save();

        return response()->json(['message' => 'Admin restaurado exitosamente']);
    }
}
