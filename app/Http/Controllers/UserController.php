<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Recuperer le profil IA de l'utilisateur
     */
    public function getAiProfile()
    {
        $user = Auth::user();
        
        $aiCommands = $user->ai_commands;
        if (is_string($aiCommands)) {
            $aiCommands = json_decode($aiCommands, true) ?? [];
        }
        if (!is_array($aiCommands)) {
            $aiCommands = [];
        }
        
        return response()->json([
            'ai_about' => $user->ai_about,
            'ai_behavior' => $user->ai_behavior,
            'ai_commands' => $aiCommands,
            'preferred_model' => $user->preferred_model ?? 'openai/gpt-4o-mini',
        ]);
    }
    
    /**
     * Mettre a jour le profil IA de l'utilisateur
     */
    public function updateAiProfile(Request $request)
    {
        $user = Auth::user();
        
        // Ne mettre a jour que les champs presents dans la requete
        if ($request->has('ai_about')) {
            $user->ai_about = $request->ai_about;
        }
        
        if ($request->has('ai_behavior')) {
            $user->ai_behavior = $request->ai_behavior;
        }
        
        if ($request->has('ai_commands')) {
            $user->ai_commands = json_encode($request->ai_commands ?? []);
        }
        
        if ($request->has('preferred_model')) {
            $user->preferred_model = $request->preferred_model;
        }
        
        $user->save();
        
        return response()->json(['success' => true]);
    }

    /**
     * Initialiser les commandes DesignMentor par defaut
     */
    public function initializeDesignCommands(Request $request)
    {
        $user = Auth::user();
        
        $defaultDesignCommands = [
            "/ui" => "Analyse l'interface utilisateur decrite et propose des ameliorations concretes. Structure ta reponse en points cles.",
            "/ux" => "Analyse l'experience utilisateur du parcours decrit. Identifie les points de friction et propose des solutions.",
            "/colors" => "Propose une palette de couleurs pour le projet decrit. Justifie tes choix (psychologie des couleurs, accessibilite, tendances).",
            "/typography" => "Recommande une hierarchie typographique pour le projet. Propose des combinaisons de polices adaptees.",
            "/critique" => "Fais une critique constructive du design decrit. Points positifs, points d'attention, suggestions d'amelioration.",
            "/grid" => "Propose une grille de mise en page adaptee au projet. Explique le systeme de colonnes et les breakpoints.",
            "/inspire" => "Donne 3 references de design ou d'artistes qui pourraient inspirer le projet. Explique ce qu'il faut en retenir.",
            "/accessibility" => "Analyse l'accessibilite du projet decrit. Propose des ameliorations pour rendre le design plus inclusif."
        ];
        
        // Si l'utilisateur n'a pas de commandes, les initialiser
        $currentCommands = $user->ai_commands;
        if (is_string($currentCommands)) {
            $currentCommands = json_decode($currentCommands, true) ?? [];
        }
        if (!is_array($currentCommands)) {
            $currentCommands = [];
        }
        
        if (empty($currentCommands)) {
            $user->ai_commands = json_encode($defaultDesignCommands);
            $user->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Commandes DesignMentor initialisees',
                'commands' => $defaultDesignCommands
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Des commandes existent deja',
            'commands' => $currentCommands
        ]);
    }

    /**
     * Mettre a jour uniquement les commandes
     */
    public function updateCommands(Request $request)
    {
        $request->validate([
            'commands' => 'required|array',
        ]);
        
        $user = Auth::user();
        $user->ai_commands = json_encode($request->commands);
        $user->save();
        
        return response()->json(['success' => true]);
    }

    /**
     * Mettre a jour uniquement le modele prefere
     */
    public function updatePreferredModel(Request $request)
    {
        $request->validate([
            'preferred_model' => 'required|string',
        ]);
        
        $user = Auth::user();
        $user->preferred_model = $request->preferred_model;
        $user->save();
        
        return response()->json(['success' => true]);
    }
}