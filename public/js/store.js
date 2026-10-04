(() => {
  const key = 'ecommerce_guest_cart';
  const get = () => { try { return JSON.parse(localStorage.getItem(key)) || []; } catch (_) { return []; } };
  const save = (items) => localStorage.setItem(key, JSON.stringify(items));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
  const authenticated = document.body.dataset.authenticated === 'true';
  const money = new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 });
  window.guestCart = {
    items: get,
    add(product, quantity = 1) {
      const items = get(); const id = Number(typeof product === 'object' ? product.id : product);
      const item = items.find((row) => row.product_id === id);
      if (item) item.quantity = Math.min(99, item.quantity + Number(quantity));
      else items.push({ product_id: id, quantity: Number(quantity), ...(typeof product === 'object' ? { name: product.name, price: Number(product.price), image: product.image || null, stock: Number(product.stock) } : {}) });
      save(items); return items;
    },
    async sync() {
      const items = get(); if (!items.length) return null;
      const response = await fetch('/cart/sync-guest', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ items: items.map(({ product_id, quantity }) => ({ product_id, quantity })) }) });
      if (!response.ok) throw new Error('No fue posible sincronizar el carrito.'); localStorage.removeItem(key); return response.json();
    },
  };
  const badge = (items) => document.querySelectorAll('[data-cart-badge]').forEach((node) => { node.textContent = items.reduce((sum, item) => sum + Number(item.quantity), 0); });
  const remoteCart = async () => { const response = await fetch('/cart/data', { headers: { Accept: 'application/json' } }); if (!response.ok) throw new Error('No fue posible obtener el carrito.'); return response.json(); };
  const refreshBadge = async () => { try { badge(authenticated ? (await remoteCart()).items : get()); } catch (_) {} };
  async function add(button) {
    const product = { id: Number(button.dataset.productId), name: button.dataset.productName, price: Number(button.dataset.productPrice), image: button.dataset.productImage, stock: Number(button.dataset.productStock) };
    const quantity = Number(document.querySelector(button.dataset.quantityTarget)?.value || 1);
    if (!authenticated) { window.guestCart.add(product, quantity); badge(get()); location.assign('/carrito'); return; }
    const response = await fetch('/cart', { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ product_id: product.id, quantity }) });
    if (!response.ok) { const data = await response.json(); alert(data.message || 'No fue posible añadir el producto.'); return; }
    badge((await response.json()).items); button.textContent = 'Añadido ✓'; setTimeout(() => { button.textContent = 'Añadir'; }, 1200);
  }
  async function cartView(root) {
    let cart = authenticated ? await remoteCart() : { items: get() };
    const render = () => {
      const total = cart.items.reduce((sum, item) => sum + Number(item.quantity) * Number(item.unit_price ?? item.price), 0);
      root.innerHTML = !cart.items.length ? '<div class="empty-state">Tu bolsa está vacía. <a href="/productos">Ver colección</a></div>' : `<div class="cart-list">${cart.items.map((item) => { const product = item.product || item; const price = Number(item.unit_price ?? product.price); return `<article class="cart-row"><img src="${product.images?.[0] || product.image || 'https://placehold.co/160x160?text=District'}" alt=""><div><h3>${product.name}</h3><p>${money.format(price)}</p><div class="quantity"><button data-cart="minus" data-id="${item.id || item.product_id}">−</button><span>${item.quantity}</span><button data-cart="plus" data-id="${item.id || item.product_id}">+</button></div></div><strong>${money.format(price * item.quantity)}</strong><button class="text-button" data-cart="remove" data-id="${item.id || item.product_id}">Eliminar</button></article>`; }).join('')}</div><aside class="cart-summary"><h2>Resumen</h2><p>Total <strong>${money.format(total)}</strong></p><a class="button" href="${authenticated ? '/checkout' : '/ingresar'}">Proceder al pago</a></aside>`;
    };
    root.addEventListener('click', async (event) => { const button = event.target.closest('[data-cart]'); if (!button) return; const item = cart.items.find((row) => String(row.id || row.product_id) === button.dataset.id); if (!item) return; try { if (button.dataset.cart === 'remove') { if (authenticated) { const r = await fetch(`/cart/items/${item.id}`, { method: 'DELETE', headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf } }); cart = await r.json(); } else { cart.items = cart.items.filter((row) => row !== item); save(cart.items); } } else { const quantity = item.quantity + (button.dataset.cart === 'plus' ? 1 : -1); if (quantity < 1) return; if (authenticated) { const r = await fetch(`/cart/items/${item.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ quantity }) }); if (!r.ok) throw new Error('No hay suficiente inventario.'); cart = await r.json(); } else { item.quantity = quantity; save(cart.items); } } badge(cart.items); render(); } catch (error) { alert(error.message); } });
    render();
  }
  document.addEventListener('click', (event) => { const addButton = event.target.closest('[data-add-to-cart]'); if (addButton) { event.preventDefault(); add(addButton); } const thumb = event.target.closest('[data-gallery-image]'); if (thumb && document.querySelector('[data-gallery-main]')) document.querySelector('[data-gallery-main]').src = thumb.dataset.galleryImage; });
  document.addEventListener('DOMContentLoaded', async () => { if (authenticated && document.body.dataset.syncGuest === 'true') try { await window.guestCart.sync(); } catch (_) {} await refreshBadge(); const root = document.querySelector('[data-cart-root]'); if (root) cartView(root); });
})();
