


# Seeding
php artisan make:seeder UserSeeder
php artisan db:seed

# Migration
php artisan make:migration create_posts_table
php artisan migrate:rollback
php artisan migrate:rollback --step=4
php artisan migrate
php artisan make:migration add_role_id_to_users_table --table=users
php artisan migrate:reset

composer dump-autoload
php artisan vendor:publish --tag=pages --force

# Start mit:
composer run dev &

stripe listen --forward-to http://192.168.10.28:8000/stripe/webhook

clear && php artisan migrate:refresh && php artisan db:seed
php artisan migrate:reset
php artisan db:seed
php artisan db:wipe

admin@admin.com :: admin

# Testing
php artisan test
php artisan test --coverage

| Command                                 | Beschreibung                                     |
| --------------------------------------- | ------------------------------------------------ |
| `php artisan test`                      | Führt alle Tests mit schöner Laravel-Ausgabe aus |
| `php artisan test --parallel`           | Tests parallel ausführen (schneller)             |
| `php artisan test --filter=NAME`        | Nur bestimmte Tests ausführen                    |
| `php artisan test --group=NAME`         | Nur Tests einer bestimmten Gruppe laufen lassen  |
| `php artisan test --coverage`           | Code Coverage (welche Zeilen werden getestet)    |
| `php artisan test --stop-on-failure`    | Stoppt beim ersten Fehler                        |
| `php artisan test --testsuite=feature`  | Nur Feature Tests laufen lassen                  |
| `php artisan test --testsuite=unit`     | Nur Unit Tests laufen lassen                     |
| `php artisan test --recreate-databases` | Testdatenbanken neu aufsetzen                    |



# Setup Project:
composer create-project laravel/laravel mein-mandantenprojekt
cd mein-mandantenprojekt
composer require stancl/tenancy spatie/laravel-permission
php artisan tenancy:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate
php artisan storage:link


Umgang mit ressoure routes
https://laravel.com/docs/10.x/controllers


https://github.com/settings/billing

Installation:
composer install
composer update
php artisan key:generate
php artisan migrate

php artisan --version

## Stripe installieren (für Tests)
https://docs.stripe.com/stripe-cli/install?locale=de-DE&install-method=apt

stripe listen --forward-to http://127.0.0.1:8000/stripe/webhook


# NPM installieren
sudo apt install npm

Node Version:
node -v

# NVM installieren (https://nodejs.org/en/download/current):
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.39.7/install.sh | bash
nvm install 24

npm audit fix

# Breeze installieren
composer require laravel/breeze --dev
php artisan breeze:install
// gewählt: blade; Test: pest

# Breeze remove:
composer remove laravel/breeze
composer update
php artisan config:cache

npm install 
npm run dev &
php artisan migrate


# vue-mandate 
/bin/bash -c "$(curl -fsSL https://php.new/install/linux/8.4)"
composer global require laravel/installer
laravel new vue-mandate
""" Auswahl: vue/standard/pest """

composer require stancl/tenancy spatie/laravel-permission
php artisan tenancy:install
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"
php artisan migrate

sudo apt install redis-server -y
sudo systemctl start redis

# Redis testen
redis-cli
127.0.0.1:6379> PING
PONG

# Redis PHP-Erweiterung installieren
sudo apt install php-redis -y
php -m | grep redis // wenn redis erscheint ist das gut

# in die .env:
SESSION_DRIVER=redis
CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

php artisan config:clear
php artisan cache:clear

# in der vite.config.ts folgendes eintragen:
    server: {
        host: '0.0.0.0',
        port: 5173,
        cors: true,
        hmr: {
            host: '192.168.2.3', // DEINE lokale IP hier
        }
    },

# laravel cashier installieren
composer require laravel/cashier
php artisan vendor:publish --tag="cashier-migrations"
php artisan migrate
https://laravel.com/docs/12.x/billing
Anpassen der Migrations auf tenant bzw. tenant_id als foreign
Foreign muss als String implementiert werden!!!

npm install @heroicons/vue @vueuse/core



# Paket
composer dump-autoload
composer require nilit/lara-boiler-core:* --prefer-source


# Sonstiges
php artisan make:controller ShiftController
php artisan make:model Post