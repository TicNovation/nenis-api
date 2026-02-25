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
                if ($user->total_items >= $plan->max_items) {
                    return response()->json([
                        'message' => 'Has alcanzado el límite máximo de productos de tu plan (' . $plan->max_items . '). Mejora tu plan para crear más.',
                        'limit_reached' => true,
                        'current' => $user->total_items,
                        'limit' => $plan->max_items
                    ], 403);
                }
                break;
                
            // Se pueden agregar más casos aquí (sucursales, empleos, etc.)
        }

        return $next($request);
    }
}
