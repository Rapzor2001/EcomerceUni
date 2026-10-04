/**
 * Guest cart adapter. Product pages can call window.guestCart.add(productId, quantity).
 * Call sync() immediately after login/registration, then clear the local cart only on success.
 */
const storageKey = 'ecommerce_guest_cart';

function read() {
    try { return JSON.parse(localStorage.getItem(storageKey)) ?? []; } catch { return []; }
}

function write(items) {
    localStorage.setItem(storageKey, JSON.stringify(items));
}

window.guestCart = {
    items: read,
    add(product, quantity = 1) {
        const productId = typeof product === 'object' ? product.id : product;
        const items = read();
        const item = items.find((entry) => entry.product_id === Number(productId));
        if (item) item.quantity = Math.min(99, item.quantity + Number(quantity));
        else items.push({ product_id: Number(productId), quantity: Number(quantity), ...(typeof product === 'object' ? { name: product.name, price: Number(product.price), image: product.image ?? null, stock: Number(product.stock) } : {}) });
        write(items);
        return items;
    },
    async sync(endpoint = '/cart/sync-guest') {
        const items = read();
        if (!items.length) return null;
        const token = document.querySelector('meta[name="csrf-token"]')?.content;
        const response = await fetch(endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token }, body: JSON.stringify({ items: items.map(({ product_id, quantity }) => ({ product_id, quantity })) }) });
        if (!response.ok) throw new Error('No fue posible sincronizar el carrito.');
        localStorage.removeItem(storageKey);
        return response.json();
    },
};
