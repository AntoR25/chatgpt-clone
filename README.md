Structure du projet

designmentor/
├── app/
│   ├── Http/Controllers/
│   │   ├── ChatController.php        # Gestion des conversations et messages
│   │   ├── StreamController.php      # Endpoint de streaming SSE
│   │   └── UserController.php        # Gestion du profil IA
│   ├── Models/
│   │   ├── User.php
│   │   ├── Conversation.php
│   │   └── Message.php
│   └── Services/
│       └── StreamService.php         # Service de streaming OpenRouter
│
├── database/migrations/
├── resources/js/
│   ├── pages/
│   │   ├── Chat.vue
│   │   └── settings/Ai.vue
│   ├── components/
│   │   └── MarkdownRenderer.vue
│   └── layouts/
│
├── routes/web.php
├── tests/
├── .env.example
├── composer.json
├── package.json
└── README.md

Installation rapide
bash

# Cloner le projet
git clone https://github.com/ton-repo/designmentor.git
cd designmentor

# Dépendances PHP
composer install

# Dépendances JS
npm install

# Environnement
cp .env.example .env
php artisan key:generate

# Base de données
php artisan migrate

# Assets
npm run build

# Lancer le serveur
php artisan serve
Variables d'environnement essentielles
env
DB_DATABASE=designmentor
DB_USERNAME=root
DB_PASSWORD=

OPENROUTER_API_KEY=ta_cle_api_openrouter
OPENROUTER_BASE_URL=https://openrouter.ai/api/v1
