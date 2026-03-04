<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Exception;

class ChatAiController extends Controller
{
    /**
     * Main endpoint for the AI Chat Widget
     */
    public function chat(Request $request)
    {
        try {
            $data = $request->validate([
                'message' => 'required|string|max:1000',
                'fingerprint' => 'nullable|string',
                'is_admin' => 'boolean',
                'business_id' => 'nullable|integer'
            ]);

            //Se asignan los valores que vienen del API
            $message = $data['message'];
            $sessionId = 'web-' . Str::random(10); // Valor por defecto
            $isAdmin = $data['is_admin'] ?? false;
            $businessId = $data['business_id'] ?? null;
            // 1. GET USER AND PLAN DETAILED LIMITS - Manual JWT check to support public & logged users
            $user = $request->attributes->get('user'); // Fallback if middleware is ever added
            
            if (!$user) {
                try {
                    $jwt = null;
                    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                        $jwt = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
                    } else if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                        $jwt = $_SERVER['HTTP_AUTHORIZATION'];
                    } else {
                        $jwt = $request->header('Authorization');
                    }

                    if ($jwt && strlen($jwt) > 10) {
                        $jwt = preg_replace('/^Bearer\s+/i', '', $jwt);
                        $jwt = trim($jwt);
                        
                        // Try as User first (clients)
                        try {
                            $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key(config('jwt.secret_usuario'), 'HS256'));
                            $user = \App\Models\Usuario::with('planActivo')->find($decoded->sub);
                            if ($user) {
                                Log::debug("ChatAI: Usuario detectado via JWT ID {$user->id}");
                                $isAdmin = false; // Trust the token, it's a regular user
                            }
                        } catch (Exception $e) {
                            Log::debug("ChatAI: JWT Usuario invalido: " . $e->getMessage());
                            // Try as Admin
                            try {
                                $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key(config('jwt.secret_admin'), 'HS256'));
                                $isAdmin = true;
                                Log::debug("ChatAI: Admin detectado via JWT");
                            } catch (Exception $e2) {
                                Log::debug("ChatAI: JWT Admin invalido: " . $e2->getMessage());
                            }
                        }
                    } else {
                        Log::debug("ChatAI: No se recibio Authorization header o es muy corto.");
                    }
                } catch (Exception $e) {
                    Log::error("ChatAI JWT Error: " . $e->getMessage());
                }
            }
            
            // Atribución de uso: Si es admin y hay business_id, contar para el dueño del negocio
            if (!$user && $isAdmin && $businessId) {
                $negocio = \App\Models\Negocio::find($businessId);
                if ($negocio) {
                    $user = \App\Models\Usuario::with('planActivo')->find($negocio->id_usuario);
                    if ($user) Log::debug("ChatAI: Atribuyendo uso al dueño del negocio ID {$user->id} (Admin chat)");
                }
            }

            $planActivo = $user ? $user->planActivo : null;
            $planName = $planActivo ? strtolower($planActivo->nombre) : 'basic';

            // 1.2 SESSION IDENTIFICATION (For OrionIA grouping)
            if ($user) {
                // Formato: 10 + ID del usuario (8 dígitos con ceros iniciales) -> Ej: 1000000005
                $sessionId = "10" . str_pad($user->id, 8, '0', STR_PAD_LEFT);
            } elseif (!empty($data['fingerprint'])) {
                // Para usuarios no logueados, concatenamos el prefijo 10 al fingerprint para consistencia
                $sessionId = "10" . $data['fingerprint'];
            }

            // Check if plan allows AI Assistant
            if ($user && $planActivo && $planActivo->max_ia_consultas == 0 && !$isAdmin) {
                return response()->json([
                    'reply' => "Tu plan actual ($planName) no incluye el asistente de IA. ¡Sube de nivel para usar estas funciones!",
                    'suggestions' => [],
                    'upgrade_required' => true
                ], 403);
            }

            // 1.1 DAILY QUOTA CHECK (Persisted in DB)
            $consumedToday = 0;
            if ($user) {
                // Auto-reset daily counter if the last update was yesterday or earlier
                if ($user->updated_at && !$user->updated_at->isToday()) {
                    $user->ia_consultas_hoy = 0;
                    $user->save();
                }
                $consumedToday = $user->ia_consultas_hoy;
            }

            if ($user && $planActivo && $consumedToday >= $planActivo->max_ia_consultas && !$isAdmin) {
                return response()->json([
                    'reply' => "Has alcanzado tu límite de **{$planActivo->max_ia_consultas} consultas diarias** de IA para tu plan **$planName**. \n\n¡Mañana tendrás tus créditos recargados! 🚀",
                    'suggestions' => [
                        ['id' => 'admin.plan_benefits', 'label' => '¿Qué incluye mi plan?', 'message' => '¿Qué beneficios tiene mi plan actual?']
                    ],
                    'limit_reached' => true
                ], 403);
            }

            // 2. RATE LIMITING & PROTECTION
            if (!$user && !$isAdmin) {
                // Public limit: 5 messages per 24 hours per fingerprint/IP
                $publicRateKey = "chat_public_" . ($data['fingerprint'] ?? $request->ip());
                if (RateLimiter::tooManyAttempts($publicRateKey, 5)) {
                    return response()->json([
                        'reply' => "Has agotado tus 5 consultas gratuitas de hoy. ✨ **¡Crea una cuenta gratis para seguir platicando conmigo y potenciar tu negocio!**\n\nAl registrarte tendrás acceso a más consultas mensuales y herramientas de IA exclusivas.",
                        'suggestions' => [
                            ['id' => 'public.register', 'label' => '¿Cómo me registro?', 'message' => '¿Cuáles son los pasos para registrarme?'],
                            ['id' => 'public.pricing', 'label' => 'Ver planes', 'message' => '¿Qué planes tienen y cuánto cuestan?']
                        ],
                        'limit_reached' => true
                    ], 429);
                }
                RateLimiter::hit($publicRateKey, 86400); 
            } else {
                // Throttling for logged users or admins: Max 10 messages per minute to prevent spam/abuse
                $rateLimitKey = "chat_speed_" . ($user ? $user->id : $request->ip());
                if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
                    return response()->json([
                        'reply' => "Vas muy rápido. Por favor, espera un minuto antes de continuar.",
                        'limit_reached' => true
                    ], 429);
                }
                RateLimiter::hit($rateLimitKey, 60); // Reset every 60 seconds
            }


             //Se implementarán lectura de comandos para acciones específicas  
             $allowedCommands = [
                '__CONTACTAR_SOPORTE__',
                '__INTENTAR_OTRA_PREGUNTA__',
                '__IA_GENERAR_KEYWORDS__',
                '__IA_MEJORAR_DESCRIPCION__',
               // '__IA_SUGERIR_CATEGORIA__',
                //'__IA_AUDITORIA_SEO__',
                //'__IA_GENERAR_ITEMS__',
            ];  

            // 6. HANDLE COMMANDS
            if (str_starts_with($message, '__') && str_ends_with($message, '__') && in_array($message, $allowedCommands)) {
                $this->logToOrionia($sessionId, $message, 'input');
                
                $cmdData = $this->handleCommand($message, $user, $businessId, $planName, $planActivo);
                
                // Si el comando devuelve una respuesta especial de "bloqueo" (ej. plan insuficiente), la enviamos tal cual
                if (isset($cmdData['status']) && $cmdData['status'] !== 200) {
                    return response()->json($cmdData, $cmdData['status']);
                }

                $reply = $cmdData['reply'] ?? '';
                $this->logToOrionia($sessionId, $reply, 'output');

                // Solo incrementamos uso si el comando realmente usó la IA (definido en el comando)
                $consumedToday = $user ? $user->ia_consultas_hoy : 0;
                if (!empty($cmdData['is_ai'])) {
                    $usageData = $this->incrementUsage($user, $planActivo, $reply);
                    $consumedToday = $usageData['consumedToday'];
                }

                return response()->json(array_merge($cmdData, [
                    'plan_detected' => $planName,
                    'usage' => $user ? [
                        'current' => $consumedToday,
                        'limit' => $planActivo ? $planActivo->max_ia_consultas : 0,
                        'monthly_total' => $user->ia_consultas_mes_actual
                    ] : null
                ]));
            }

            // 3. CACHING (Efficiency - Cached responses DON'T count as new consultas)
            $normalizedMessage = Str::lower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $message)));
            $cacheKey = "ai_answer_" . md5($normalizedMessage);
            
            if (!$isAdmin && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                $this->logToOrionia($sessionId, $message, 'input');
                $this->logToOrionia($sessionId, $cachedData['reply'] . " (CACHED)", 'output');

                return response()->json(array_merge($cachedData, [
                    'plan_detected' => $planName,
                    'is_cached' => true
                ]), 200);
            }

            // 4. CALL AI & FETCH KB
            $this->logToOrionia($sessionId, $message, 'input');
            $kbArticles = $this->searchKnowledgeBase($message);
            $context = $this->preparePromptContext($kbArticles);

            // INYECCIÓN DINÁMICA: Si pregunta por planes o precios, le damos los datos reales de la DB
            $lowerMsg = Str::lower($message);
            if (Str::contains($lowerMsg, ['plan', 'precio', 'costo', 'cuanto vale', 'cuanto cuesta', 'beneficio', 'comparar'])) {
                $dynamicPlans = $this->getDynamicPlansContext();
                $context .= "\n\nTABLA DE PRECIOS Y PLANES VIGENTES (DATOS REALES):\n" . $dynamicPlans;
            }

            $aiResponse = $this->callDeepSeek($message, $context, $isAdmin);
            
            // 5. INCREMENT USAGE
            $usageData = $this->incrementUsage($user, $planActivo, $aiResponse);
            $consumedToday = $usageData['consumedToday'];

            // 8. LOG TO ORIONIA (OUTPUT)
            $this->logToOrionia($sessionId, $aiResponse, 'output');

            // 9. Detect next suggested chips
            $suggestions = $this->getSuggestedChips($kbArticles, $isAdmin, $planName, $user);

            $responseData = [
                'reply' => $aiResponse,
                'suggestions' => $suggestions,
                'sources' => $kbArticles->map(fn($art) => [
                    'titulo' => $art->titulo,
                    'link' => $art->link_fuente
                ])->filter(fn($s) => $s['link'])->values()
            ];

            // Store in Cache
            if (!$isAdmin && strlen($aiResponse) > 15) {
                Cache::put($cacheKey, $responseData, now()->addDays(7));
            }

            return response()->json(array_merge($responseData, [
                'plan_detected' => $planName,
                'usage' => $user ? [
                    'current' => $consumedToday, 
                    'limit' => $planActivo ? $planActivo->max_ia_consultas : 0,
                    'monthly_total' => $user->ia_consultas_mes_actual
                ] : null
            ]), 200);

        } catch (Exception $e) {
            Log::error('ChatAI Error: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'reply' => 'Lo siento, tuve un problema técnico procesando tu mensaje. ¿Podrías intentar de nuevo en un momento? (Error: ' . $e->getMessage() . ')',
                'suggestions' => []
            ], 500);
        }
    }

    //Comandos especiales
    private function handleCommand($command, $user, $businessId = null, $planName = 'basic', $planActivo = null)
    {
        $dailyLimitKey = $user ? "ia_daily_user_" . $user->id : null;
        switch ($command) {
            case '__CONTACTAR_SOPORTE__':
                return [
                    'reply' => "Si necesitas ayuda personalizada, puedes contactar a nuestro equipo de soporte:\n\n"
                        . "📱 **WhatsApp:**\n"
                        . "https://wa.me/524792257124\n\n"
                        . "✉️ **Correo electrónico:**\n"
                        . "contacto@nuevaeradigital.mx\n\n"
                        . "Para poder ayudarte más rápido, incluye:\n"
                        . "- El correo con el que te registraste\n"
                        . "- Una descripción clara del problema\n"
                        . "- Captura de pantalla si es posible\n\n"
                        . "Nuestro equipo revisará tu caso y te responderá lo antes posible.",
                    'suggestions' => [
                        [
                            'id' => 'public.how_it_works',
                            'label' => '¿Cómo funciona Nenis?',
                            'message' => 'Explícame qué es Nenis y cómo ayuda a emprendedores. Resumen breve.'
                        ]
                    ],
                    'is_ai' => false
                ];

            case '__INTENTAR_OTRA_PREGUNTA__':
                return [
                    'reply' => "¡Claro! Estoy aquí para ayudarte. ¿Qué más te gustaría saber sobre Nenis?\n\n"
                        . "Puedes preguntarme sobre nuestros **planes**, cómo **registrar tu negocio**, nuestras **políticas** o cómo mejorar tu presencia en la plataforma.",
                    'suggestions' => $this->getSuggestedChips(collect([]), false, 'basic'),
                    'is_ai' => false
                ];

            case '__IA_GENERAR_KEYWORDS__':
                if (!$user || !$businessId) {
                    return ['reply' => "No pude identificar tu negocio. Por favor, asegúrate de estar dentro del panel de administración.", 'suggestions' => [], 'status' => 400];
                }
                if(!$planActivo || $planActivo->incluye_ia_negocios == 0){
                    return [
                        'reply' => "Tu plan no incluye esta función avanzada de negocios. Revisa la sección de Suscripción para actualizar tu plan.", 
                        'suggestions' => [['id' => 'admin.upgrade_to_pro', 'label' => 'Mejorar a Pro', 'message' => '¿Qué beneficios obtengo si actualizo al plan Pro?']],
                        'status' => 403
                    ];
                }

                $negocio = \App\Models\Negocio::where('id', $businessId)->where('id_usuario', $user->id)->first();
                if (!$negocio) {
                    return ['reply' => "No encontré el negocio seleccionado.", 'suggestions' => [], 'status' => 404];
                }

                $prompt = "Actúa como experto en SEO local para Nenis. Analiza este negocio y genera una lista de 10 a 15 keywords separadas por comas que le ayuden a posicionar mejor.\n\n"
                        . "NEGOCIO: {$negocio->nombre}\n"
                        . "DESCRIPCIÓN: {$negocio->descripcion}\n"
                        . "CATEGORÍA: " . ($negocio->categoriaPrincipal->nombre ?? 'N/A') . "\n\n"
                        . "REGLA: Devuelve solo las keywords sugeridas y una breve explicación de por qué elegiste las más importantes.";

                $aiResp = $this->callDeepSeek($prompt, "Eres un consultor experto en SEO para negocios locales.", true);

                return [
                    'reply' => "Aquí tienes una propuesta de palabras clave optimizadas para **{$negocio->nombre}**:\n\n" . $aiResp . "\n\n¿Te gustaría que te ayude con algo más?",
                    'suggestions' => [['id' => 'admin.apply_keywords_help', 'label' => '¿Cómo las aplico?', 'message' => '¿Cómo aplico las keywords?']],
                    'is_ai' => true
                ];

            case '__IA_MEJORAR_DESCRIPCION__':
                if (!$user || !$businessId) {
                    return ['reply' => "No pude identificar tu negocio. Por favor, asegúrate de estar dentro del panel de administración.", 'suggestions' => [], 'status' => 400];
                }

                if(!$planActivo || $planActivo->incluye_ia_negocios == 0){
                    return [
                        'reply' => "Tu plan no incluye esta función avanzada de negocios. Revisa la sección de Suscripción para actualizar tu plan.", 
                        'suggestions' => [['id' => 'admin.upgrade_to_pro', 'label' => 'Mejorar a Pro', 'message' => '¿Qué beneficios obtengo si actualizo al plan Pro?']],
                        'status' => 403
                    ];
                }

                $negocio = \App\Models\Negocio::where('id', $businessId)->where('id_usuario', $user->id)->first();
                if (!$negocio) {
                    return ['reply' => "No encontré el negocio seleccionado.", 'suggestions' => [], 'status' => 404];
                }

                $prompt = "Actúa como copywriter profesional. Mejora la siguiente descripción de negocio para que sea más atractiva, clara y profesional, manteniendo un tono adecuado para la plataforma Nenis.\n\n"
                        . "NEGOCIO: {$negocio->nombre}\n"
                        . "DESCRIPCIÓN ACTUAL: {$negocio->descripcion}\n\n"
                        . "REGLA: Devuelve una propuesta revisada y brevemente explica los puntos que mejoraste.";

                $aiResp = $this->callDeepSeek($prompt, "Eres un redactor creativo experto en ventas y SEO.", true);

                return [
                    'reply' => "He preparado una mejora para la descripción de **{$negocio->nombre}**:\n\n" . $aiResp,
                    'suggestions' => [['id' => 'admin.gen_keywords', 'label' => 'Generar keywords', 'message' => '__IA_GENERAR_KEYWORDS__']],
                    'is_ai' => true
                ];

        }
    }


    /**
     * Simple search in KB
     */
    private function searchKnowledgeBase($message)
    {
        $message = strtolower($message);
        $words = explode(' ', $message);
        
        // Stop words removal (simplified)
        $stopWords = ['para', 'esta', 'quien', 'donde', 'como', 'forma', 'esta', 'este', 'estos', 'unas', 'unos', 'sobre', 'todo'];
        $usefulWords = array_filter($words, fn($w) => strlen($w) > 2 && !in_array($w, $stopWords));

        if (empty($usefulWords)) {
            return collect([]);
        }

        // We'll calculate a simple relevance score: Title match > Keyword match > Content match
        $query = KbArticle::where('estatus', 'publicado');

        $query->where(function($q) use ($usefulWords) {
            foreach ($usefulWords as $word) {
                $q->orWhere('titulo', 'LIKE', "%$word%")
                  ->orWhere('contenido', 'LIKE', "%$word%")
                  ->orWhere('keywords', 'LIKE', "%$word%");
            }
        });

        // Ordering by ID and latest articles usually gives better results for recently added guides
        return $query->orderBy('id', 'DESC')
                     ->take(5)
                     ->get();
    }

    /**
     * Map KB articles to a text context
     */
    private function preparePromptContext($articles)
    {
        if ($articles->isEmpty()) return "No hay información específica en la base de conocimientos para esta consulta.";

        $context = "Información de referencia de Nenis:\n";
        foreach ($articles as $art) {
            $context .= "### " . $art->titulo . "\n" . $art->contenido . "\n\n";
        }
        return $context;
    }

    /**
     * DeepSeek API Call
     */
    private function callDeepSeek($message, $context, $isAdmin)
    {
        $config = config('services.iaprovider.deepseek');
        $apiKey = $config['key'];
        $baseUrl = rtrim($config['base_url'], '/');

        $systemPrompt = "Eres 'Nenis AI', el asistente inteligente de la plataforma Nenis. 
            Tu objetivo es ayudar a emprendedoras y negocios locales con información clara, precisa y profesional.

            REGLAS IMPORTANTES:
            1. Responde ÚNICAMENTE con base en la información proporcionada en el CONTEXTO.
            2. No inventes datos, precios, políticas ni características.
            3. Si el CONTEXTO no contiene la información suficiente, indica que no encontraste información en la base y sugiere contactar a soporte.
            4. Mantén un tono amable, claro y profesional.
            5. Habla en femenino cuando te refieras a la usuaria como 'Nenis' o usa tono neutro profesional.
            6. No menciones que eres un modelo de IA.
            7. No hagas suposiciones fuera del CONTEXTO.

            FORMATO:
            - Respuesta clara y estructurada.
            - Si aplica, usa listas cortas.
            - Si citas información, hazlo de forma natural (sin decir 'según el contexto').

            CONTEXTO:
            $context
            ";

        if ($isAdmin) {
            $systemPrompt .= "\n\nNOTA: Estás respondiendo a un administrador desde su panel de control. Puedes ser más técnico o directo.";
        }

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer $apiKey",
                'Content-Type' => 'application/json',
            ])->post("$baseUrl/chat/completions", [
                'model' => 'deepseek-chat',
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $message],
                ],
                'temperature' => 0.7,
                'max_tokens' => 1024
            ]);

            if ($response->successful()) {
                return $response->json()['choices'][0]['message']['content'];
            }

            Log::error('DeepSeek Error: ' . $response->body());
            throw new Exception("Error en DeepSeek API");

        } catch (Exception $e) {
            Log::error('DeepSeek Exception: ' . $e->getMessage());
            return "Parece que mi cerebro está algo ocupado ahora mismo. ¿Podrías intentar de nuevo? Si el problema persiste, contacta a soporte.";
        }
    }

    /**
     * Logging interaction to Orionia
     */
    private function logToOrionia($sessionId, $content, $type)
    {
        try {
            // Log a single event (input or output)
            Http::withoutVerifying()->post('https://chatbot-api.orionia.com.mx/saveChat', [
                'company_id' => '6924cd1203988', 
                'agent_id' => '6924ce43db1a8',   
                'whatsapp' => $sessionId,
                'message' => $content,
                'type' => $type
            ]);
        } catch (Exception $e) {
            Log::warning("Error logging to Orionia ($type): " . $e->getMessage());
        }
    }

    /**
     * Get follow-up chips
     */
    private function getSuggestedChips($kbArticles, $isAdmin, $plan = 'basic', $user = null)
    {
        $config = config('chat_suggestions');
        // Usamos sección 'admin' si el usuario está logueado (Emprendedora) O es SuperAdmin
        $isLogged = ($user !== null || $isAdmin);
        $section = $isLogged ? 'admin' : 'public';
        
        if ($kbArticles->isEmpty()) {
            // Return defaults if no topic detected
            if ($isLogged) {
                $initialIds = $config['admin']['initial_by_plan'][$plan] ?? $config['admin']['initial_by_plan']['basic'];
            } else {
                $initialIds = $config['public']['initial'];
            }
            return $this->mapIdsToChips($initialIds, $isLogged);
        }

        // Get dominant category from found articles
        $category = $kbArticles->first()->categoria;
        
        $chipIds = $config[$section]['by_category'][$category] ?? [];
        
        // If too few chips, add some initial ones
        if (count($chipIds) < 2) {
             if ($isLogged) {
                $initial = $config['admin']['initial_by_plan'][$plan] ?? $config['admin']['initial_by_plan']['basic'];
             } else {
                $initial = $config['public']['initial'];
             }
             $chipIds = array_unique(array_merge($chipIds, $initial));
        }
        
        return $this->mapIdsToChips(array_slice($chipIds, 0, 4), $isLogged);
    }

    private function mapIdsToChips($ids, $isAdmin)
    {
        $config = config('chat_suggestions');
        $catalog = $isAdmin ? $config['admin']['chips'] : $config['public']['chips'];
        
        $result = [];
        foreach ($ids as $id) {
            if (isset($catalog[$id])) {
                $result[] = [
                    'id' => $catalog[$id]['id'],
                    'label' => $catalog[$id]['label'],
                    'message' => $catalog[$id]['message']
                ];
            }
        }
        return $result;
    }

    /**
     * Centralized logic to increment daily (DB) and monthly (DB) AI usage.
     */
    private function incrementUsage($user, $planActivo, $aiResponse)
    {
        $consumedToday = 0;
        if ($user && strlen($aiResponse) > 10) {
            // 1. Double safety: Reset daily counter if it's a new day and wasn't reset at start
            if ($user->updated_at && !$user->updated_at->isToday()) {
                $user->ia_consultas_hoy = 0;
                $user->save();
            }

            // 2. Double Increment in DB (Persists immediately to DB)
            Log::debug("ChatAI: [Consumo DB] Usuario {$user->id} | Prev Hoy: {$user->ia_consultas_hoy} | Prev Mes: {$user->ia_consultas_mes_actual}");
            
            $user->increment('ia_consultas_hoy');
            $user->increment('ia_consultas_mes_actual');
            
            $user->refresh(); // Load updated values from DB
            $consumedToday = $user->ia_consultas_hoy;
            
            Log::debug("ChatAI: [Consumo DB] Usuario {$user->id} | Nuevo Hoy: {$consumedToday} | Nuevo Mes: {$user->ia_consultas_mes_actual}");
        }
        
        return ['consumedToday' => $consumedToday];
    }

    /**
     * Fetch current plans from DB and format them for the AI context
     */
    private function getDynamicPlansContext()
    {
        return Cache::remember('ai_plans_context', 3600, function() {
            try {
                $planes = \App\Models\Plan::where('activo', 1)->get();
                $text = "";
                foreach ($planes as $p) {
                    $text .= "- PLAN {$p->nombre}: Precio $" . number_format($p->precio_mensual, 2) . " MXN/mes. ";
                    $text .= "Incluye: {$p->max_negocios} negocios, {$p->max_items} productos, {$p->max_ia_consultas} consultas IA diarias. ";
                    $text .= "Alcance: {$p->max_alcance_visibilidad}. ";
                    $text .= $p->incluye_ia_negocios ? "Tiene herramientas IA avanzadas. " : "No tiene IA avanzada. ";
                    $text .= $p->destacado ? "Es un plan destacado. " : "";
                    $text .= "\n";
                }
                return $text;
            } catch (\Exception $e) {
                Log::error("Error generating dynamic plans context: " . $e->getMessage());
                return "Información de planes no disponible temporalmente.";
            }
        });
    }
}
