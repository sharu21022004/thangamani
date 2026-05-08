const crypto = require('crypto');
const Razorpay = require('razorpay');
const admin = require('firebase-admin');
const nodemailer = require('nodemailer');

// Firebase init
if (!admin.apps.length) {
    admin.initializeApp({
        credential: admin.credential.cert({
            projectId: process.env.FIREBASE_PROJECT_ID,
            clientEmail: process.env.FIREBASE_CLIENT_EMAIL,
            privateKey: process.env.FIREBASE_PRIVATE_KEY.replace(/\\n/g, '\n'),
        }),
    });
}
const db = admin.firestore();

// Razorpay init
const razorpay = new Razorpay({
    key_id: process.env.RAZORPAY_KEY_ID,
    key_secret: process.env.RAZORPAY_KEY_SECRET,
});

// Helper
function generateOrderNumber() {
    const d = new Date();
    const date = d.getFullYear().toString()
        + String(d.getMonth() + 1).padStart(2, '0')
        + String(d.getDate()).padStart(2, '0');
    const rand = String(Math.floor(Math.random() * 9000) + 1000);
    return `TM-${date}-${rand}`;
}

async function sendOrderEmail(order) {
    const transporter = nodemailer.createTransport({
        host: process.env.SMTP_HOST,
        port: parseInt(process.env.SMTP_PORT),
        secure: false,
        auth: {
            user: process.env.SMTP_USERNAME,
            pass: process.env.SMTP_PASSWORD,
        },
    });

    try {
        // Admin email
        const itemsHtml = order.cartItems.map(item => `<li>${item}</li>`).join('');
        const adminMailOptions = {
            from: process.env.SMTP_FROM,
            to: process.env.ADMIN_EMAIL,
            subject: 'New Order - ' + order.orderNumber,
            html: `
<div style="font-family:Arial;background:#f5f5f5;padding:20px;">
  <div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden">
    <div style="background:#c8a96e;color:#fff;padding:15px;text-align:center">
      <h2 style="margin:0;">Thanga Mani</h2>
      <p style="margin:5px 0 0;">New Order Received</p>
    </div>
    <div style="padding:20px">
      <h3>Order Details</h3>
      <p><b>Order ID:</b> ${order.orderNumber}</p>
      <p><b>Payment ID:</b> ${order.paymentId}</p>
      <p><b>Total Amount:</b> ₹${order.totalAmount}</p>
      <hr>
      <h3>Customer Details</h3>
      <p>${order.customer.name}<br>${order.customer.phone}<br>${order.customer.email}<br>${order.customer.address}</p>
      <hr>
      <h3>Items</h3>
      <ul>${itemsHtml}</ul>
    </div>
    <div style="background:#eee;padding:10px;text-align:center;font-size:12px">
      Thanga Mani Admin Panel
    </div>
  </div>
</div>
            `,
        };

        await transporter.sendMail(adminMailOptions);

        // Customer email
        const customerMailOptions = {
            from: process.env.SMTP_FROM,
            to: order.customer.email,
            subject: 'Order Confirmation - Thanga Mani',
            html: `
<div style="font-family:Arial;background:#f5f5f5;padding:20px;">
  <div style="max-width:600px;margin:auto;background:#fff;border-radius:8px;overflow:hidden;text-align:center">
    <div style="background:#c8a96e;color:#fff;padding:20px">
      <h2 style="margin:0;">Thank You!</h2>
    </div>
    <div style="padding:20px">
      <p>Hi <b>${order.customer.name}</b>,</p>
      <p>Your order has been successfully placed.</p>
      <div style="background:#f9f9f9;padding:15px;border-radius:6px;margin:15px 0">
        <p><b>Order Number:</b> ${order.orderNumber}</p>
        <p><b>Total Paid:</b> ₹${order.totalAmount}</p>
      </div>
      <p>We will contact you shortly regarding your order.</p>
      <p style="margin-top:20px">Thank you for choosing <b>Thanga Mani</b></p>
    </div>
    <div style="background:#eee;padding:10px;font-size:12px">
      © ${new Date().getFullYear()} Thanga Mani
    </div>
  </div>
</div>
            `,
        };

        await transporter.sendMail(customerMailOptions);

        return { success: true };
    } catch (error) {
        console.error('Email error:', error);
        return { success: false, error: error.message };
    }
}

module.exports = async (req, res) => {
    if (req.method !== 'POST') {
        return res.status(405).json({ error: 'Method not allowed' });
    }

    const {
        razorpay_order_id,
        razorpay_payment_id,
        razorpay_signature,
        customer_name,
        customer_phone,
        customer_email,
        customer_city,
        customer_pincode,
        customer_address,
        cart_items,
        total_amount,
    } = req.body;

    // Signature check
    const body = razorpay_order_id + '|' + razorpay_payment_id;
    const expected = crypto
        .createHmac('sha256', process.env.RAZORPAY_KEY_SECRET)
        .update(body)
        .digest('hex');

    if (expected !== razorpay_signature) {
        console.warn('Signature mismatch!', { expected, razorpay_signature });
        return res.status(400).json({ error: 'Payment verification failed' });
    }

    // Generate order number
    const orderNumber = generateOrderNumber();

    // Save to Firestore
    try {
        const orderData = {
            orderNumber,
            paymentId: razorpay_payment_id,
            razorpayOrderId: razorpay_order_id,
            totalAmount: total_amount,
            customer: {
                name: customer_name,
                phone: customer_phone,
                email: customer_email,
                city: customer_city,
                pincode: customer_pincode,
                address: customer_address,
            },
            cartItems: cart_items,
            status: 'paid',
            createdAt: new Date().toISOString(),
        };

        await db.collection('orders').doc(orderNumber).set(orderData);
        console.log('✅ Order saved to Firebase:', orderNumber);

        // Send emails
        const emailResult = await sendOrderEmail(orderData);
        console.log('Email sent:', emailResult);

        res.json({ success: true, order_number: orderNumber, email_sent: emailResult.success });
    } catch (err) {
        console.error('Firebase save error:', err);
        res.json({ success: true, order_number: orderNumber, dbError: true });
    }
};