<?php

namespace App;


use App\Lib\DB;
use App\Lib\Response;

require_once __DIR__ . '/config/autoload.php';
require_once __DIR__ . '/config/db.php';

Response::setCORSHeaders();

$sql = "SELECT format_id, name, price
    FROM formats
    ORDER BY price ASC";

$formats = DB::all($pdo, $sql);

Response::ok(['formats' => $formats]);