<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\StreamController;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    // ============================================================
    // ROUTES DE STREAMING
    // ============================================================
    Route::post('/chat/stream', [StreamController::class, 'stream'])->middleware('auth');

    // ============================================================
    // ROUTES DU CHAT
    // ============================================================
    Route::get('/chat', [ChatController::class, 'index']);
    Route::post('/chat/send', [ChatController::class, 'send']);
    Route::post('/chat/conversation', [ChatController::class, 'storeConversation']);
    Route::delete('/chat/conversation/{id}', [ChatController::class, 'deleteConversation']);
    
    // ============================================================
    // ROUTES DE RECHERCHE
    // ============================================================
    Route::get('/chat/search', [ChatController::class, 'search']);
    Route::get('/chat/conversation/{id}/messages', [ChatController::class, 'getMessages']);

    // ============================================================
    // ROUTES DE PARTAGE
    // ============================================================
    Route::post('/chat/share', [ChatController::class, 'share']);
    Route::get('/shared/{id}', [ChatController::class, 'showShared']);

    // ============================================================
    // ROUTES DE TAGS
    // ============================================================
    Route::post('/chat/tags', [ChatController::class, 'updateTags']);

    // ============================================================
    // ROUTES DE FORK
    // ============================================================
    Route::post('/chat/fork', [ChatController::class, 'fork']);

    // ============================================================
    // ROUTES D'EXPORT
    // ============================================================
    Route::post('/chat/export', [ChatController::class, 'export']);

    // ============================================================
    // ROUTES DU PROFIL UTILISATEUR
    // ============================================================
    Route::get('/user/ai-profile', [UserController::class, 'getAiProfile']);
    Route::post('/user/ai-profile', [UserController::class, 'updateAiProfile']);
    
    // Commandes design
    Route::post('/user/initialize-design-commands', [UserController::class, 'initializeDesignCommands']);
    Route::post('/user/update-commands', [UserController::class, 'updateCommands']);
    Route::post('/user/update-model', [UserController::class, 'updatePreferredModel']);

    // ============================================================
    // ROUTE DES PARAMETRES
    // ============================================================
    Route::get('/settings/ai', function () {
        return inertia('settings/Ai');
    })->name('settings.ai');
});

// ============================================================
// INCLUSION DES ROUTES SUPPLEMENTAIRES
// ============================================================
require __DIR__.'/settings.php';