<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\Membresia;
use Illuminate\Http\Request;
use App\Models\Usuario;
use App\Models\Negocio;
use App\Models\Estado;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Models\MensajeDiario;
use App\Mail\RecuperarPassword;
use Illuminate\Support\Facades\Mail;
use App\Mail\VerificacionEmail;


class UsuarioController extends Controller
{
    /**
     * Helper para registrar el historial de membresías
     */
    private function registrarMembresia($usuario, $id_plan, $meses = 1, $monto = 0, $stripe_pago_id = 'SISTEMA', $stripe_cliente_id = null, $fecha_inicio = null)
    {
        $inicio = $fecha_inicio ? Carbon::parse($fecha_inicio) : Carbon::now();
        
        Membresia::create([
            'id_usuario' => $usuario->id,
            'id_plan' => $id_plan,
            'stripe_pago_id' => $stripe_pago_id,
            'stripe_cliente_id' => $stripe_cliente_id,
            'meses_comprados' => $meses,
            'monto_pagado' => $monto,
            'inicio_en' => $inicio,
            'fin_en' => (clone $inicio)->addMonths($meses),
            'estatus' => 'activo',
        ]);
    }

    /**
     * Login de usuarios (Emprendedores)
     */
    public function login(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'correo' => 'required|email',
            'pass' => 'required|string',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->first();

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if (!Hash::check($request->pass, $usuario->pass)) {
            // Revisar si coincide con la contraseña temporal
            if (!$usuario->temp_pass || !Hash::check($request->pass, $usuario->temp_pass)) {
                return response()->json(['message' => 'Contraseña incorrecta'], 401);
            }
        }

        if (!$usuario->correo_verificado) {
            return response()->json(['message' => 'Debes verificar tu correo electrónico antes de iniciar sesión.'], 403);
        }

        if (!$usuario->activo) {
            return response()->json(['message' => 'Cuenta desactivada. Contacta al soporte.'], 403);
        }

        // Generar JWT
        $token = JWT::encode([
            'sub' => $usuario->id,
            'correo' => $usuario->correo,
            'exp' => time() + 86400
        ], config('jwt.secret_usuario'), 'HS256');

        // Obtener los últimos 20 mensajes diarios activos
        $mensajes = MensajeDiario::where('activo', 1)->inRandomOrder()->limit(20)->get();
        $estados = Estado::with('ciudades')->get();

        $usuario->load(['planActivo', 'membresias']);
        $usuario->loadCount(['negocios', 'items']);

        return response()->json([
            'message' => 'Usuario logueado exitosamente',
            'data' => $usuario,
            'token' => $token,
            'estados' => $estados,
            'mensajes' => $mensajes
        ]);
    }

    /**
     * Registro público de nuevos usuarios (Emprendedores)
     */
    public function registro(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'telefono' => 'required|string|max:20',
            'pass' => 'required|string|min:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        // Asignar Plan 1 por defecto (Básico)
        $id_plan = 1;
        $plan = Plan::find($id_plan);

        if (!$plan) {
            return response()->json(['message' => 'Error de configuración del sistema: Plan base no encontrado'], 500);
        }

        $usuario = new Usuario();
        $usuario->nombre = $request->nombre;
        $usuario->correo = $request->correo;
        $usuario->telefono = $request->telefono;
        $usuario->pass = Hash::make($request->pass);
        $usuario->id_plan_activo = $id_plan;
        $usuario->correo_verificado = 0;
        
        // Heredar configuración del plan
        $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
        $usuario->prioridad_cache = $plan->prioridad_busqueda;
        $usuario->destacado_cache = $plan->destacado;
        $usuario->destacado_titulo_cache = $plan->nombre;
        
        $usuario->total_negocios = 0;
        $usuario->total_items = 0;
        $usuario->activo = 0; // Desactivado hasta que verifique correo
        $usuario->save();

        // Registrar historial de membresía
        $this->registrarMembresia($usuario, $id_plan, 12, 0, 'REGISTRO_GRATIS');

        // Enviar correo de verificación
        $token_verificacion = JWT::encode([
            'sub' => $usuario->id,
            'correo' => $usuario->correo,
            'exp' => time() + 86400 * 3
        ], config('jwt.secret_usuario'), 'HS256');

        $url = "https://admin.nenis.com.mx/verificarcorreo?token=" . $token_verificacion;//PROD
        $url = "http://localhost:8080/verificarcorreo?token=" . $token_verificacion;//DEV
        Mail::to($usuario->correo)->send(new VerificacionEmail([
            'nombre' => $usuario->nombre,
            'url' => $url
        ]));


        return response()->json([
            'message' => 'Usuario registrado exitosamente',
            'data' => $usuario,
        ], 201);
    }

