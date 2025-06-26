// Carrito dinámico ToolSoft

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
            if (confirm('¿Eliminar este producto del carrito?')) {
                fetch('cart.php?action=remove&name=' + encodeURIComponent(name))
                    .then(() => {
                        row.remove();
                        updateCartTotal();
                        if (document.querySelectorAll('#cart-table tbody tr').length === 0) {
                            location.reload();
                        }
                    });
            }
        });
    });

    // Vaciar carrito
    const clearBtn = document.querySelector('.clear-cart-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            if (confirm('¿Vaciar todo el carrito?')) {
                fetch('cart.php?action=clear')
                    .then(() => location.reload());
            }
        });
    }

    // Actualizar cantidad AJAX
    function updateQuantity(name, quantity, row) {
        fetch('cart.php?action=update&name=' + encodeURIComponent(name) + '&quantity=' + quantity)
            .then(() => {
                row.querySelector('.quantity-input').value = quantity;
                // Actualizar subtotal
                const price = parseFloat(row.children[2].textContent.replace('$', ''));
                row.querySelector('.item-subtotal').textContent = '$' + (price * quantity).toFixed(2);
                updateCartTotal();
            });
    }

    // Actualizar total
    function updateCartTotal() {
        let total = 0;
        document.querySelectorAll('#cart-table tbody tr').forEach(function (row) {
            const subtotal = parseFloat(row.querySelector('.item-subtotal').textContent.replace('$', ''));
            total += subtotal;
        });
        const totalElem = document.getElementById('cart-total');
        if (totalElem) totalElem.textContent = '$' + total.toFixed(2);
    }
}); 