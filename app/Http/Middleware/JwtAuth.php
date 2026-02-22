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

        $decoded = null;
        $user = null;
        $type = null;

        // Intentar validar como Admin
        try {
            $decoded = JWT::decode($jwt, new Key(config('jwt.secret_admin'), 'HS256'));
            $user = Admin::find($decoded->sub);
            $type = 'admin';
        } catch (SignatureInvalidException $e) {
            try {
                $decoded = JWT::decode($jwt, new Key(config('jwt.secret_usuario'), 'HS256'));
                $user = Usuario::find($decoded->sub);
                $type = 'usuario';
            } catch (SignatureInvalidException $e2) {
                return response()->json(['message' => 'No se pudo comprobar su identidad'], 401);
            } catch (ExpiredException $e2) {
                return response()->json(['message' => 'Su sesión ha expirado'], 401);
            } catch (UnexpectedValueException $e2) {
                return response()->json(['message' => 'Clave de verificación inválida'], 401);
            }
        } catch (ExpiredException $e) {
            return response()->json(['message' => 'Su sesión ha expirado'], 401);
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Clave de verificación inválida'], 401);
        }

        if (!is_object($user)) {
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
