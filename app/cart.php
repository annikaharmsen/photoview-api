<?php

namespace App;

use App\Lib\Auth;
use App\Lib\DB;
use App\Lib\Response;
use PDOException;
use Exception;

// require dependencies
require_once __DIR__ . '/config/autoload.php';

Response::setCORSHeaders();

require_once __DIR__ . '/config/db.php';

// verify user is logged in
$user_id   = Auth::requireLogin($pdo);

// get request method
$method = $_SERVER["REQUEST_METHOD"];

// REQUEST METHOD ROUTER
switch ($method) {

    case "POST": // add item
        postAddItem($pdo, $user_id);
        break;

    case "GET": // get all items
        getItems($pdo, $user_id);
        break;

    case "PATCH": // update item quantity
    case "PUT":
        patchQuantity($pdo, $user_id);
        break;

    case "DELETE": // remove item
        deleteItem($pdo, $user_id);
        break;

    default: 
        Response::error("Not a valid HTTP method for server endpoint.", 405);
}

function postAddItem($pdo, $user_id) {

    // validate variables
    $photo_id  = $_POST['photo_id']   ?? null;
    $format_id = $_POST['format_id']  ?? null;
    $quantity  = $_POST['quantity']   ?? null;

    if (!$photo_id || !$format_id || !is_numeric($quantity) || (int)$quantity < 1) {
        Response::error("Invalid photo ID, format ID, or quantity.", 400);
    }

    try {
        // if item already exists, update quantity
        $existing_item = findExistingItem($pdo, $user_id, $photo_id, $format_id);

        if ($existing_item) { 

            $new_qty = (int)$existing_item["quantity"] + (int)$quantity;

            updateQuantity($pdo, $existing_item["cart_item_id"], $new_qty); // sends response
        }
        
        $sql = "
            INSERT INTO cart_items (user_id, photo_id, format_id, quantity)
            VALUES (:user_id, :photo_id, :format_id, :quantity)
        ";
        DB::insert($pdo, $sql, [
            'user_id'   => $user_id,
            'photo_id'  => $photo_id,
            'format_id' => $format_id,
            'quantity'  => (int)$quantity
        ]);

        Response::ok([
            'message' => 'Item added to cart.'
        ]);

    } catch (PDOException $e) {
        Response::error("Database error: " . $e->getMessage(), 500);
    }
}

// HELPER FUNCTIONS

function getItems($pdo, $user_id) {
    try {
        $sql = "
            SELECT
                ci.cart_item_id,
                ci.photo_id,
                ci.format_id,
                ci.quantity,
                p.image_url,
                p.description AS photo_desc,
                f.name       AS format_name,
                f.price,
                f.description AS format_desc
            FROM cart_items ci
            JOIN photos p   ON ci.photo_id  = p.photo_id
            JOIN formats f  ON ci.format_id = f.format_id
            WHERE ci.user_id = :user_id
            ORDER BY ci.cart_item_id DESC
        ";
        
        $items = DB::all($pdo, $sql, ['user_id' => $user_id]);

        // convert prices to float
        foreach ($items as &$item) {
            $item = [
                'cart_item_id' => $item['cart_item_id'],
                'quantity' => $item['quantity'],
                'photo' => [
                    'photo_id' => $item['photo_id'],
                    'image_url' => $item['image_url'],
                    'description' => $item['photo_desc']
                ],
                'format' => [
                    'format_id' => $item['format_id'],
                    'name' => $item['format_name'],
                    'price' => floatval($item['price']),
                    'description' => $item['format_desc']
                ]
                ];
        }

        Response::ok([
            'items' => $items
        ]);

    } catch (PDOException $e) {
        Response::error('Database error: ' . $e->getMessage(), 500);
    }
}

function patchQuantity($pdo, $user_id) {

    try {

        $data = json_decode(file_get_contents("php://input"), true);

        // basic validation 
        $cart_item_id = $data["cart_item_id"] ?? null;
        $quantity = $data["quantity"] ?? null;

        if (!$cart_item_id || !is_numeric($cart_item_id) || !is_numeric($quantity) || $quantity < 1) {
            http_response_code(400);
            exit("Invalid or missing cart_item_id or quantity.");
        }

        updateQuantity($pdo, $cart_item_id,$quantity);

    } catch (PDOException $e) {
        http_response_code(500);
        exit("Internal server error: " . $e->getMessage());
        
    } catch (Exception $e){
        http_response_code(400);
        exit("Invalid data: " . $e->getMessage());

    }

}

function deleteItem($pdo, $user_id) {

    try {

        // Read the request body
        $data = json_decode(file_get_contents("php://input"), true);

        // Validate the input
        $item_id = $data["cart_item_id"] ?? null;

        if (!$item_id || !is_numeric($item_id)) {
            http_response_code(400);
            exit("Invalid or missing cart_item_id.");
        }

        $sql = "
            DELETE FROM cart_items
            WHERE cart_item_id = :?
            AND user_id      = :?
        ";

        $params = [$data["cart_item_id"], $user_id];

        $row_count = DB::run($pdo, $sql, $params);

        if ($row_count < 1) {
            throw new Exception("No rows deleted.");
        };

        Response::ok();

    } catch (PDOException $e) {

        http_response_code(500);
        exit("Internal server error: " . $e->getMessage());

    } catch (Exception $e){

        http_response_code(400);
        exit("Invalid data: " . $e->getMessage());

    }
}
function findExistingItem($pdo, $user_id, $photo_id, $format_id) {
    $sql = "
        SELECT cart_item_id, quantity
        FROM cart_items
        WHERE user_id = :user_id
        AND photo_id = :photo_id
        AND format_id = :format_id
        LIMIT 1
    ";

    $params = [
        'user_id'   => $user_id,
        'photo_id'  => $photo_id,
        'format_id' => $format_id
    ];

    $cart_item = DB::one($pdo, $sql, $params);
    
    return $cart_item;
}

function updateQuantity($pdo, $cartItemId, $newQuantity) {

    try {

        // basic validation
        if ($newQuantity < 1) {
            http_response_code(400);
            exit("Quantity must be at least 1.");
        }

        $sql = "
            UPDATE cart_items
            SET quantity = :quantity
            WHERE cart_item_id = :id
        ";

        $params = [
            'quantity' => $newQuantity,
            'id'       => $cartItemId
        ];

        DB::run($pdo, $sql, $params);

        Response::ok(['message' => 'Quantity updated in cart.']);

    } catch (PDOException $e) {

        Response::error("Internal server error: " . $e->getMessage(), 500);

    }
}