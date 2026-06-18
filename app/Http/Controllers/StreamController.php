<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StreamService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StreamController extends Controller
{
    public function __construct(
        private StreamService $streamService
    ) {}

    /**
     * Endpoint de streaming
     */
    public function stream(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => 'required|string|max:100000',
            'model' => 'required|string',
            'temperature' => 'nullable|numeric|min:0|max:2',
            'reasoning_effort' => 'nullable|string|in:low,medium,high',
        ]);

        // Sauvegarder le modèle préféré de l'utilisateur
        $user = Auth::user();
        if ($user && $user->preferred_model !== $validated['model']) {
            $user->update(['preferred_model' => $validated['model']]);
        }

        // Récupérer les instructions personnalisées de l'utilisateur
        $systemPrompt = $this->buildSystemPrompt($user);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $validated['message']]
        ];

        $model = $validated['model'];
        $temperature = (float) ($validated['temperature'] ?? 1.0);
        $reasoningEffort = $validated['reasoning_effort'] ?? null;

        return response()->stream(
            function () use ($messages, $model, $temperature, $reasoningEffort): void {
                $this->streamService->streamToOutput($messages, $model, $temperature, $reasoningEffort);
            },
            headers: [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'no-cache, no-store',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    /**
     * Construire le system prompt avec les instructions personnalisées
     */
    private function buildSystemPrompt(?User $user): string
    {
        $prompt = "Tu es un assistant IA utile et précis.\n\n";

        if ($user && $user->ai_about && !empty(trim($user->ai_about))) {
            $prompt .= "=== PROFIL DE L'UTILISATEUR ===\n";
            $prompt .= $user->ai_about . "\n\n";
        }

        if ($user && $user->ai_behavior && !empty(trim($user->ai_behavior))) {
            $prompt .= "=== COMPORTEMENT ATTENDU ===\n";
            $prompt .= $user->ai_behavior . "\n\n";
        }

        // Ajouter les commandes personnalisées
        if ($user && $user->ai_commands) {
            $commands = $this->decodeCommands($user->ai_commands);
            if (!empty($commands)) {
                $prompt .= "=== COMMANDES DISPONIBLES ===\n";
                foreach ($commands as $cmd => $instruction) {
                    $prompt .= "- {$cmd}: {$instruction}\n";
                }
                $prompt .= "\n";
            }
        }

        $prompt .= "Adapte tes réponses en fonction de ces informations. Sois utile et précis.";

        return $prompt;
    }

    /**
     * Decoder les commandes depuis JSON
     */
    private function decodeCommands(mixed $commands): array
    {
        if (empty($commands)) {
            return [];
        }

        if (is_array($commands)) {
            return $commands;
        }

        if (is_string($commands)) {
            $decoded = json_decode($commands, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return [];
    }
}