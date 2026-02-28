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
                'payment_intent_data' => [
                    'metadata' => [
                        'usuario_id' => $usuario->id,
                        'plan_id' => $plan->id,
                        'meses' => $totalMeses
                    ]
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
        // 🔍 DEBUG: Logueamos el objeto completo para ver su estructura real en producción
        Log::info("---------- STRIPE DEBUG START ({$titulo}) ----------");
        Log::info("Clase del objeto: " . get_class($object));
        Log::info("ID del objeto: " . ($object->id ?? 'N/A'));
        
        // 🏗️ EXTRACCIÓN LIMPIA: El SDK de Stripe tiene objetos con propiedades internas (_values).
        // Usamos toArray() si está disponible o una conversión limpia para evitar basura del SDK.
        $metadata = [];
        if (isset($object->metadata)) {
            $metadata = method_exists($object->metadata, 'toArray') 
                ? $object->metadata->toArray() 
                : json_decode(json_encode($object->metadata), true);
        }

        Log::info("Contenido Metadata Limpio: " . json_encode($metadata));
        
        // 🏗️ EXTRACCIÓN ROBUSTA: Intentamos varios caminos para el id_usuario
        $usuario_id = $metadata['usuario_id'] ?? null;
        
        Log::info("ID Usuario detectado: " . ($usuario_id ?? 'NULL'));
        Log::info("---------- STRIPE DEBUG END ----------");

        // IMPORTANTE: Si id_usuario sigue siendo NULL, la BD va a tronar (SQL 23000).
        // Usaremos el ID 1 (o el que consideres tu Admin/Sistema) como fallback SEGURO.
        $final_id_usuario = $usuario_id ? (int)$usuario_id : 1; 

        SolicitudSoporte::create([
            'id_usuario' => $final_id_usuario,
            'asunto' => "Sistema: " . $titulo,
            'mensaje' => "⚠️ Evento de Stripe detectado: " . $titulo . "\n\n" .
                         "ID Stripe: " . ($object->id ?? 'N/A') . "\n" .
                         "Correo: " . ($object->customer_email ?? $object->receipt_email ?? 'No disponible') . "\n" .
                         "Metadata original: " . json_encode($metadata) . "\n" .
                         "Detalle Error: " . ($object->last_payment_error->message ?? 'Error desconocido o expiración manual de sesión'),
            'estatus' => 'solicitado', // ✅ CORRECCIÓN: 'abierto' no es un valor válido en el ENUM de Nenis
            'activo' => true
        ]);
        
        Log::info("Ticket de soporte creado exitosamente para el usuario ID: " . $final_id_usuario);
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
