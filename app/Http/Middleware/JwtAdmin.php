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
use Illuminate\Support\Facades\Storage;

class JwtAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */

    public $attributes;

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

        try {
            $decoded = JWT::decode($jwt, new Key(config('jwt.secret_admin'), 'HS256'));

            $admin = Admin::find($decoded->sub);
            if (is_object($admin)) {
                if ($admin->active == 1) {

                } else {
                    return response()->json(['message' => 'El administrador no se encuentra activo'], 401);
                }
            } else {
                return response()->json(['message' => 'No se encontró el administrador'], 401);
            }

        } catch (ExpiredException $e) {
            return response()->json(['message' => 'Su sesión ha expirado'], 401);
        } catch (SignatureInvalidException $e) {
            return response()->json(['message' => 'No se pudo comprobar su identidad'], 401);
        } catch (UnexpectedValueException $e) {
            return response()->json(['message' => 'Clave de verificación inválida'], 401);
        }

        $request->attributes->add(['token' => $decoded]);
        return $next($request);
    }
}
