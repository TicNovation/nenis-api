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
                'session_id' => 'nullable|string',
                'fingerprint' => 'nullable|string',
                'is_admin' => 'boolean',
                'business_id' => 'nullable|integer'
            ]);

            $message = $data['message'];
            $sessionId = $data['session_id'] ?? 'web-'.Str::random(10);
            $isAdmin = $data['is_admin'] ?? false;
            $businessId = $data['business_id'] ?? null;
            // 1. GET USER AND PLAN DETAILED LIMITS
            $user = null;
            try {
                // Explicitly reload user to get the latest ia_consultas_mes_actual
                $user = auth('api')->user();
                if ($user) {
                    $user = \App\Models\Usuario::with('planActivo')->find($user->id);
                }
            } catch (Exception $authEx) { }

            $planObj = $user ? $user->planActivo : null;
            $planName = $planObj ? strtolower($planObj->nombre) : 'basic';

            // Check if plan allows AI Assistant
            if ($user && $planObj && !$planObj->incluye_asistente_ia && !$isAdmin) {
                return response()->json([
                    'reply' => "Tu plan actual ($planName) no incluye el asistente de IA. ¡Sube de nivel para usar estas funciones!",
                    'suggestions' => [],
                    'upgrade_required' => true
                ], 403);
            }

            // 1.1 DAILY QUOTA CHECK (For registered users)
            $dailyLimitKey = $user ? "ia_daily_user_" . $user->id : null;
            $consumedToday = $dailyLimitKey ? Cache::get($dailyLimitKey, 0) : 0;

            if ($user && $planObj && $consumedToday >= $planObj->max_ia_consultas && !$isAdmin) {
                return response()->json([
                    'reply' => "Has alcanzado tu límite de **{$planObj->max_ia_consultas} consultas diarias** de IA para tu plan **$planName**. \n\n¡Mañana tendrás tus créditos recargados! 🚀",
                    'suggestions' => [
                        ['id' => 'admin.plan_benefits', 'label' => '¿Qué incluye mi plan?', 'message' => '¿Qué beneficios tiene mi plan actual?']
                    ],
                    'limit_reached' => true
                ], 403);
            }

            // 2. RATE LIMITING & PUBLIC QUOTA
            if (!$user && !$isAdmin) {
                // Public limit: 5 messages per 24 hours per IP
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
                // Logged users or admins: Safety rate limit (30/day for users, 1000/day for admins)
                $rateLimitKey = $user ? "chat_user_{$user->id}" : "chat_admin_ip_" . $request->ip();
                $maxAttempts = $isAdmin ? 1000 : 50; 

                if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                    return response()->json([
                        'reply' => "Has realizado muchas consultas seguidas. Por favor, espera un momento antes de continuar.",
                        'limit_reached' => true
                    ], 429);
                }
                RateLimiter::hit($rateLimitKey, 86400);
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

            if (str_starts_with($message, '__') && str_ends_with($message, '__') && in_array($message, $allowedCommands)) {
                return $this->handleCommand($message, $user, $businessId);
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

            // 4. CALL AI & INCREMENT COUNTER (INPUT)
            $this->logToOrionia($sessionId, $message, 'input');
            $kbArticles = $this->searchKnowledgeBase($message);
            $context = $this->preparePromptContext($kbArticles);
            $aiResponse = $this->callDeepSeek($message, $context, $isAdmin);
            
            // Increment the counter only on success and when authenticated (and not cached)
            if ($user && strlen($aiResponse) > 10) {
                // Increment daily cache (expires at midnight)
                if ($dailyLimitKey) {
                    $secondsToMidnight = now()->diffInSeconds(now()->endOfDay());
                    if (!Cache::has($dailyLimitKey)) {
                        Cache::put($dailyLimitKey, 1, $secondsToMidnight);
                        $consumedToday = 1;
                    } else {
                        $consumedToday = Cache::increment($dailyLimitKey);
                    }
                }
                // Keep incrementing monthly counter for stats
                $user->increment('ia_consultas_mes_actual');
            }

            // 8. LOG TO ORIONIA (OUTPUT)
            $this->logToOrionia($sessionId, $aiResponse, 'output');

            // 9. Detect next suggested chips
            $suggestions = $this->getSuggestedChips($kbArticles, $isAdmin, $planName);

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
                    'current' => $consumedToday, // Send daily count for the dashboard progress bar
                    'limit' => $planObj ? $planObj->max_ia_consultas : 0,
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

    /**
     * Simple search in KB
     */
    private function searchKnowledgeBase($message)
    {
        // Simple search logic: check if any keyword from the message matches the title, keywords or content
        $words = explode(' ', strtolower($message));
        $usefulWords = array_filter($words, fn($w) => strlen($w) > 3);

        $query = KbArticle::where('estatus', 'publicado');

        if (!empty($usefulWords)) {
            $query->where(function($q) use ($usefulWords) {
                foreach ($usefulWords as $word) {
                    $q->orWhere('titulo', 'LIKE', "%$word%")
                      ->orWhere('contenido', 'LIKE', "%$word%")
                      ->orWhere('keywords', 'LIKE', "%$word%");
                }
            });
        }

        return $query->take(3)->get();
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
    private function getSuggestedChips($kbArticles, $isAdmin, $plan = 'basic')
    {
        $config = config('chat_suggestions');
        $section = $isAdmin ? 'admin' : 'public';
        
        if ($kbArticles->isEmpty()) {
            // Return defaults if no topic detected
            if ($isAdmin) {
                $initialIds = $config['admin']['initial_by_plan'][$plan] ?? $config['admin']['initial_by_plan']['basic'];
            } else {
                $initialIds = $config['public']['initial'];
            }
            return $this->mapIdsToChips($initialIds, $isAdmin);
        }

        // Get dominant category from found articles
        $category = $kbArticles->first()->categoria;
        
        $chipIds = $config[$section]['by_category'][$category] ?? [];
        
        // If too few chips, add some initial ones
        if (count($chipIds) < 2) {
             if ($isAdmin) {
                $initial = $config['admin']['initial_by_plan'][$plan] ?? $config['admin']['initial_by_plan']['basic'];
             } else {
                $initial = $config['public']['initial'];
             }
             $chipIds = array_unique(array_merge($chipIds, $initial));
        }

        return $this->mapIdsToChips(array_slice($chipIds, 0, 4), $isAdmin);
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

    private function handleCommand($command, $user, $businessId = null)
    {
        switch ($command) {
            case '__CONTACTAR_SOPORTE__':
                return response()->json([
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
                    ]
                ]);

            case '__INTENTAR_OTRA_PREGUNTA__':
                return response()->json([
                    'reply' => "¡Claro! Estoy aquí para ayudarte. ¿Qué más te gustaría saber sobre Nenis?\n\n"
                        . "Puedes preguntarme sobre nuestros **planes**, cómo **registrar tu negocio**, nuestras **políticas** o cómo mejorar tu presencia en la plataforma.",
                    'suggestions' => $this->getSuggestedChips(collect([]), false, 'basic')
                ]);

            case '__IA_GENERAR_KEYWORDS__':
                if (!$user || !$businessId) {
                    return response()->json(['reply' => "No pude identificar tu negocio. Por favor, asegúrate de estar dentro del panel de administración y haber seleccionado un negocio.", 'suggestions' => []]);
                }

                $negocio = \App\Models\Negocio::where('id', $businessId)->where('id_usuario', $user->id)->first();
                if (!$negocio) {
                    return response()->json(['reply' => "No encontré el negocio seleccionado o no tienes permisos para gestionarlo.", 'suggestions' => []]);
                }

                $prompt = "Actúa como experto en SEO local para Nenis. Analiza este negocio y genera una lista de 10 a 15 keywords separadas por comas que le ayuden a posicionar mejor.\n\n"
                        . "NEGOCIO: {$negocio->nombre}\n"
                        . "DESCRIPCIÓN: {$negocio->descripcion}\n"
                        . "CATEGORÍA: " . ($negocio->categoriaPrincipal->nombre ?? 'N/A') . "\n\n"
                        . "REGLA: Devuelve solo las keywords sugeridas y una breve explicación de por qué elegiste las más importantes.";

                $resp = $this->callDeepSeek($prompt, "Eres un consultor experto en SEO para negocios locales.", true);
                
                // Increment daily and monthly
                $dailyLimitKey = "ia_daily_user_" . $user->id;
                $secondsToMidnight = now()->diffInSeconds(now()->endOfDay());
                if (!Cache::has($dailyLimitKey)) {
                    Cache::put($dailyLimitKey, 1, $secondsToMidnight);
                } else {
                    Cache::increment($dailyLimitKey);
                }
                $user->increment('ia_consultas_mes_actual');

                return response()->json([
                    'reply' => "Aquí tienes una propuesta de palabras clave optimizadas para **{$negocio->nombre}**:\n\n" . $resp . "\n\n¿Te gustaría que te ayude con algo más?",
                    'suggestions' => [
                        ['id' => 'admin.apply_keywords_help', 'label' => '¿Cómo las aplico?', 'message' => '¿Cómo aplico las keywords?']
                    ]
                ]);

            case '__IA_MEJORAR_DESCRIPCION__':
                if (!$user || !$businessId) {
                    return response()->json(['reply' => "No pude identificar tu negocio. Por favor, asegúrate de estar dentro del panel de administración.", 'suggestions' => []]);
                }

                $negocio = \App\Models\Negocio::where('id', $businessId)->where('id_usuario', $user->id)->first();
                if (!$negocio) {
                    return response()->json(['reply' => "No encontré el negocio seleccionado o no tienes permisos para gestionarlo.", 'suggestions' => []]);
                }

                $prompt = "Actúa como copywriter profesional. Mejora la siguiente descripción de negocio para que sea más atractiva, clara y profesional, manteniendo un tono adecuado para la plataforma Nenis.\n\n"
                        . "NEGOCIO: {$negocio->nombre}\n"
                        . "DESCRIPCIÓN ACTUAL: {$negocio->descripcion}\n\n"
                        . "REGLA: Devuelve una propuesta revisada y brevemente explica los puntos que mejoraste.";

                $resp = $this->callDeepSeek($prompt, "Eres un redactor creativo experto en ventas y SEO.", true);
                
                // Increment daily and monthly
                $dailyLimitKey = "ia_daily_user_" . $user->id;
                $secondsToMidnight = now()->diffInSeconds(now()->endOfDay());
                if (!Cache::has($dailyLimitKey)) {
                    Cache::put($dailyLimitKey, 1, $secondsToMidnight);
                } else {
                    Cache::increment($dailyLimitKey);
                }
                $user->increment('ia_consultas_mes_actual');

                return response()->json([
                    'reply' => "He preparado una mejora para la descripción de **{$negocio->nombre}**:\n\n" . $resp,
                    'suggestions' => [
                        ['id' => 'admin.gen_keywords', 'label' => 'Generar keywords', 'message' => '__IA_GENERAR_KEYWORDS__']
                    ]
                ]);

            default:
                return response()->json([
                    'reply' => "Comando no reconocido o en desarrollo. ¿En qué más puedo ayudarte?",
                    'suggestions' => []
                ]);
        }
    }
}
