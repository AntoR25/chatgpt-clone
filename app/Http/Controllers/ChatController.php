<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

class ChatController extends Controller
{
    /**
     * Page chat (Inertia)
     */
    public function index()
    {
        $conversations = Conversation::where('user_id', Auth::id())
            ->with('messages')
            ->latest()
            ->get();

        return inertia('Chat', [
            'conversations' => $conversations,
        ]);
    }

    /**
     * Créer une conversation
     */
    public function storeConversation()
    {
        $conversation = Conversation::create([
            'user_id' => Auth::id(),
            'ai_model_id' => 1,
            'title' => 'Nouvelle conversation',
        ]);

        return response()->json([
            'id' => $conversation->id,
            'title' => $conversation->title,
        ]);
    }

    public function deleteConversation($id)
{
    $conversation = Conversation::where('id', $id)
        ->where('user_id', Auth::id())
        ->first();

    if (! $conversation) {
        return response()->json([
            'message' => 'Conversation introuvable',
        ], 404);
    }

    /**
     * Delete messages first (important si pas cascade DB)
     */
    Message::where('conversation_id', $conversation->id)->delete();

    /**
     * Delete conversation
     */
    $conversation->delete();

    return response()->json([
        'message' => 'Conversation supprimée',
        'id' => $id,
    ]);
}
    /**
     * Envoyer message + OpenRouter
     */
    public function send(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string'],
            'conversation_id' => ['required', 'integer'],
        ]);

        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $conversation) {
            return response()->json([
                'message' => 'Conversation introuvable',
            ], 404);
        }

        /**
         * 1. Save user message
         */
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message,
        ]);

        /**
         * 2. Auto title (IA intelligente, seulement 1er message)
         */
        if ($conversation->title === 'Nouvelle conversation') {
            $title = $this->generateAiTitle($request->message);

            $conversation->update([
                'title' => $title,
            ]);

            $conversation->refresh();
        }

        /**
         * 3. Build history
         */
        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->toArray();

        /**
         * 4. OpenRouter request (chat)
         */
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'openai/gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.7,
        ]);

        /**
         * 5. Handle API error
         */
        if ($response->failed()) {
            return response()->json([
                'message' => 'Erreur OpenRouter',
                'error' => $response->body(),
            ], 500);
        }

        $answer = $response->json('choices.0.message.content')
            ?? 'Réponse vide';

        /**
         * 6. Save assistant message
         */
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer,
        ]);

        return response()->json([
            'answer' => $answer,
            'conversation_title' => $conversation->title,
        ]);
    }

    /**
     * IA TITLE GENERATOR (IMPORTANT)
     */
    private function generateAiTitle(string $message): string
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'openai/gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Tu génères des titres très courts (max 6 mots) pour des conversations. Réponds uniquement avec le titre, sans guillemets, sans ponctuation inutile.'
                ],
                [
                    'role' => 'user',
                    'content' => $message
                ]
            ],
            'temperature' => 0.3,
            'max_tokens' => 20,
        ]);

        if ($response->failed()) {
            return 'Nouvelle conversation';
        }

        return trim(
            $response->json('choices.0.message.content')
            ?? 'Nouvelle conversation'
        );
    }
}