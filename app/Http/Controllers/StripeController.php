<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Usuario;
use App\Models\Membresia;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Validator;

class StripeController extends Controller
{

    public function crearSesionPago(Request $request){

        $validator = Validator::make($request->all(), [

            'id_plan' => 'required|exists:planes,id',
            'meses' => 'required|integer|min:1|max:12',
        ]);

        $usuario = $this->obtenerUsuario($request, $request->id_usuario);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // 1. Validar el plan en tu BD
        $plan = Plan::find($request->id_plan);

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        // 2. Configurar Stripe con tu Secret Key
        Stripe::setApiKey(config('services.stripe.secret'));

        // 3. Crear la sesión de Checkout
        $session = Session::create([
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price' => $plan->stripe_precio_id, // El id tipo 'price_123...'
                'quantity' => $request->meses ?? 1,
            ]],
            'mode' => 'payment', // 'subscription' si fuera recurrente
            'success_url' => config('services.stripe.admin_panel_url') . '/success-payment?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => config('services.stripe.admin_panel_url') . '/subscription',
            'metadata' => [
                'usuario_id' => $usuario->id,
                'plan_id' => $plan->id,
                'meses' => $request->meses ?? 1
            ],
        ]);

    return response()->json(['id' => $session->id, 'url' => $session->url]);
}

}
