<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Plan;
use App\Models\Usuario;
use App\Models\Membresia;
use Stripe\Stripe;
use Stripe\Checkout\Session;
use Validator;
use Carbon\Carbon;
use App\Models\Negocio;
use App\Models\PlanPrecio;

class StripeController extends Controller
{

    public function crearSesionPago(Request $request){

        $validator = Validator::make($request->all(), [
            'id_plan' => 'required|exists:planes,id',
            'id_plan_precio' => 'nullable|exists:plan_precios,id',
            'meses' => 'required_without:id_plan_precio|integer|min:1|max:48',
        ]);

        $usuario = $this->obtenerUsuario($request, $request->id_usuario);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        // 1. Validar el plan en tu BD
        $plan = Plan::find($request->id_plan);
        $planPrecio = $request->id_plan_precio ? PlanPrecio::find($request->id_plan_precio) : null;

        if (!$plan) {
            return response()->json(['message' => 'Plan no encontrado'], 404);
        }

        // Determinar Price ID y Meses
        $stripePriceId = $planPrecio ? $planPrecio->stripe_price_id : $plan->stripe_precio_id;
        $totalMeses = $planPrecio ? $planPrecio->meses : ($request->meses ?? 1);

        if (!$stripePriceId) {
            return response()->json(['message' => 'Este plan no tiene un ID de precio de Stripe configurado'], 400);
        }
        
        try {
            // 2. Configurar Stripe con tu Secret Key
            Stripe::setApiKey(config('services.stripe.secret'));

            // 3. Crear la sesión de Checkout
            $session = Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price' => $stripePriceId,
                    'quantity' => 1, // La cantidad la define el ID de precio/meses del objeto
                ]],
                'mode' => 'payment',
                'success_url' => config('services.stripe.admin_panel_url') . '/success-payment?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => config('services.stripe.admin_panel_url') . '/subscription',
                'metadata' => [
                    'usuario_id' => $usuario->id,
                    'plan_id' => $plan->id,
                    'meses' => $totalMeses
                ],
            ]);
        } catch (\Throwable $th) {
            //throw $th;
            return response()->json(['message' => $th->getMessage()], 500);
        }

        return response()->json(['id' => $session->id, 'url' => $session->url]);
    }

    public function webhook(Request $request)
    {
        Stripe::setApiKey(config('services.stripe.secret'));
        $endpoint_secret = config('services.stripe.webhook_secret');

        $payload = @file_get_contents('php://input');
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
        } catch (\UnexpectedValueException $e) {
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        if ($event->type == 'checkout.session.completed') {
            $session = $event->data->object;
            $this->procesarPagoExitoso($session);
        }

        return response()->json(['status' => 'success']);
    }

    private function procesarPagoExitoso($session)
    {
        $metadata = $session->metadata;
        $usuario_id = $metadata->usuario_id;
        $plan_id = $metadata->plan_id;
        $meses = $metadata->meses;

        $usuario = Usuario::find($usuario_id);
        $plan = Plan::find($plan_id);

        if ($usuario && $plan) {
            // 1. Cancelar membresías activas anteriores
            Membresia::where('id_usuario', $usuario->id)
                ->where('estatus', 'activo')
                ->update(['estatus' => 'cancelado']);

            // 2. Crear nueva membresía
            $inicio = Carbon::now();
            Membresia::create([
                'id_usuario' => $usuario->id,
                'id_plan' => $plan->id,
                'stripe_pago_id' => $session->payment_intent ?? $session->id,
                'stripe_cliente_id' => $session->customer,
                'meses_comprados' => $meses,
                'monto_pagado' => $session->amount_total / 100,
                'inicio_en' => $inicio,
                'fin_en' => (clone $inicio)->addMonths($meses),
                'estatus' => 'activo',
            ]);

            // 3. Actualizar usuario
            $usuario->id_plan_activo = $plan->id; 
            $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
            $usuario->prioridad_cache = $plan->prioridad_busqueda;
            $usuario->destacado_cache = $plan->destacado;
            $usuario->destacado_titulo_cache = $plan->nombre;
            $usuario->save();

            // 4. Sincronizar negocios
            Negocio::where('id_usuario', $usuario->id)->update([
                'destacado_cache' => $usuario->destacado_cache,
                'destacado_titulo_cache' => $usuario->destacado_titulo_cache,
                'alcance_visibilidad' => $usuario->max_alcance_visibilidad,
                'prioridad_cache' => $usuario->prioridad_cache,
            ]);
        }
    }
}
