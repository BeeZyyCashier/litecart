<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    define('REQUIRE_POST_TOKEN', false); // Allow unsigned external incoming POST data
    require_once('../../includes/app_header.inc.php');

    $postedData = json_decode(file_get_contents('php://input'), true);
    if (!is_array($postedData)) {
        http_response_code(400);
        die('Invalid IPN');
    }

    // Get Order From Database
    $orders_query = database::query(
        "SELECT * from " . DB_TABLE_ORDERS . " WHERE id = '" . database::input($postedData['reference']) . "'    limit 1");

    if (!$order = database::fetch($orders_query)) {
        http_response_code(404);
        die('Order not found');
    }

    // Get the order's payment option
    list($module_id, $option_id) = explode(':', $order['payment_option_id']);

    // Pass the call to the payment module's method callback()
    $payment = new mod_payment();
    $payment->run('callback', $module_id, $order);

} else {
    http_response_code(400);
    die('Bad Request');
}
