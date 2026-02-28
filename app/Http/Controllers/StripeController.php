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
use Illuminate\Support\Facades\Log;
use App\Models\SolicitudSoporte;

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
                'cancel_url' => config('services.stripe.admin_panel_url') . '/dashboard/subscription?error=payment_cancelled',
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

        $payload = $request->getContent();
        $sig_header = $request->header('Stripe-Signature');
        $event = null;

        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sig_header, $endpoint_secret
            );
            Log::info("Stripe Webhook Recibido: " . $event->type);
        } catch (\UnexpectedValueException $e) {
            Log::error("Stripe Webhook Error: Invalid Payload");
            return response()->json(['message' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            Log::error("Stripe Webhook Error: Invalid Signature");
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                Log::info("Checkout Session Completed. ID: " . $session->id);
                $this->procesarPagoExitoso($session);
                break;

            case 'payment_intent.payment_failed':
                $paymentIntent = $event->data->object;
                Log::warning("Pago Fallido detectado. ID: " . $paymentIntent->id);
                $this->crearTicketPorFallo($paymentIntent, "Pago Fallido");
                break;

            case 'checkout.session.expired':
                $session = $event->data->object;
                Log::info("Sesión de Checkout expirada. ID: " . $session->id);
                $this->crearTicketPorFallo($session, "Sesión de Pago Expirada");
                break;
        }

        return response()->json(['status' => 'success']);
    }

    private function crearTicketPorFallo($object, $titulo)
    {
        // En Stripe, la metadata puede venir como objeto o array dependiendo del contexto del SDK
        $metadata = $object->metadata ?? [];
        
        // Intentar extraer el ID de usuario (asegurarnos que sea el valor plano)
        $usuario_id = null;
        if (is_array($metadata)) {
            $usuario_id = $metadata['usuario_id'] ?? null;
        } elseif (is_object($metadata)) {
            $usuario_id = $metadata->usuario_id ?? null;
        }

        // Si por alguna razón sigue siendo null (ej: evento sin metadata), 
        // lo registramos como null en la BD si la columna lo permite, o ponemos un valor por defecto si es requerido.
        // Dado el error SQLSTATE[23000], la columna id_usuario NO permite nulls. 
        // Usaremos 0 o un ID de sistema si no viene, pero lo ideal es que siempre venga.
        
        SolicitudSoporte::create([
            'id_usuario' => $usuario_id, // Si esto falla, es que el evento no traía metadata
            'asunto' => "Sistema: " . $titulo,
            'mensaje' => "Se ha detectado un evento de Stripe ($titulo). \n\n" .
                         "ID Stripe: " . ($object->id ?? 'N/A') . "\n" .
                         "Correo Cliente: " . ($object->customer_email ?? $object->receipt_email ?? 'No disponible') . "\n" .
                         "Detalle: " . ($object->last_payment_error->message ?? 'Cierre de sesión o error de red'),
            'estatus' => 'abierto',
            'activo' => true
        ]);
        
        Log::info("Ticket de soporte automático creado por fallo en Stripe: " . $titulo . " para usuario: " . ($usuario_id ?? 'Desconocido'));
    }

    private function procesarPagoExitoso($session)
    {
        $metadata = $session->metadata;
        Log::info("Procesando pago exitoso. Metadata: " . json_encode($metadata));

        $usuario_id = isset($metadata->usuario_id) ? (int)$metadata->usuario_id : null;
        $plan_id = isset($metadata->plan_id) ? (int)$metadata->plan_id : null;
        $meses = isset($metadata->meses) ? (int)$metadata->meses : 0;

        Log::info("Datos extraídos (casteados) - Usuario: $usuario_id, Plan: $plan_id, Meses: $meses");

        $usuario = Usuario::find($usuario_id);
        $plan = Plan::find($plan_id);

        if ($usuario && $plan) {
            Log::info("Usuario ({$usuario->nombre}) y Plan ({$plan->nombre}) encontrados. Actualizando...");
            
            // 1. Verificar si tiene una membresía activa para sumar días si es el mismo plan
            $membresiaActual = Membresia::where('id_usuario', $usuario->id)
                ->where('estatus', 'activo')
                ->where('fin_en', '>', Carbon::now())
                ->first();

            $inicio = Carbon::now();
            $fin = (clone $inicio)->addMonths($meses);

            // Si es renovación del mismo plan, sumamos los meses a la fecha de fin actual
            if ($membresiaActual && $membresiaActual->id_plan == $plan->id) {
                Log::info("Es renovación del mismo plan. Sumando al periodo actual que vence en: " . $membresiaActual->fin_en);
                $fin = Carbon::parse($membresiaActual->fin_en)->addMonths($meses);
            }

            // Cancelar membresías activas anteriores (incluyendo la actual si existía)
            Membresia::where('id_usuario', $usuario->id)
                ->where('estatus', 'activo')
                ->update(['estatus' => 'cancelado']);

            // 2. Crear nueva membresía
            $nuevaMembresia = Membresia::create([
                'id_usuario' => $usuario->id,
                'id_plan' => $plan->id,
                'stripe_pago_id' => $session->payment_intent ?? $session->id,
                'stripe_cliente_id' => $session->customer,
                'meses_comprados' => $meses,
                'monto_pagado' => $session->amount_total / 100,
                'inicio_en' => $inicio,
                'fin_en' => $fin,
                'estatus' => 'activo',
            ]);

            Log::info("Nueva membresía creada ID: " . $nuevaMembresia->id . " vence en: " . $fin);

            // 3. Actualizar usuario
            $usuario->id_plan_activo = $plan->id; 
            $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
            $usuario->prioridad_cache = $plan->prioridad_busqueda;
            $usuario->destacado_cache = $plan->destacado;
            $usuario->destacado_titulo_cache = $plan->nombre;
            $usuario->save();

            Log::info("Usuario actualizado a plan: " . $plan->nombre);

            // 4. Sincronizar negocios
            Negocio::where('id_usuario', $usuario->id)->update([
                'destacado_cache' => $usuario->destacado_cache,
                'destacado_titulo_cache' => $usuario->destacado_titulo_cache,
                'alcance_visibilidad' => $usuario->max_alcance_visibilidad,
                'prioridad_cache' => $usuario->prioridad_cache,
            ]);
            Log::info("Negocios sincronizados.");
        } else {
            Log::warning("No se pudo procesar el pago: Usuario o Plan no encontrados. Metadata: " . json_encode($metadata));
        }
    }
}
