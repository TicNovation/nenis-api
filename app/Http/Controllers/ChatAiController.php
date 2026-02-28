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
                'is_admin' => 'boolean'
            ]);

            $message = $data['message'];
            $sessionId = $data['session_id'] ?? 'web-'.Str::random(10);
            $isAdmin = $data['is_admin'] ?? false;
            $plan = 'basic';
            $user = null;

            // 1. GET USER AND PLAN
            try {
                $user = auth('api')->user();
                if ($user) {
                    $negocio = \App\Models\Negocio::where('id_usuario', $user->id)->first();
                    if ($negocio) {
                        $plan = strtolower($negocio->plan ?? 'basic');
                    }
                }
            } catch (Exception $authEx) { }

            // 2. RATE LIMITING (Prevention of abuse)
            $rateLimitKey = $user ? "chat_user_{$user->id}" : "chat_ip_" . ($data['fingerprint'] ?? $request->ip());
            
            // Define limits based on plan
            $maxAttempts = 5; // Default for guests
            if ($isAdmin) $maxAttempts = 1000;
            elseif ($plan === 'elite' || $plan === 'diamante') $maxAttempts = 200;
            elseif ($plan === 'pro') $maxAttempts = 50;
            elseif ($user) $maxAttempts = 15; // Basic/Authenticated

            if (RateLimiter::tooManyAttempts($rateLimitKey, $maxAttempts)) {
                $seconds = RateLimiter::availableIn($rateLimitKey);
                
                $fallbackMessage = "🌸 **¡Hola!** En este momento no puedo analizar a fondo tu solicitud porque has alcanzado el límite de consultas diarias para tu plan ($plan).\n\n" .
                    "Sin embargo, aquí tienes información útil que podría ayudarte de inmediato:\n\n" .
                    "📜 **Legal y Políticas:**\n" .
                    "• [Términos y Condiciones](REEMPLAZAR_POR_URL_TERMINOS)\n" .
                    "• [Políticas de Privacidad](REEMPLAZAR_POR_URL_PRIVACIDAD)\n\n" .
                    "📞 **Contacto y Soporte:**\n" .
                    "• WhatsApp: [Escribir a Soporte](https://wa.me/REEMPLAZAR_POR_NUMERO)\n" .
                    "• Correo: REEMPLAZAR_POR_CORREO\n\n" .
                    "📱 **Síguenos:**\n" .
                    "• [Instagram](REEMPLAZAR_POR_INSTAGRAM)\n" .
                    "• [Facebook](REEMPLAZAR_POR_FACEBOOK)\n\n" .
                    "Podrás volver a consultarme en aproximadamente **" . ceil($seconds / 60) . " minutos**. ¡Gracias por ser parte de Nenis!";

                return response()->json([
                    'reply' => $fallbackMessage,
                    'suggestions' => $this->getSuggestedChips(collect([]), $isAdmin, $plan), // Sugerencias iniciales
                    'limit_reached' => true
                ], 429);
            }
            RateLimiter::hit($rateLimitKey, 86400); // 1 day window

            // 3. CACHING (Efficiency)
            $normalizedMessage = Str::lower(trim(preg_replace('/[^a-zA-Z0-9\s]/', '', $message)));
            $cacheKey = "ai_answer_" . md5($normalizedMessage);
            
            if (!$isAdmin && Cache::has($cacheKey)) {
                $cachedData = Cache::get($cacheKey);
                // Log input only (output is cached)
                $this->logToOrionia($sessionId, $message, 'input');
                $this->logToOrionia($sessionId, $cachedData['reply'] . " (CACHED)", 'output');

                return response()->json(array_merge($cachedData, [
                    'plan_detected' => $plan,
                    'is_cached' => true
                ]), 200);
            }

            // 4. IMMEDIATE LOG TO ORIONIA (INPUT)
            $this->logToOrionia($sessionId, $message, 'input');

            // 5. Detect Intent / Find relevant KB articles
            $kbArticles = $this->searchKnowledgeBase($message);
            
            // 6. Prepare context for AI
            $context = $this->preparePromptContext($kbArticles);
            
            // 7. Call DeepSeek (This is the "thinking" part)
            $aiResponse = $this->callDeepSeek($message, $context, $isAdmin);
            
            // 8. LOG TO ORIONIA (OUTPUT)
            $this->logToOrionia($sessionId, $aiResponse, 'output');

            // 9. Detect next suggested chips
            $suggestions = $this->getSuggestedChips($kbArticles, $isAdmin, $plan);

            $responseData = [
                'reply' => $aiResponse,
                'suggestions' => $suggestions,
                'sources' => $kbArticles->map(fn($art) => [
                    'titulo' => $art->titulo,
                    'link' => $art->link_fuente
                ])->filter(fn($s) => $s['link'])->values()
            ];

            // Store in Cache - Only store responses that are meaningful
            if (!$isAdmin && strlen($aiResponse) > 15) {
                Cache::put($cacheKey, $responseData, now()->addDays(7));
            }

            return response()->json(array_merge($responseData, [
                'plan_detected' => $plan
            ]), 200);

        } catch (Exception $e) {
            Log::error('ChatAI Error: ' . $e->getMessage());
            return response()->json([
                'reply' => 'Lo siento, tuve un problema técnico procesando tu mensaje. ¿Podrías intentar de nuevo en un momento?',
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

        $systemPrompt = "Eres 'Nenis AI', el asistente inteligente de la plataforma Nenis. " .
            "Tu objetivo es ayudar a emprendedoras y negocios locales. " .
            "Usa la siguiente información de contexto para responder de forma precisa, amable y profesional. " .
            "Si no sabes la respuesta, sugiere contactar a soporte técnico. " .
            "Habla siempre en femenino si te diriges a la usuaria como 'Nenis' o de forma neutra profesional.\n\n" .
            "CONTEXTO:\n$context";

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
}
