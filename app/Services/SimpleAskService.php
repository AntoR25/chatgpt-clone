<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class SimpleAskService
{
    public const DEFAULT_MODEL = 'openai/gpt-5-mini';

    private string $apiKey;

    private string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openrouter.api_key');

        $this->baseUrl = rtrim(
            config(
                'services.openrouter.base_url',
                'https://openrouter.ai/api/v1'
            ),
            '/'
        );
    }

    public function getModels(): array
    {
        return cache()->remember(
            'openrouter.models',
            now()->addHour(),
            function (): array {

                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                ])->get($this->baseUrl . '/models');

                if ($response->failed()) {
                    return [];
                }

                return collect($response->json('data', []))
                    ->sortBy('name')
                    ->map(function (array $model): array {
                        return [
                            'id' => $model['id'],
                            'name' => $model['name'],
                            'description' => $model['description'] ?? '',
                            'context_length' => $model['context_length'] ?? 0,
                        ];
                    })
                    ->values()
                    ->toArray();
            }
        );
    }

    public function sendMessage(
        array $messages,
        ?string $model = null,
        float $temperature = 1.0
    ): string {
        $model = $model ?? self::DEFAULT_MODEL;

        $messages = [
            $this->getSystemPrompt(),
            ...$messages,
        ];

        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])
            ->timeout(120)
            ->post(
                $this->baseUrl . '/chat/completions',
                [
                    'model' => $model,
                    'messages' => $messages,
                    'temperature' => $temperature,
                ]
            );

        if ($response->failed()) {

            $error = $response->json(
                'error.message',
                'Erreur inconnue'
            );

            throw new \RuntimeException(
                "Erreur OpenRouter : {$error}"
            );
        }

        return $response->json(
            'choices.0.message.content',
            ''
        );
    }

    private function getSystemPrompt(): array
    {
        $user = Auth::check()
            ? Auth::user()->name
            : 'Utilisateur';

        $now = now()
            ->locale('fr')
            ->format('d/m/Y H:i');

        return [
            'role' => 'system',
            'content' => view(
                'prompts.system',
                [
                    'now' => $now,
                    'user' => $user,
                ]
            )->render(),
        ];
    }
}