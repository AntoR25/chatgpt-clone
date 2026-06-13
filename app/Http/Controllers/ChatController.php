<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
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
     * Creer une conversation
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

    /**
     * Supprimer une conversation
     */
    public function deleteConversation(int $id)
    {
        $conversation = Conversation::where('id', $id)
            ->where('user_id', Auth::id())
            ->first();

        if (! $conversation) {
            return response()->json([
                'message' => 'Conversation introuvable',
            ], 404);
        }

        Message::where('conversation_id', $conversation->id)->delete();
        $conversation->delete();

        return response()->json([
            'message' => 'Conversation supprimée',
            'id' => $id,
        ]);
    }

    /**
     * Envoyer message + OpenRouter avec instructions personnalisees
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

        $user = Auth::user();
        $originalMessage = $request->message;
        $messageText = $originalMessage;

        // Decoder les commandes depuis JSON
        $userCommands = $this->decodeCommands($user->ai_commands);

        // 1. Gestion des commandes personnalisees
        if (str_starts_with($messageText, '/')) {
            $parts = explode(' ', $messageText, 2);
            $cmdName = $parts[0];
            $cmdArgs = $parts[1] ?? '';
            
            if (isset($userCommands[$cmdName])) {
                $commandInstruction = $userCommands[$cmdName];
                
                if (! empty($cmdArgs)) {
                    $messageText = $commandInstruction . "\n\n" . $cmdArgs;
                } else {
                    $messageText = $commandInstruction;
                }
            }
        }

        // 2. Commandes natives
        if ($messageText === '/help' || $messageText === '/aide') {
            $helpMessage = $this->getHelpMessage($userCommands);
            
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $helpMessage,
            ]);
            
            return response()->json([
                'answer' => $helpMessage,
                'conversation_title' => $conversation->title,
            ]);
        }
        
        if ($messageText === '/commands') {
            $commandsMessage = $this->getCommandsList($userCommands);
            
            Message::create([
                'conversation_id' => $conversation->id,
                'role' => 'assistant',
                'content' => $commandsMessage,
            ]);
            
            return response()->json([
                'answer' => $commandsMessage,
                'conversation_title' => $conversation->title,
            ]);
        }

        // 3. Save user message
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $messageText,
        ]);

        // 4. Auto title
        if ($conversation->title === 'Nouvelle conversation') {
            $title = $this->generateAiTitle($originalMessage);
            $conversation->update(['title' => $title]);
            $conversation->refresh();
        }

        // 5. Build history
        $messages = Message::where('conversation_id', $conversation->id)
            ->orderBy('id')
            ->get()
            ->map(fn ($message) => [
                'role' => $message->role,
                'content' => $message->content,
            ])
            ->toArray();

        // 6. Injection du profil utilisateur
        $systemPrompt = $this->buildSystemPrompt($user, $userCommands);
        array_unshift($messages, [
            'role' => 'system',
            'content' => $systemPrompt
        ]);

        // 7. OpenRouter request
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

        // 8. Handle API error
        if ($response->failed()) {
            return response()->json([
                'message' => 'Erreur OpenRouter',
                'error' => $response->body(),
            ], 500);
        }

        $answer = $response->json('choices.0.message.content')
            ?? 'Desole, je n\'ai pas pu generer une reponse.';

        // 9. Save assistant message
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
     * Decoder les commandes depuis JSON ou string
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

    /**
     * Obtenir le message d'aide
     */
    private function getHelpMessage(array $userCommands): string
    {
        $message = "Commandes disponibles :\n\n";
        $message .= "Commandes natives :\n";
        $message .= "- /help ou /aide : Affiche cette aide\n";
        $message .= "- /commands : Liste tes commandes personnalisees\n\n";
        
        if (!empty($userCommands)) {
            $message .= "Tes commandes personnalisees :\n";
            foreach ($userCommands as $cmd => $instruction) {
                $shortInstruction = substr($instruction, 0, 50);
                $message .= "- {$cmd} : {$shortInstruction}...\n";
            }
        } else {
            $message .= "Astuce : Va dans Parametres > IA pour creer tes propres commandes !\n";
            $message .= "Exemple :\n";
            $message .= "- /debug : Analyse ce code\n";
            $message .= "- /eli5 : Explique simplement\n";
            $message .= "- /review : Code review\n";
        }
        
        return $message;
    }

    /**
     * Obtenir la liste des commandes personnalisees
     */
    private function getCommandsList(array $userCommands): string
    {
        if (empty($userCommands)) {
            return "Aucune commande personnalisee configuree.\n\nVa dans Parametres > IA pour en ajouter !";
        }
        
        $response = "Tes commandes personnalisees :\n\n";
        foreach ($userCommands as $cmd => $instruction) {
            $response .= $cmd . " : " . $instruction . "\n\n";
        }
        $response .= "\nUtilise-les comme : " . array_key_first($userCommands) . " ta question";
        
        return $response;
    }

    /**
     * Construire le system prompt avec le profil utilisateur
     */
    private function buildSystemPrompt(User $user, array $userCommands): string
    {
        $systemPrompt = "Tu es un assistant IA utile et precis.";
        
        if (!empty($userCommands)) {
            $systemPrompt .= "\n\nCommandes disponibles :\n";
            foreach ($userCommands as $cmd => $instruction) {
                $systemPrompt .= "- {$cmd}: {$instruction}\n";
            }
            $systemPrompt .= "\nQuand l'utilisateur utilise ces commandes, reponds en consequence.\n";
        }

        if ($user->ai_about || $user->ai_behavior) {
            $systemPrompt = "";
            
            if ($user->ai_about) {
                $systemPrompt .= "A propos de l'utilisateur :\n" . $user->ai_about . "\n\n";
            }
            
            if ($user->ai_behavior) {
                $systemPrompt .= "Comportement attendu :\n" . $user->ai_behavior . "\n\n";
            }
            
            if (!empty($userCommands)) {
                $systemPrompt .= "Commandes personnalisees :\n";
                foreach ($userCommands as $cmd => $instruction) {
                    $systemPrompt .= "- {$cmd}: {$instruction}\n";
                }
                $systemPrompt .= "\n";
            }
            
            $systemPrompt .= "Adapte tes reponses en fonction de ces informations.";
        }
        
        return $systemPrompt;
    }

    /**
     * Generer un titre de conversation avec l'IA
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
                    'content' => 'Tu generes des titres tres courts (max 6 mots) pour des conversations. Reponds uniquement avec le titre, sans guillemets, sans ponctuation inutile.'
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