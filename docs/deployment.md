# Despliegue de Nova Store

Requisitos: PHP 8.2+, Composer 2, Node 20+, PostgreSQL 14+, extensión `pdo_pgsql`, HTTPS y credenciales activas de Botón de pagos Bold.

```bash
git clone <URL_DEL_REPOSITORIO> nova-store
cd nova-store
composer install --no-dev --optimize-autoloader
cp .env.example .env
# Configura APP_ENV=production, APP_DEBUG=false, APP_URL=https://tu-dominio.co,
# DB_*, MAIL_* y BOLD_IDENTITY_KEY, BOLD_INTEGRITY_KEY, BOLD_WEBHOOK_SECRET.
php artisan key:generate
php artisan migrate:fresh --seed --force
npm ci
npm run build
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

Configura el document root del servidor web en `public/`, asigna escritura sobre `storage/` y `bootstrap/cache/`, y usa HTTPS. En el Panel Bold registra `https://tu-dominio.co/api/webhooks/bold`. El endpoint valida `X-Bold-Signature` mediante HMAC-SHA256 sobre el cuerpo en Base64. Usa como `BOLD_WEBHOOK_SECRET` la llave correspondiente al Botón de pagos.

Para colas asíncronas, usa un supervisor para `php artisan queue:work --tries=3` después de cambiar `QUEUE_CONNECTION` a `database` o Redis.

## Credenciales de datos demo

- Administrador: `admin@nova.test` / `Admin123!`
- Cliente: `cliente@nova.test` / `Cliente123!`
