// Checkout dinámico ToolSoft

document.addEventListener('DOMContentLoaded', function () {
    // Actualizar cantidad
    document.querySelectorAll('.quantity-controls').forEach(function (controls) {
        const row = controls.closest('tr');
        const name = row.getAttribute('data-name');
        const input = controls.querySelector('.quantity-input');
        const minus = controls.querySelector('.minus');
        const plus = controls.querySelector('.plus');

        minus.addEventListener('click', function () {
            let val = parseInt(input.value, 10);
            if (val > 1) {
                updateQuantity(name, val - 1, row);
            }
        });
        plus.addEventListener('click', function () {
            let val = parseInt(input.value, 10);
            updateQuantity(name, val + 1, row);
        });
        input.addEventListener('change', function () {
            let val = parseInt(input.value, 10);
            if (isNaN(val) || val < 1) val = 1;
            updateQuantity(name, val, row);
        });
    });

    // Eliminar producto
    document.querySelectorAll('.remove-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const row = btn.closest('tr');
            const name = row.getAttribute('data-name');
            if (confirm('¿Eliminar este producto del pedido?')) {
                fetch('cart.php?action=remove&name=' + encodeURIComponent(name))
                    .then(() => {
                        row.remove();
                        updateCheckoutTotal();
                        if (document.querySelectorAll('#checkout-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
            }
        });
    });

    // Actualizar cantidad AJAX
    function updateQuantity(name, quantity, row) {
        fetch('cart.php?action=update&name=' + encodeURIComponent(name) + '&quantity=' + quantity)
            .then(() => {
                row.querySelector('.quantity-input').value = quantity;
                // Actualizar subtotal
                const price = parseFloat(row.children[2].textContent.replace('$', ''));
                row.querySelector('.item-subtotal').textContent = '$' + (price * quantity).toFixed(2);
                updateCheckoutTotal();
            });
    }

    // Actualizar total
    function updateCheckoutTotal() {
        let total = 0;
        document.querySelectorAll('#checkout-table tbody tr').forEach(function (row) {
            const subtotal = parseFloat(row.querySelector('.item-subtotal').textContent.replace('$', ''));
            total += subtotal;
        });
        const totalElem = document.getElementById('checkout-total');
        if (totalElem) totalElem.textContent = '$' + total.toFixed(2);
    }

    // Confirmar compra AJAX
    const checkoutForm = document.getElementById('checkout-form');
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function (e) {
            e.preventDefault();
            const btn = checkoutForm.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = '⏳ Procesando...';
            fetch('checkout.php', {
                method: 'POST',
                body: new FormData(checkoutForm)
            })
            .then(response => {
                // Si la respuesta es una redirección, seguirla
                if (response.redirected) {
                    window.location.href = response.url;
                    return;
                }
                return response.text();
            })
            .then(html => {
                if (!html) return;
                // Si la respuesta contiene la palabra clave de éxito, redirigir
                if (html.includes('¡Pago Completado!') || html.includes('order_success.php')) {
                    window.location.href = 'order_success.php';
                } else {
                    document.getElementById('checkout-message').innerHTML = html;
                    btn.disabled = false;
                    btn.textContent = 'Confirmar compra';
                }
            })
            .catch(() => {
                document.getElementById('checkout-message').innerHTML = '<div class="cart-message error">Ocurrió un error al procesar el pedido.</div>';
                btn.disabled = false;
                btn.textContent = 'Confirmar compra';
            });
        });
    }
}); 