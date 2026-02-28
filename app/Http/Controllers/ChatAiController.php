<?php

namespace App\Http\Controllers;

use App\Models\KbArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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

            // 1. IMMEDIATE LOG TO ORIONIA (INPUT)
            // Log user message as soon as it arrives
            $this->logToOrionia($sessionId, $message, 'input');

            // 2. Try to get authenticated user and their active plan
            try {
                if ($user = auth('api')->user()) {
                    $negocio = \App\Models\Negocio::where('id_usuario', $user->id)->first();
                    if ($negocio) {
                        $plan = strtolower($negocio->plan ?? 'basic');
                    }
                }
            } catch (Exception $authEx) { }

            // 3. Detect Intent / Find relevant KB articles
            $kbArticles = $this->searchKnowledgeBase($message);
            
            // 4. Prepare context for AI
            $context = $this->preparePromptContext($kbArticles);
            
            // 5. Call DeepSeek (This is the "thinking" part)
            $aiResponse = $this->callDeepSeek($message, $context, $isAdmin);
            
            // 6. LOG TO ORIONIA (OUTPUT)
            // Log AI response once calculated
            $this->logToOrionia($sessionId, $aiResponse, 'output');

            // 7. Detect next suggested chips
            $suggestions = $this->getSuggestedChips($kbArticles, $isAdmin, $plan);

            return response()->json([
                'reply' => $aiResponse,
                'suggestions' => $suggestions,
                'plan_detected' => $plan,
                'sources' => $kbArticles->map(fn($art) => [
                    'titulo' => $art->titulo,
                    'link' => $art->link_fuente
                ])->filter(fn($s) => $s['link'])->values()
            ], 200);

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
