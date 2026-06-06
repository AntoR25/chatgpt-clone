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
            'conversations' => $conversations ?? []
        ]);
    }

    /**
     * Créer une conversation
     */
    public function storeConversation()
    {
        $conv = Conversation::create([
            'user_id' => Auth::id(),
            'ai_model_id' => 1, // 👈 IMPORTANT (ou ton modèle par défaut)
            'title' => 'Nouvelle conversation'
        ]);

        return response()->json([
            'id' => $conv->id,
            'title' => $conv->title
        ]);
    }

    /**
     * Envoyer message + OpenRouter
     */
    public function send(Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'conversation_id' => 'required|integer'
        ]);

        $conversation = Conversation::where('id', $request->conversation_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$conversation) {
            return response()->json([
                'message' => 'Conversation introuvable'
            ], 404);
        }

        // 💾 user message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $request->message
        ]);

        // 🤖 OpenRouter request
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
            'Content-Type' => 'application/json',
            'HTTP-Referer' => config('app.url'),
            'X-Title' => config('app.name'),
        ])->post('https://openrouter.ai/api/v1/chat/completions', [
            'model' => 'openai/gpt-4o-mini',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $request->message
                ]
            ]
        ]);

        $answer = $response->json('choices.0.message.content') 
            ?? 'Réponse vide';

        // 💾 assistant message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $answer
        ]);

        return response()->json([
            'answer' => $answer
        ]);
    }
}