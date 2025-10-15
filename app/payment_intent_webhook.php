<?php

namespace App;

use App\Lib\DB;
use App\Lib\Response;
use Exception;

// set up sdk
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config/db.php';

\Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

// read event data
$payload = @file_get_contents("php://input");
$event = null;

try {
    $event = \Stripe\Event::constructFrom(json_decode($payload, true));
} catch(\UnexpectedValueException $e) {
    Response::error("Webhook error while parsing basic request.", 400);
}

// handle transaction status updates
switch ($event->type) {
    case 'payment_intent.succeeded':
        // IMPROVE: let user know payment has succeeded-- maybe email...?
    case 'payment_intent.failed':
        // IMPROVE: or failed!
        $payment_intent = $event->data->object;
        record_transaction($pdo, $payment_intent);
        update_order_status($pdo, $payment_intent);
        break;
    default:
        error_log('Received unknown event type: ' . $event->type);
}

http_response_code(200);


/*
* PAYMENT INTENT OBJECT
*
* {
*  "id": "pi_3NF7RzKiyf5dfgdfg",
*  "amount": 5000,
*  "currency": "usd",
*  "status": "succeeded",
*  "payment_method": "pm_1NNNnZLu123abc",
*  "client_secret": "pi_3NF7..._secret_...",
*  "created": 1704500000,
*  "confirmation_method": "automatic",
*  "metadata": {
*    "order_id": "12345"
*  },
*  "charges": {
*    "data": [
*      {
*        "id": "ch_3NF7Rz...",
*        "amount": 5000,
*        "status": "succeeded"
*        "payment_method_details": {
*          "card": {
*            "brand": "visa",
*            "last4": "4242",
*            "exp_month": 12,
*            "exp_year": 2026
*          }
*        }
*      }
*    ]
*  }
*}
*/

function record_transaction($pdo, $payment_intent) {
    $sql = 'INSERT INTO transactions (
            payment_provider,
            payment_id,
            payment_method,
            brand,
            last4,
            exp_month,
            exp_year,
            txn_type,
            amount,
            currency,
            status,
            order_id
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    $card = $payment_intent->charges->data[0]->payment_method_details->card ?? null;

    if (!$card) {
        throw new Exception('Expected card details, none found.');
    }

    $params = [
        'stripe',                               // payment_provider
        $payment_intent->id,                     // payment_id
        'card',                                 // payment_method
        $card->brand,                           // brand
        $card->last4,                           // last4
        $card->exp_month,                       // exp_month
        $card->exp_year,                        // exp_year
        'charge',                               // txn_type ('charge'/'refund')
        $payment_intent->amount,                 // amount (in cents)
        $payment_intent->currency,               // currency
        $payment_intent->status,                 // status
        $payment_intent->metadata->order_id      // order_id
    ];

    DB::insert($pdo, $sql, $params);
}

function update_order_status($pdo, $payment_intent) {
    $sql = 'UPDATE orders
            SET status = ?, payment_status = ?
            WHERE order_id = ?';

    $params = [
        $payment_intent->status == 'succeeded' ? 'placed' : 'missing payment',
        $payment_intent->status == 'succeeded' ? 'paid' : 'failed',
        $payment_intent->metadata->order_id
    ];

    DB::run($pdo, $sql, $params);
}
