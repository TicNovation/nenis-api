<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\SignatureInvalidException;
use Firebase\JWT\BeforeValidException;
use Firebase\JWT\ExpiredException;
use UnexpectedValueException;
use App\Models\Admin;
use App\Models\Usuario;

class JwtAuth
{
    public function handle($request, Closure $next)
    {
        if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $jwt = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $jwt = $_SERVER['HTTP_AUTHORIZATION'];
        } else {
            $jwt = $request->header('Authorization', null);
        }

        if (!$jwt) {
            return response()->json(['message' => 'No ha iniciado sesión'], 401);
        }

        // Limpiar Bearer con cualquier cantidad de espacios
        $jwt = preg_replace('/^Bearer\s+/i', '', $jwt);
        $jwt = trim($jwt);
        $decoded = null;
        $user = null;
        $type = null;

        // 1. Intentar como Admin
        try {
            $decoded = JWT::decode($jwt, new Key(config('jwt.secret_admin'), 'HS256'));
            $user = Admin::find($decoded->sub);
            if ($user) $type = 'admin';
        } catch (\Exception $e) {
            // No es un token de admin válido o secret distinto
        }

        // 2. Si no es admin, intentar como Usuario
        if (!$type) {
            try {
                $decoded = JWT::decode($jwt, new Key(config('jwt.secret_usuario'), 'HS256'));
                $user = Usuario::find($decoded->sub);
                if ($user) $type = 'usuario';
            } catch (ExpiredException $e) {
                return response()->json(['message' => 'Su sesión ha expirado'], 401);
            } catch (\Exception $e) {
                // Token inválido para ambos
                return response()->json(['message' => 'Sesión inválida o expirada'], 401);
            }
        }

        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 401);
        }

        if ($user->activo != 1) {
            return response()->json(['message' => 'El usuario no se encuentra activo'], 401);
        }

        // Agregamos el usuario y el tipo al request para usarlo en el controlador
        $request->attributes->add(['user' => $user, 'auth_type' => $type, 'token' => $decoded]);

        return $next($request);
    }
}
