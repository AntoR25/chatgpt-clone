<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    // Chat routes
    Route::get('/chat', [ChatController::class, 'index']);
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::post('/chat/conversation', [ChatController::class, 'storeConversation']);
    Route::delete('/chat/conversation/{id}', [ChatController::class, 'deleteConversation']);
    
    // User AI Profile routes
    Route::get('/user/ai-profile', [UserController::class, 'getAiProfile']);
    Route::post('/user/ai-profile', [UserController::class, 'updateAiProfile']);
    
    // Settings routes
    Route::get('/settings/ai', function () {
        return inertia('settings/Ai');  // setting sans 's' et minuscule
    })->name('settings.ai');
});

require __DIR__.'/settings.php';