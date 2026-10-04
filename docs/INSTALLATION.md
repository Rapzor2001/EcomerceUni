# Instalación y entrega de NOIR DISTRICT

## Qué entregar

Entrega la carpeta completa del proyecto, incluyendo `app`, `bootstrap`, `config`, `database`, `public`, `resources`, `routes`, `storage`, `composer.json`, `composer.lock`, `package.json` y `package-lock.json`. No entregues `.env`, `vendor`, `node_modules`, `storage/logs` ni credenciales. La persona receptora crea su propio `.env` desde `.env.example`.

## Requisitos de la otra persona

- PHP 8.2 o superior, con extensiones `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `json` y `zip`.
- Composer 2.
- PostgreSQL 14 o superior, creado y en ejecución.
- Node.js 20 LTS y npm, solo si necesita reconstruir los recursos de Vite.
- Acceso a Internet durante `composer install` y, en producción, HTTPS público para Bold y los webhooks.

## Arranque local

```powershell
cd ecommerce
composer install
Copy-Item .env.example .env
php artisan key:generate
```

En `.env`, complete como mínimo `APP_URL`, `DB_DATABASE`, `DB_USERNAME` y `DB_PASSWORD`. Para correo configure `RESEND_API_KEY`; nunca comparta ese archivo en Git o por correo.

```powershell
php artisan migrate --seed
php artisan storage:link
php artisan optimize:clear
php artisan serve
```

Abrir `http://127.0.0.1:8000`. El panel queda en `/admin`. Después de sembrar la base, el usuario de ejemplo es `admin@noirdistrict.test` con clave `Admin123!`; debe cambiarse antes de publicar.

## Construcción de recursos

La tienda incluye recursos estáticos en `public/css` y `public/js`, por lo que puede iniciar sin Node. Si se modifican `resources/css` o `resources/js`, ejecutar:

```powershell
npm ci
npm run build
```

## Producción

1. Defina `APP_ENV=production`, `APP_DEBUG=false` y una `APP_URL` HTTPS pública.
2. Configure PostgreSQL y ejecute `php artisan migrate --force` y `php artisan storage:link`.
3. Configure Resend con un dominio verificado. Retire `MAIL_REDIRECT_TO`.
4. Configure `BOLD_IDENTITY_KEY`, `BOLD_INTEGRITY_KEY`, `BOLD_WEBHOOK_SECRET` y `BOLD_REDIRECTION_URL=https://dominio/checkout/resultado/{order}`. Registre `https://dominio/api/webhooks/bold` en Bold.
5. Para WhatsApp Cloud API configure las variables `WHATSAPP_*` y registre `https://dominio/api/webhooks/whatsapp`.
6. Ejecute `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache` y asegure permisos de escritura para `storage` y `bootstrap/cache`.

## Pruebas

Antes de entregar, ejecutar:

```powershell
php artisan optimize:clear
php artisan test
```

Después, si es producción, regenerar cachés con los tres comandos `*:cache` indicados arriba.
