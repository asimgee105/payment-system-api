<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>Payment System Test</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 30px;
            font-family: Arial, sans-serif;
            background: #0f172a;
            color: white;
        }

        .container {
            max-width: 1000px;
            margin: auto;
        }

        .card {
            background: #1e293b;
            padding: 24px;
            border-radius: 15px;
            margin-bottom: 20px;
        }

        input,
        select {
            width: 100%;
            padding: 12px;
            margin: 6px 0;
            border-radius: 8px;
            border: 1px solid #475569;
            background: #0f172a;
            color: white;
        }

        button {
            padding: 11px 16px;
            border: 0;
            border-radius: 8px;
            background: #2563eb;
            color: white;
            cursor: pointer;
            margin: 5px;
        }

        pre {
            white-space: pre-wrap;
            word-break: break-word;
            background: #020617;
            padding: 20px;
            border-radius: 10px;
            min-height: 150px;
        }

        .grid {
            display: grid;
            grid-template-columns:
                repeat(2, 1fr);
            gap: 20px;
        }

        @media(max-width:700px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

<div class="container">

    <h1>
        Payment System Test Panel
    </h1>

    <div class="grid">

        <div class="card">

            <h2>Register</h2>

            <input id="name" placeholder="Name">

            <input
                id="email"
                type="email"
                placeholder="Email"
            >

            <input
                id="password"
                type="password"
                value="password123"
            >

            <button onclick="registerUser()">
                Register
            </button>

        </div>

        <div class="card">

            <h2>Login</h2>

            <input
                id="loginEmail"
                type="email"
                placeholder="Email"
            >

            <input
                id="loginPassword"
                type="password"
                value="password123"
            >

            <button onclick="loginUser()">
                Login
            </button>

        </div>

    </div>

    <div class="card">

        <h2>Create Payment</h2>

        <input
            id="amount"
            type="number"
            value="1050"
        >

        <input
            id="currency"
            value="USD"
        >

        <input
            id="paymentKey"
            value="payment-test-1"
            placeholder="Idempotency Key"
        >

        <button onclick="createPayment()">
            Create Payment
        </button>

        <button onclick="listPayments()">
            Payment List
        </button>

    </div>

    <div class="card">

        <h2>Refund</h2>

        <input
            id="paymentId"
            type="number"
            placeholder="Payment ID"
        >

        <input
            id="refundAmount"
            type="number"
            value="500"
        >

        <input
            id="refundKey"
            value="refund-test-1"
        >

        <button onclick="createRefund()">
            Refund
        </button>

    </div>

    <div class="card">

        <h2>Response</h2>

        <pre id="output">
Response yahan show hogi...
        </pre>

    </div>

</div>

<script>
let token =
    localStorage.getItem('payment_token') ?? '';

const output =
    document.getElementById('output');

function show(data) {
    output.textContent =
        JSON.stringify(
            data,
            null,
            2
        );
}

async function registerUser() {

    const response =
        await fetch('/api/register', {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json',

                'Accept':
                    'application/json',
            },

            body: JSON.stringify({
                name:
                    document.getElementById('name').value,

                email:
                    document.getElementById('email').value,

                password:
                    document.getElementById('password').value,

                password_confirmation:
                    document.getElementById('password').value,
            })
        });

    const data =
        await response.json();

    if (data.token) {
        token = data.token;

        localStorage.setItem(
            'payment_token',
            token
        );
    }

    show(data);
}

async function loginUser() {

    const response =
        await fetch('/api/login', {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json',

                'Accept':
                    'application/json',
            },

            body: JSON.stringify({
                email:
                    document
                        .getElementById(
                            'loginEmail'
                        )
                        .value,

                password:
                    document
                        .getElementById(
                            'loginPassword'
                        )
                        .value,
            })
        });

    const data =
        await response.json();

    if (data.token) {
        token = data.token;

        localStorage.setItem(
            'payment_token',
            token
        );
    }

    show(data);
}

async function createPayment() {

    const response =
        await fetch('/api/payments', {
            method: 'POST',

            headers: {
                'Content-Type':
                    'application/json',

                'Accept':
                    'application/json',

                'Authorization':
                    `Bearer ${token}`,

                'Idempotency-Key':
                    document
                        .getElementById(
                            'paymentKey'
                        )
                        .value,
            },

            body: JSON.stringify({
                amount:
                    Number(
                        document
                            .getElementById(
                                'amount'
                            )
                            .value
                    ),

                currency:
                    document
                        .getElementById(
                            'currency'
                        )
                        .value,
            })
        });

    show(
        await response.json()
    );
}

async function listPayments() {

    const response =
        await fetch('/api/payments', {
            headers: {
                'Accept':
                    'application/json',

                'Authorization':
                    `Bearer ${token}`,
            }
        });

    show(
        await response.json()
    );
}

async function createRefund() {

    const paymentId =
        document
            .getElementById(
                'paymentId'
            )
            .value;

    const response =
        await fetch(
            `/api/payments/${paymentId}/refunds`,
            {
                method: 'POST',

                headers: {
                    'Content-Type':
                        'application/json',

                    'Accept':
                        'application/json',

                    'Authorization':
                        `Bearer ${token}`,

                    'Idempotency-Key':
                        document
                            .getElementById(
                                'refundKey'
                            )
                            .value,
                },

                body: JSON.stringify({
                    amount:
                        Number(
                            document
                                .getElementById(
                                    'refundAmount'
                                )
                                .value
                        ),
                })
            }
        );

    show(
        await response.json()
    );
}
</script>

</body>
</html>