const crypto = require('crypto');
const Razorpay = require('razorpay');
const admin = require('firebase-admin');

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

module.exports = async (req, res) => {
    if (req.method !== 'POST') {
        return res.status(405).json({ error: 'Method not allowed' });
    }

    const { totalAmount } = req.body;
    if (!totalAmount || totalAmount <= 0) {
        return res.status(400).json({ error: 'Invalid amount' });
    }

    try {
        const orderNumber = generateOrderNumber();
        const order = await razorpay.orders.create({
            amount: Math.round(totalAmount * 100),
            currency: 'INR',
            receipt: orderNumber,
        });

        res.json({
            order_id: order.id,
            order_number: orderNumber,
            key_id: process.env.RAZORPAY_KEY_ID,
            amount: totalAmount,
        });
    } catch (err) {
        console.error('Create order error:', err);
        res.status(500).json({ error: 'Failed to create Razorpay order' });
    }
};