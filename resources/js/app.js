import './bootstrap';
import './guest-cart';
import '../css/app.css';
import '../css/fashion.css';

const isAuthenticated = document.body.dataset.authenticated === 'true';
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });

const updateBadge = (items) => {
    document.querySelectorAll('[data-cart-badge]').forEach((badge) => { badge.textContent = items.reduce((total, item) => total + Number(item.quantity), 0); });
};

async function authenticatedCart() {
    const response = await fetch('/cart/data', { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error('No fue posible obtener el carrito.');
    return response.json();
}

async function refreshBadge() {
    try { updateBadge(isAuthenticated ? (await authenticatedCart()).items : window.guestCart.items()); } catch (_) { /* Badge remains available on a temporary network failure. */ }
}

async function addProduct(button) {
    const product = { id: Number(button.dataset.productId), name: button.dataset.productName, price: Number(button.dataset.productPrice), image: button.dataset.productImage || null, stock: Number(button.dataset.productStock) };
    const quantity = Number(document.querySelector(button.dataset.quantityTarget)?.value ?? 1);
    if (!isAuthenticated) { window.guestCart.add(product, quantity); updateBadge(window.guestCart.items()); window.location.assign('/carrito'); return; }
    const response = await fetch('/cart', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ product_id: product.id, quantity }) });
    if (!response.ok) { alert((await response.json()).message ?? 'No fue posible añadir el producto.'); return; }
    updateBadge((await response.json()).items);
    button.textContent = '✓ Añadido'; setTimeout(() => { button.textContent = 'Añadir al carrito'; }, 1300);
}

document.addEventListener('click', (event) => {
    const button = event.target.closest('[data-add-to-cart]');
    if (button) { event.preventDefault(); addProduct(button); }
    const galleryImage = event.target.closest('[data-gallery-image]');
    if (galleryImage) { document.querySelector('[data-gallery-main]').src = galleryImage.dataset.galleryImage; }
});

document.addEventListener('DOMContentLoaded', async () => {
    if (isAuthenticated && document.body.dataset.syncGuest === 'true') { try { await window.guestCart.sync(); } catch (_) { /* Retain local cart so the customer can retry. */ } }
    await refreshBadge();
    const cartRoot = document.querySelector('[data-cart-root]');
    if (cartRoot) initialiseCart(cartRoot);
});

async function initialiseCart(root) {
    let cart = isAuthenticated ? await authenticatedCart() : { items: window.guestCart.items() };
    const render = () => {
        const items = cart.items;
        const total = items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_price ?? item.price), 0);
        root.innerHTML = !items.length ? '<div class="empty-state">Tu carrito está vacío. <a href="/productos">Ver productos</a></div>' : `<div class="cart-list">${items.map((item) => { const product = item.product ?? item; const price = Number(item.unit_price ?? product.price); return `<article class="cart-row"><img src="${product.images?.[0] ?? product.image ?? 'https://placehold.co/160x160?text=Producto'}" alt=""><div><h3>${product.name}</h3><p>${money.format(price)}</p><div class="quantity"><button data-cart-action="minus" data-id="${item.id ?? item.product_id}">−</button><span>${item.quantity}</span><button data-cart-action="plus" data-id="${item.id ?? item.product_id}" data-stock="${product.stock}">+</button></div></div><strong>${money.format(price * item.quantity)}</strong><button class="text-button" data-cart-action="remove" data-id="${item.id ?? item.product_id}">Eliminar</button></article>`; }).join('')}</div><aside class="cart-summary"><h2>Resumen</h2><p>Total <strong>${money.format(total)}</strong></p><a class="button" href="${isAuthenticated ? '/checkout' : '/ingresar?redirect=/checkout'}">Proceder al pago</a></aside>`;
    };
    const persist = async (id, quantity) => {
        if (isAuthenticated) { const response = await fetch(`/cart/items/${id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ quantity }) }); if (!response.ok) throw new Error('No hay suficiente stock.'); cart = await response.json(); }
        else { cart.items = cart.items.map((item) => item.product_id === Number(id) ? { ...item, quantity } : item); localStorage.setItem('ecommerce_guest_cart', JSON.stringify(cart.items)); }
    };
    root.addEventListener('click', async (event) => { const button = event.target.closest('[data-cart-action]'); if (!button) return; const item = cart.items.find((row) => String(row.id ?? row.product_id) === button.dataset.id); if (!item) return; try { if (button.dataset.cartAction === 'remove') { if (isAuthenticated) { const response = await fetch(`/cart/items/${item.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } }); cart = await response.json(); } else { cart.items = cart.items.filter((row) => row !== item); localStorage.setItem('ecommerce_guest_cart', JSON.stringify(cart.items)); } } else { const next = item.quantity + (button.dataset.cartAction === 'plus' ? 1 : -1); if (next > 0) await persist(item.id ?? item.product_id, next); } updateBadge(cart.items); render(); } catch (error) { alert(error.message); } });
    render();
}
