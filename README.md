Structure :

<img width="777" height="707" alt="Structures" src="https://github.com/user-attachments/assets/9bf8ab09-f1e5-416b-9218-c478f2bbda85" />


Installation rapide :
bash

# Cloner le projet
git clone https://github.com/ton-repo/designmentor.git
cd chatgpt-clone

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
