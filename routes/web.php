<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

Route::post('/chat/send', [ChatController::class, 'send']);

Route::inertia('/', 'Welcome')->name('home');

Route::middleware('auth')->group(function () {
    Route::get('/chat', [ChatController::class, 'index']);

    Route::post('/chat/send', [ChatController::class, 'send']);

    Route::post('/chat/conversation', [ChatController::class, 'storeConversation']);

    Route::delete('/chat/conversation/{id}', [ChatController::class, 'deleteConversation']);
});

require __DIR__.'/settings.php';
