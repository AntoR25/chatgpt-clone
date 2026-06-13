<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    public function getAiProfile()
    {
        $user = Auth::user();
        
        // Decoder ai_commands si c'est une string JSON
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
            'ai_commands' => $aiCommands
        ]);
    }
    
    public function updateAiProfile(Request $request)
    {
        $request->validate([
            'ai_about' => 'nullable|string',
            'ai_behavior' => 'nullable|string',
            'ai_commands' => 'nullable|array',
        ]);
        
        $user = Auth::user();
        $user->ai_about = $request->ai_about;
        $user->ai_behavior = $request->ai_behavior;
        // Encoder en JSON avant de sauvegarder
        $user->ai_commands = json_encode($request->ai_commands ?? []);
        $user->save();
        
        return response()->json(['success' => true]);
    }
}