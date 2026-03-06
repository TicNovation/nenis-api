<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Plan;

class CheckPlanLimits
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $feature (negocios, items, empleos)
     * @return mixed
     */
    public function handle(Request $request, Closure $next, $feature)
    {
        $user = $request->attributes->get('user');
        $type = $request->attributes->get('auth_type');

        // Los admins no tienen límites
        if ($type === 'admin') {
            return $next($request);
        }

        if (!$user) {
            return response()->json(['message' => 'Usuario no identificado'], 401);
        }

        $plan = Plan::find($user->id_plan_activo);

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado para el usuario'], 404);
        }

        switch ($feature) {
            case 'negocios':
                if ($user->total_negocios >= $plan->max_negocios) {
                    return response()->json([
                        'message' => 'Has alcanzado el límite máximo de negocios de tu plan (' . $plan->max_negocios . '). Mejora tu plan para crear más.',
                        'limit_reached' => true,
                        'current' => $user->total_negocios,
                        'limit' => $plan->max_negocios
                    ], 403);
                }
                break;

            case 'items':
            case 'sucursales':
            case 'empleos':
            case 'imagenes':
                $id_negocio = $request->input('id_negocio');
                
                if (!$id_negocio) {
                    return response()->json(['message' => 'ID de negocio es requerido para esta validación'], 400);
                }

                $negocio = \App\Models\Negocio::where('id', $id_negocio)
                    ->where('id_usuario', $user->id)
                    ->first();

                if (!$negocio) {
                    return response()->json(['message' => 'Negocio no encontrado o no pertenece a tu cuenta'], 404);
                }

                switch ($feature) {
                    case 'items':
                        if ($negocio->total_items >= $plan->max_items_negocio) {
                            return response()->json([
                                'message' => 'Has alcanzado el límite máximo de productos (' . $plan->max_items_negocio . ') para este negocio. Mejora tu plan para crear más.',
                                'limit_reached' => true,
                                'current' => $negocio->total_items,
                                'limit' => $plan->max_items_negocio
                            ], 403);
                        }
                        break;
                    
                    case 'sucursales':
                        if ($negocio->total_sucursales >= $plan->max_sucursales_negocio) {
                            return response()->json([
                                'message' => 'Has alcanzado el límite máximo de sucursales (' . $plan->max_sucursales_negocio . ') para este negocio. Mejora tu plan para agregar más.',
                                'limit_reached' => true,
                                'current' => $negocio->total_sucursales,
                                'limit' => $plan->max_sucursales_negocio
                            ], 403);
                        }
                        break;

                    case 'empleos':
                        if ($negocio->total_ofertas_empleo >= $plan->max_ofertas_empleo_activas) {
                            return response()->json([
                                'message' => 'Has alcanzado el límite máximo de ofertas de empleo (' . $plan->max_ofertas_empleo_activas . ') para este negocio. Mejora tu plan para agregar más.',
                                'limit_reached' => true,
                                'current' => $negocio->total_ofertas_empleo,
                                'limit' => $plan->max_ofertas_empleo_activas
                            ], 403);
                        }
                        break;

                    case 'imagenes':
                        if ($negocio->total_imagenes >= $plan->max_imagenes_negocio) {
                            return response()->json([
                                'message' => 'Has alcanzado el límite máximo de imágenes (' . $plan->max_imagenes_negocio . ') permitidas para este negocio.',
                                'limit_reached' => true,
                                'current' => $negocio->total_imagenes,
                                'limit' => $plan->max_imagenes_negocio
                            ], 403);
                        }
                        break;
                }
                break;
        }

        return $next($request);
    }
}
