# Arquitectura inicial del e-commerce

## Datos

Las migraciones crean `categories`, `products`, `carts`, `cart_items`, `orders` y `order_items`, además de extender `users` con teléfono y dirección de envío. Las cantidades y precios usan `decimal(12,2)`, las imágenes y direcciones se guardan en `jsonb` de PostgreSQL y el índice parcial de `carts` impide más de un carrito activo por cliente.

## Carrito persistente

1. Un invitado guarda `{ product_id, quantity }` en `localStorage` con `resources/js/guest-cart.js`.
2. Tras registro o inicio de sesión, la vista debe ejecutar `await window.guestCart.sync()`.
3. `POST /cart/sync-guest` fusiona el contenido con el carrito `active` ligado al usuario; se limita al stock actual y se actualiza el precio mostrado en el carrito.
4. El navegador se limpia solamente tras una respuesta correcta, por lo que los artículos no se pierden por un error de red.
5. El carrito del usuario siempre se consulta desde PostgreSQL, por lo que sigue disponible después de cerrar sesión o desde otro dispositivo.

Las mutaciones autenticadas se exponen como `GET/POST /cart`, `PATCH/DELETE /cart/items/{cartItem}`. Todas requieren CSRF y sesión `auth`.

## Preparación local

1. Copia `.env.example` a `.env` y completa PostgreSQL, SMTP y Bold.
2. Ejecuta `php artisan key:generate`.
3. Crea la base indicada en `DB_DATABASE` y ejecuta `php artisan migrate`.
4. Ejecuta `npm install` y `npm run dev` para compilar los recursos.

`BOLD_INTEGRITY_KEY` y `BOLD_WEBHOOK_SECRET` son secretos de servidor y nunca deben enviarse al navegador.