    /**
     * Creación de usuario por parte del Administrador
     */
    public function crear(Request $request)
    {
        $token = $request->get('token');

        $validator = Validator::make($request->all(), [
            'nombre' => 'required|string|max:255',
            'correo' => 'required|email|unique:usuarios,correo',
            'telefono' => 'required|string|max:20',
            'pass' => 'required|string|min:6',
            'id_plan_activo' => 'required|integer|exists:planes,id',
            'meses' => 'required|integer|min:1',
            'monto' => 'required|numeric',
            'activo' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['message' => $validator->errors()->first()], 400);
        }

        $plan = Plan::find($request->id_plan_activo);

        $usuario = new Usuario();
        $usuario->nombre = $request->nombre;
        $usuario->correo = $request->correo;
        $usuario->telefono = $request->telefono;
        $usuario->pass = Hash::make($request->pass);
        $usuario->id_plan_activo = $request->id_plan_activo;
        $usuario->activo = $request->activo;
        $usuario->correo_verificado = 1;

        // Configuraciones de plan heredadas
        $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
        $usuario->prioridad_cache = $plan->prioridad_busqueda;
        $usuario->destacado_cache = $plan->destacado;
        $usuario->destacado_titulo_cache = $plan->nombre;
        
        $usuario->total_negocios = 0;
        $usuario->total_items = 0;

        $usuario->save();

        // Registrar historial de membresía
        $this->registrarMembresia($usuario, $plan->id, $request->meses, $request->monto, 'ADMIN_ALTA - ' . $token->nombre);

        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'data' => $usuario
        ], 201);
    }

    public function cambiarPlan(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id_plan' => 'required|integer|exists:planes,id',
            'meses' => 'sometimes|integer|min:1',
            'monto' => 'sometimes|numeric',
            'stripe_id' => 'sometimes|string',
            'stripe_cliente_id' => 'sometimes|string'
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = $this->obtenerUsuario($request, $request->id_usuario);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $plan = Plan::find($request->id_plan);
        $type = $request->get('auth_type');

        // Seguridad: Solo admin puede cambiar a planes de pago sin proceso de Stripe configurado aquí.
        // Usuarios solo pueden cambiar a planes gratuitos directamente.
        if ($type !== 'admin' && $plan->precio_mensual > 0) {
            return response()->json(['message' => 'Este plan requiere pago. Por favor, utiliza la pasarela de pago.'], 402);
        }

        // LÓGICA DE CAMBIO: Cancelamos membresías anteriores para que el nuevo plan mande desde hoy
        Membresia::where('id_usuario', $usuario->id)
            ->where('estatus', 'activo')
            ->update(['estatus' => 'cancelado']);

        $usuario->id_plan_activo = $plan->id;
        $usuario->max_alcance_visibilidad = $plan->max_alcance_visibilidad;
        $usuario->prioridad_cache = $plan->prioridad_busqueda;
        $usuario->destacado_cache = $plan->destacado;
        $usuario->destacado_titulo_cache = $plan->nombre;
        $usuario->save();

        // El nuevo plan inicia HOY (al no pasar fecha_inicio al helper)
        $this->registrarMembresia(
            $usuario, 
            $plan->id, 
            $request->input('meses', 12), // Default 12 meses para cambios gratuitos 
            $request->input('monto', 0), 
            $request->input('stripe_id', 'CAMBIO_PLAN'),
            $request->input('stripe_cliente_id', $request->stripe_cliente_id)
        );

        // Sincronizar negocios
        $this->actualizarBeneficiosNegocios($usuario);

        return response()->json([
            'message' => 'Has cambiado al plan ' . $plan->nombre . '. Tus nuevos beneficios están activos desde ahora.',
            'data' => $usuario
        ], 200);
    }
    public function renovarPlan(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'meses' => 'required|integer|min:1',
            'monto' => 'required|numeric',
            'stripe_id' => 'sometimes|string',
            'stripe_cliente_id' => 'sometimes|string'
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = $this->obtenerUsuario($request, $request->id_usuario);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $plan = Plan::find($usuario->id_plan_activo);
        $type = $request->get('auth_type');

        // Seguridad: Solo permitimos renovar directamente si el plan es GRATUITO o si es ADMIN
        if ($type !== 'admin' && $plan->precio_mensual > 0) {
            return response()->json(['message' => 'La renovación de este plan requiere pago.'], 402);
        }

        // RENOVACIÓN: Solo extendemos el tiempo del plan ACTUAL
        $ultimaMembresia = Membresia::where('id_usuario', $usuario->id)
            ->where('id_plan', $usuario->id_plan_activo)
            ->where('estatus', 'activo')
            ->orderBy('fin_en', 'DESC')
            ->first();

        $hoy = Carbon::now();

        // Seguridad: Si el plan es gratuito, no permitir renovar si faltan más de 7 días para expirar
        if ($type !== 'admin' && $plan->precio_mensual == 0) {
            if ($ultimaMembresia && $ultimaMembresia->fin_en->diffInDays($hoy, false) < -7) {
                return response()->json(['message' => 'Aún tienes tiempo a favor. Podrás renovar cuando falten menos de 7 días para que expire tu plan.'], 400);
            }
        }

        $fechaInicio = $hoy;

        if ($ultimaMembresia && $ultimaMembresia->fin_en > $hoy) {
            // Si tiene tiempo a favor del mismo plan, se acumula al final
            $fechaInicio = $ultimaMembresia->fin_en;
            
            // IMPORTANTE: Para evitar múltiples membresías 'vigentes' visualmente, 
            // marcamos la anterior como 'completada' o 'extendida' al crear la nueva
            $ultimaMembresia->update(['estatus' => 'finalizado_por_renovacion']);
        }

        $this->registrarMembresia(
            $usuario,
            $usuario->id_plan_activo,
            $request->input('meses', 12), // Por defecto 12 meses si es gratis/admin
            $request->input('monto', 0),
            $request->input('stripe_id', 'RENOVACION_DIRECTA'),
            $request->input('stripe_cliente_id', $request->stripe_cliente_id),
            $fechaInicio
        );

        return response()->json([
            'message' => 'Membresía renovada exitosamente. Tu nuevo periodo inicia el ' . $fechaInicio->format('d-m-Y'),
            'data' => $usuario
        ], 200);
    }

    private function actualizarBeneficiosNegocios($usuario)
    {
        Negocio::where('id_usuario', $usuario->id)->update([
            'destacado_cache' => $usuario->destacado_cache,
            'destacado_titulo_cache' => $usuario->destacado_titulo_cache,
            'alcance_visibilidad' => $usuario->max_alcance_visibilidad,
            'prioridad_cache' => $usuario->prioridad_cache,
        ]);
    }

    public function actualizar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
            'nombre' => 'sometimes|string|max:255',
            'correo' => 'sometimes|email|unique:usuarios,correo,' . $request->id,
            'telefono' => 'sometimes|string|max:20',
            'pass' => 'sometimes|string|min:6',
            'id_plan_activo' => 'sometimes|integer|exists:planes,id',
            'activo' => 'sometimes|boolean',
            'correo_verificado' => 'sometimes|boolean',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = Usuario::find($request->id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        if ($request->filled('nombre')) $usuario->nombre = $request->nombre;
        if ($request->filled('correo')) $usuario->correo = $request->correo;
        if ($request->filled('telefono')) $usuario->telefono = $request->telefono;
        if ($request->filled('pass')) {
            $usuario->pass = Hash::make($request->pass);
            $usuario->temp_pass = null; // Limpiar contraseña temporal
        }
        if ($request->has('activo')) $usuario->activo = $request->activo;
        if ($request->has('correo_verificado')) $usuario->correo_verificado = $request->correo_verificado;

        $usuario->save();


        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'data' => $usuario
        ], 200);
    }

    public function eliminar(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = Usuario::find($request->id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $usuario->activo = 0;
        $usuario->save(); // Borrado lógico vía 'activo' o SoftDeletes si está activo en el modelo

        return response()->json(['message' => 'Usuario desactivado exitosamente'], 200);
    }

    public function listar()
    {
        $usuarios = Usuario::with('planActivo')
            ->withCount(['negocios', 'items'])
            ->get();
        return response()->json(['data' => $usuarios], 200);
    }

    public function encontrar(int $id)
    {
        $usuario = Usuario::with(['planActivo', 'negocios', 'membresias'])->find($id);
        if ($usuario) {
            $usuario->loadCount(['negocios', 'items']);
        }

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        return response()->json(['data' => $usuario], 200);
    }

    public function solicitarSoporte(Request $request){
        $validate = Validator::make($request->all(), [
            'id' => 'required|integer',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = Usuario::find($request->id);

        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $usuario->activo = 0;
        $usuario->save(); // Borrado lógico vía 'activo' o SoftDeletes si está activo en el modelo

        return response()->json(['message' => 'Usuario desactivado exitosamente'], 200);
    }

    /**
     * Recuperación de contraseña
     */
    public function recuperarPassword(Request $request)
    {
        $validate = Validator::make($request->all(), [
            'correo' => 'required|email',
        ]);

        if ($validate->fails()) {
            return response()->json(['message' => $validate->errors()->first()], 400);
        }

        $usuario = Usuario::where('correo', $request->correo)->first();

        // Retornar siempre el mismo mensaje por seguridad (evitar enumeración de usuarios)
        if (!$usuario) {
            return response()->json(['message' => 'Si el correo existe, se ha enviado una contraseña temporal a su bandeja de entrada'], 200);
        }

        $temp_pass = \Illuminate\Support\Str::random(10);
        $usuario->temp_pass = Hash::make($temp_pass);
        $usuario->save();

        // Módulo de correos
        Mail::to($usuario->correo)->send(new RecuperarPassword([
            'nombre' => $usuario->nombre,
            'pass' => $temp_pass
        ]));

        return response()->json([
            'message' => 'Si el correo existe, se ha enviado una contraseña temporal a su bandeja de entrada',
            'debug_temp_pass' => config('app.debug') ? $temp_pass : null // Solo mostrar en local/debug
        ], 200);
    }

    public function verificarCorreo(Request $request){
        try {
            $token = $request->token;
            if (!$token) return response()->json(['message' => 'Token no proporcionado'], 400);

            $decoded = JWT::decode($token, new Key(config('jwt.secret_usuario'), 'HS256'));

            if ($decoded->sub){
                $usuario = Usuario::find($decoded->sub);
                if (!$usuario) return response()->json(['message' => 'Usuario no encontrado'], 404);
                
                $usuario->activo = 1;
                $usuario->correo_verificado = 1;
                $usuario->save();
                return response()->json(['message' => '¡Correo verificado exitosamente! Ya puedes iniciar sesión.'], 200);
            }
        } catch (\Exception $e) {
            return response()->json(['message' => 'El enlace de verificación ha expirado o es inválido.'], 400);
        }

        return response()->json(['message' => 'Token inválido, contacte al administrador'], 400);
    }

    public function perfil(Request $request)
    {
        $id_usuario = $this->obtenerUsuarioId($request, $request->id_usuario);
        $usuario = Usuario::with(['planActivo', 'membresias'])->where('id', $id_usuario)->first();
        
        if (!$usuario) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $usuario->loadCount(['negocios', 'items']);

        return response()->json(['data' => $usuario], 200);
    }
}
