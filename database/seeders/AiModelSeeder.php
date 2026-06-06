<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\AiModel;

class AiModelSeeder extends Seeder
{
    public function run(): void
    {
        AiModel::insert([
            [
                'name' => 'Claude Sonnet 4.5',
                'provider' => 'Anthropic',
                'slug' => 'anthropic/claude-sonnet-4.5',
                'is_active' => true,
            ],
            [
                'name' => 'GPT-5 Mini',
                'provider' => 'OpenAI',
                'slug' => 'openai/gpt-5-mini',
                'is_active' => true,
            ],
            [
                'name' => 'Gemini 3',
                'provider' => 'Google',
                'slug' => 'google/gemini-3',
                'is_active' => true,
            ],
        ]);
    }
}