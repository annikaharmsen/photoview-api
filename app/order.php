<?php

namespace App;

use App\Lib\Auth;
use App\Lib\DB;
use App\Lib\Response;
use Exception;

require_once __DIR__ . '/config/autoload.php';

Response::setCORSHeaders();

require_once __DIR__ . '/config/db.php';

$user_id = Auth::requireLogin($pdo);

switch($_SERVER['REQUEST_METHOD']) {
    case 'POST':
        try {
            $pdo->beginTransaction();

            $input = json_decode(file_get_contents('php://input'), true);

            $address_id = createAddress($pdo, $user_id, $input);
            $format_prices = getFormatPrices($pdo);
            $order_total = getCartTotal($pdo, $format_prices, $input);
            $order_id = createOrder($pdo, $user_id, $address_id, $order_total);
            add_order_items($pdo, $order_id, $format_prices, $input);
            clear_cart($pdo, $user_id);
            $client_secret = get_client_secret($order_total, $order_id);

            $pdo->commit();

            Response::ok(['client_secret' => $client_secret]);
        } catch (Exception $e) {
            $pdo->rollBack();
            Response::error('Order processing failed: ' . $e->getMessage(), 500);
        }
}

// inserts address to database; returns address_id
function createAddress($pdo, $user_id, $input) {
    $sql = 'INSERT INTO shipping_addresses
            (user_id, recipient_name, address, city, state_region, postal_code, country)
            VALUES (?, ?, ?, ?, ?, ?, ?)';
    $address = $input['shipping_address'];
    $params = [
        $user_id,
        $address['full_name'],
        $address['address'],
        $address['city'],
        $address['state'],
        $address['zip'],
        'USA']; // IMPROVE: integrate support for international shipping

        // IMPROVE: USPS address validation

    $address_id = DB::insert($pdo, $sql, $params);

    return $address_id;
}

// returns associative array (format_id => format_price)
function getFormatPrices($pdo) {
    $sql = '
        SELECT format_id, price
        FROM formats';
    
    $formats = DB::all($pdo, $sql);

    return array_column($formats, 'price', 'format_id');
}

// returns cart total
function getCartTotal($pdo, $format_prices, $input) {
    $total = 0;

    foreach ($input['cart_items'] as $item) {;
        $total += $item['format']['price'] * $item['quantity'];
    }

    return $total;
}

//create order: user_id, shipping_address_id, total_amount --> order_id
function createOrder($pdo, $user_id, $shipping_address_id, $order_total) {
    $sql = 'INSERT INTO orders
        (user_id, shipping_address_id, total_amount)
        VALUES (?, ?, ?)';
    $params = [$user_id, $shipping_address_id, $order_total];

    $order_id = DB::insert($pdo, $sql, $params);

    return $order_id;
}

//add order items: order_id, photo_id, format_id, quantity, unit_price
function add_order_items($pdo, $order_id, $format_prices, $input) {
    if (empty($input['cart_items'])) {
        return;
    }

    $sql = 'INSERT INTO order_items (order_id, photo_id, format_id, quantity, unit_price) VALUES ';
    $params = [];
    $values = [];

    foreach ($input['cart_items'] as $item) {
        $values[] = '(?, ?, ?, ?, ?)';
        $params[] = $order_id;
        $params[] = $item['photo']['photo_id'];
        $params[] = $item['format']['format_id'];
        $params[] = $item['quantity'];
        $params[] = $item['format']['price'];
    }

    $sql .= implode(', ', $values);

    DB::insert($pdo, $sql, $params);
}

//clear cart
function clear_cart($pdo, $user_id) {
    $sql = 'DELETE FROM cart_items
        WHERE user_id = ?';
    $params = [$user_id];

    DB::run($pdo, $sql, $params);
}

// return dollar value in cents
function to_cents($dollar_amount) {
    return $dollar_amount*100;
}

// get payment intent and return client secret
function get_client_secret($order_total, $order_id) {
    \Stripe\Stripe::setApiKey($_ENV['STRIPE_SECRET_KEY']);

    $payment_intent = \Stripe\PaymentIntent::create([
        'amount' => to_cents($order_total),
        'currency' => 'usd',
        'metadata' => [
            'order_id' => $order_id,
        ],
        ]);
    
    return $payment_intent->client_secret;
}