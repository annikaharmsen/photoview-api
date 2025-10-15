<?php

namespace App;

use App\Lib\Response;

require_once __DIR__ . '/config/stripe.php';

// Return Stripe public key
Response::ok($public_key);