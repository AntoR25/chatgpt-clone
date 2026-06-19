<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        'model' => ['nullable', 'string'],
        'stream' => ['nullable', 'boolean'],
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

    $model = $request->input('model', $user->preferred_model ?? 'openai/gpt-4o-mini');
    $useStream = $request->input('stream', false);
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

    // 2. Commandes natives - Utiliser trim()
    $trimmedMessage = trim($originalMessage);

    if ($trimmedMessage === '/help' || $trimmedMessage === '/aide') {
        // Sauvegarder le message utilisateur
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $originalMessage,
            'model' => null,
        ]);
        
        $helpMessage = $this->getHelpMessage($userCommands);
        
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $helpMessage,
            'model' => $model,
        ]);
        
        if ($conversation->title === 'Nouvelle conversation') {
            $title = $this->generateAiTitle($originalMessage);
            $conversation->update(['title' => $title]);
            $conversation->refresh();
        }
        
        return response()->json([
            'answer' => $helpMessage,
            'conversation_title' => $conversation->title,
        ]);
    }
    
    if ($trimmedMessage === '/commands') {
        // Sauvegarder le message utilisateur
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'user',
            'content' => $originalMessage,
            'model' => null,
        ]);
        
        $commandsMessage = $this->getCommandsList($userCommands);
        
        Message::create([
            'conversation_id' => $conversation->id,
            'role' => 'assistant',
            'content' => $commandsMessage,
            'model' => $model,
        ]);
        
        if ($conversation->title === 'Nouvelle conversation') {
            $title = $this->generateAiTitle($originalMessage);
            $conversation->update(['title' => $title]);
            $conversation->refresh();
        }
        
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
        'model' => null,
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

    // 7. Si streaming demandé
    if ($useStream) {
        return $this->streamResponse($model, $messages);
    }

    // 8. OpenRouter request
    $response = Http::withHeaders([
        'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
        'Content-Type' => 'application/json',
        'HTTP-Referer' => config('app.url'),
        'X-Title' => config('app.name'),
    ])->post('https://openrouter.ai/api/v1/chat/completions', [
        'model' => $model,
        'messages' => $messages,
        'temperature' => 0.7,
    ]);

    if ($response->failed()) {
        return response()->json([
            'message' => 'Erreur OpenRouter',
            'error' => $response->body(),
        ], 500);
    }

    $answer = $response->json('choices.0.message.content')
        ?? 'Desole, je n\'ai pas pu generer une reponse.';

    // 10. Save assistant message
    Message::create([
        'conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => $answer,
        'model' => $model,
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
    $message = "Commandes DesignMentor disponibles :\n\n";
    $message .= "Commandes natives :\n";
    $message .= "- /help ou /aide : Affiche cette aide\n";
    
    $message .= "Commandes design recommandees :\n";
    $message .= "- /ui : Analyse UI et propose des ameliorations\n";
    $message .= "- /ux : Analyse UX et points de friction\n";
    $message .= "- /colors : Propose une palette de couleurs\n";
    $message .= "- /typography : Recommande une hierarchie typographique\n";
    $message .= "- /critique : Critique constructive de design\n";
    $message .= "- /grid : Propose une grille de mise en page\n\n";
    
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
    // Commencer avec le role de base - THEME DESIGNMENTOR
    $systemPrompt = "Tu es DesignMentor, un assistant expert en design UI/UX et direction artistique. 
    Tu aides les designers, developpeurs et creatifs a ameliorer leurs projets.
    
    TA PERSONNALITE :
    - Tu es creatif, inspirant et pedagogique
    - Tu utilises un vocabulaire technique du design (typographie, grille, espacement, contraste, hierarchie, accessibilite)
    - Tu donnes des conseils concrets et applicables
    - Tu references des designers et tendances quand pertinent
    - Tu es encourageant mais honnete dans tes retours
    - Tu parles francais avec quelques termes techniques en anglais (UI, UX, mockup, prototype, design system)
    
    TON ROLE :
    - Conseiller sur les principes de design UI
    - Proposer des ameliorations UX
    - Aider au choix des couleurs, typographies, mises en page
    - Donner des retours sur des maquettes (si l'utilisateur decrit)
    - Partager des bonnes pratiques d'accessibilite
    - Suggerer des outils et ressources design
    
    Reponds toujours de maniere structuree, avec des exemples concrets et des justifications claires.\n\n";
    
    // Ajouter les commandes personnalisees si elles existent
    if (!empty($userCommands)) {
        $systemPrompt .= "=== COMMANDES DISPONIBLES ===\n";
        foreach ($userCommands as $cmd => $instruction) {
            $systemPrompt .= "- {$cmd}: {$instruction}\n";
        }
        $systemPrompt .= "\n";
    }
    
    // Ajouter le profil utilisateur (AI ABOUT)
    if ($user->ai_about && !empty(trim($user->ai_about))) {
        $systemPrompt .= "=== PROFIL DE L'UTILISATEUR ===\n";
        $systemPrompt .= $user->ai_about . "\n\n";
    }
    
    // Ajouter le comportement attendu (AI BEHAVIOR)
    if ($user->ai_behavior && !empty(trim($user->ai_behavior))) {
        $systemPrompt .= "=== COMPORTEMENT ATTENDU ===\n";
        $systemPrompt .= $user->ai_behavior . "\n\n";
    }
    
    // Instruction finale
    $systemPrompt .= "Adapte tes reponses en fonction de ces informations. Sois utile et precis.";
    
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

    /**
     * Streamer la reponse de l'IA en temps reel
     */
    private function streamResponse(string $model, array $messages)
    {
        return response()->stream(function () use ($model, $messages) {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . env('OPENROUTER_API_KEY'),
                'Content-Type' => 'application/json',
                'HTTP-Referer' => config('app.url'),
                'X-Title' => config('app.name'),
            ])->withOptions(['stream' => true])
            ->timeout(120)
            ->post('https://openrouter.ai/api/v1/chat/completions', [
                'model' => $model,
                'messages' => $messages,
                'temperature' => 0.7,
                'stream' => true,
            ]);

            if ($response->failed()) {
                echo '[ERROR] ' . $response->json('error.message', 'Erreur HTTP');
                if (ob_get_level() > 0) ob_flush();
                flush();
                return;
            }

            $body = $response->toPsrResponse()->getBody();
            $buffer = '';

            while (!$body->eof()) {
                $buffer .= $body->read(1024);

                while (($pos = strpos($buffer, "\n")) !== false) {
                    $line = trim(substr($buffer, 0, $pos));
                    $buffer = substr($buffer, $pos + 1);

                    if ($line === '' || str_starts_with($line, ':')) {
                        continue;
                    }

                    if (!str_starts_with($line, 'data: ')) {
                        continue;
                    }

                    $data = substr($line, 6);

                    if ($data === '[DONE]') {
                        break 2;
                    }

                    try {
                        $chunk = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
                        
                        if (isset($chunk['error'])) {
                            echo '[ERROR] ' . ($chunk['error']['message'] ?? 'Erreur inconnue');
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                            break 2;
                        }

                        $delta = $chunk['choices'][0]['delta'] ?? [];

                        if (!empty($delta['content'])) {
                            echo $delta['content'];
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }

                        if (!empty($delta['reasoning'])) {
                            echo '[REASONING]' . $delta['reasoning'] . '[/REASONING]';
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }

                        if (!empty($delta['reasoning_content'])) {
                            echo '[REASONING]' . $delta['reasoning_content'] . '[/REASONING]';
                            if (ob_get_level() > 0) ob_flush();
                            flush();
                        }
                    } catch (\JsonException) {
                        // Ignorer les erreurs JSON
                    }
                }
            }
        }, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'no-cache, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}