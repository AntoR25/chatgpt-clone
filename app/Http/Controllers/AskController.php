<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SimpleAskService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AskController extends Controller
{
    public function __construct(
        private readonly SimpleAskService $askService
    ) {
    }

    public function index(): Response
    {
        return Inertia::render('Ask/Index', [
            'models' => $this->askService->getModels(),
        ]);
    }

    public function ask(Request $request)
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'model' => ['nullable', 'string'],
        ]);

        try {

            $answer = $this->askService->sendMessage(
                messages: [
                    [
                        'role' => 'user',
                        'content' => $validated['message'],
                    ],
                ],
                model: $validated['model'] ?? null,
            );

            return back()->with([
                'answer' => $answer,
                'message' => $validated['message'],
            ]);

        } catch (\Throwable $e) {

            return back()->withErrors([
                'api' => $e->getMessage(),
            ]);
        }
    }
}