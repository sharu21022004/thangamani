// ================================================================
//  product.js  —  Thanga Mani  (PHP-backend edition)
//  Replaces the previous purely client-side version.
//  Now:
//    1. Creates a Razorpay Order via PHP   POST /api/create-order.php
//    2. Verifies payment via PHP           POST /api/verify-payment.php
//       (signature check + email sending happen server-side)
// ================================================================

// ── Quantity input listeners ──────────────────────────────────
let qtyInputs = document.querySelectorAll('.qty');

qtyInputs.forEach(input => {
    input.addEventListener('input', () => {
        if (input.value === '') input.value = 0;
        calculateTotal();
    });
});

// ── Total calculation ─────────────────────────────────────────
function calculateTotal() {
    let total = 0;
    qtyInputs.forEach(i => {
        total += (Number(i.value) || 0) * (Number(i.dataset.price) || 0);
    });
    document.getElementById('totalAmount').innerText = total;
    return total;
}

// ── Open order popup (with validation) ───────────────────────
function openOrderPopup() {
    if (calculateTotal() <= 0) {
        alert('Please add quantity for at least one product.');
        return;
    }
    new bootstrap.Modal(document.getElementById('customerModal')).show();
}

// ── Confirm order: create Razorpay order then open checkout ──
async function confirmOrder() {
    const name    = document.getElementById('custName').value.trim();
    const phone   = document.getElementById('custPhone').value.trim();
    const email = document.getElementById('custEmail').value.trim();
    const city    = document.getElementById('custCity').value.trim();
    const pincode = document.getElementById('custPincode').value.trim();
    const address = document.getElementById('custAddress').value.trim();

    if (!name || !email || !phone || !city || !pincode || !address) {
        alert('Please fill in all details before confirming.');
        return;
    }

    // Build cart
    const cartItems   = [];
    let   totalAmount = 0;

    document.querySelectorAll('.qty').forEach(input => {
        const qty = parseInt(input.value) || 0;
        if (qty > 0) {
            const itemName  = input.getAttribute('data-name');
            const itemPrice = parseInt(input.getAttribute('data-price'));
            const itemTotal = qty * itemPrice;
            totalAmount    += itemTotal;
            cartItems.push(`${itemName} x${qty} = ₹${itemTotal}`);
        }
    });

    if (cartItems.length === 0) {
        alert('Please add at least one product to your order.');
        return;
    }

    // Close bootstrap modal
    const modalEl = document.getElementById('customerModal');
    const modal   = bootstrap.Modal.getInstance(modalEl);
    if (modal) modal.hide();

    // ── Step 1: Create Razorpay order on the server ───────────
    let rzpOrderData;
    try {
        const res = await fetch('api/create-order.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({ amount: totalAmount }),
        });
        rzpOrderData = await res.json();
    } catch (err) {
        alert('Could not reach the server. Please try again.');
        console.error('create-order error:', err);
        return;
    }

    if (!rzpOrderData.success) {
        alert('Order creation failed: ' + rzpOrderData.error);
        return;
    }

    // ── Step 2: Open Razorpay checkout ────────────────────────
    const options = {
        key:         rzpOrderData.key_id,
        amount:      rzpOrderData.amount,       // paise
        currency:    rzpOrderData.currency,
        order_id:    rzpOrderData.order_id,     // required for signature verification
        name:        'Thanga Mani',
        description: 'Peanut Burfi Order',
        image:       'images/logo.png',
        prefill:     { name, contact: phone },
        notes: {
            address, city, pincode,
            items: cartItems.join(', '),
        },
        theme: { color: '#c8a96e' },

        // ── Step 3: Verify payment on the server ───────────────
        handler: async function (response) {
            let verifyData;
            try {
                const res = await fetch('api/verify-payment.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        razorpay_order_id:   options.order_id, // 🔥 FIX
                        razorpay_payment_id: response.razorpay_payment_id,
                        razorpay_signature:  response.razorpay_signature,
                        customer_name:       name,
                        customer_phone:      phone,
                        customer_email:      email,
                        customer_city:       city,
                        customer_pincode:    pincode,
                        customer_address:    address,
                        cart_items:          cartItems,
                        total_amount:        totalAmount,
                    }),
                });
                verifyData = await res.json();
            } catch (err) {
    console.error(err);
    alert('Verify failed: ' + err.message);
}

            if (!verifyData.success) {
                alert('Payment verification failed: ' + verifyData.error);
                return;
            }

            // Show success popup
            showSuccessPopup({
                orderNumber: verifyData.order_number,
                paymentId:   response.razorpay_payment_id,
                totalAmount,
                name,
                cartItems,
            });

            // Reset cart
            document.querySelectorAll('.qty').forEach(i => i.value = '');
            document.getElementById('totalAmount').innerText = 0;
        },

        modal: {
            ondismiss: () => alert('Payment cancelled. Please try again.'),
        },
    };

    new Razorpay(options).open();
}

// ── Success popup ─────────────────────────────────────────────
function showSuccessPopup({ orderNumber, paymentId, totalAmount, name, cartItems }) {
    const itemsHTML = cartItems.map(item => {
        const parts = item.split(' = ');
        return `<div style="display:flex;justify-content:space-between;margin-bottom:6px;">
            <span style="font-size:13px;color:#666;">${parts[0]}</span>
            <span style="font-size:13px;">${parts[1]}</span>
        </div>`;
    }).join('');

    const popup = document.createElement('div');
    popup.innerHTML = `
    <div id="successOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:9999;display:flex;align-items:center;justify-content:center;padding:1rem;">
      <div style="background:#fff;border-radius:16px;padding:2rem;max-width:400px;width:100%;text-align:center;font-family:sans-serif;">
        <div style="width:56px;height:56px;border-radius:50%;background:#EAF3DE;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;">
          <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3B6D11" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
        </div>
        <p style="font-size:18px;font-weight:600;margin:0 0 4px;">Payment Successful!</p>
        <p style="font-size:13px;color:#888;margin:0 0 1.5rem;">Thank you, ${name}!</p>
        <div style="background:#f7f7f5;border-radius:10px;padding:1rem;margin-bottom:1.25rem;text-align:left;">
          <div style="display:flex;justify-content:space-between;margin-bottom:8px;">
            <span style="font-size:12px;color:#888;">Order number</span>
            <span style="font-size:13px;font-weight:600;font-family:monospace;">${orderNumber}</span>
          </div>
          <div style="display:flex;justify-content:space-between;margin-bottom:12px;">
            <span style="font-size:12px;color:#888;">Payment ID</span>
            <span style="font-size:13px;font-family:monospace;">${paymentId}</span>
          </div>
          <hr style="border:none;border-top:1px solid #eee;margin:8px 0;">
          ${itemsHTML}
          <hr style="border:none;border-top:1px solid #eee;margin:8px 0;">
          <div style="display:flex;justify-content:space-between;">
            <span style="font-weight:600;">Total paid</span>
            <span style="font-weight:600;">₹${totalAmount}</span>
          </div>
        </div>
        <p style="font-size:12px;color:#aaa;margin:0 0 1.25rem;">Order confirmation sent to admin via email.</p>
        <button onclick="document.getElementById('successOverlay').remove()"
          style="width:100%;padding:12px;background:#c8a96e;color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;">
          Done
        </button>
      </div>
    </div>`;
    document.body.appendChild(popup);
}