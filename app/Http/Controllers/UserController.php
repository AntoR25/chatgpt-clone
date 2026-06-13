<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
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
}