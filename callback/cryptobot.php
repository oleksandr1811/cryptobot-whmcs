<?php
// WHMCS callback for receiving webhook from CryptoBot
// Place this file in modules/gateways/callback/cryptobot.php

// --- Settings ---
$whmcsRoot = dirname(__DIR__, 3); // path to WHMCS root (3 levels up from modules/gateways/callback/)

// Try multiple possible paths for WHMCS root
$possiblePaths = [
    dirname(__DIR__, 3), // modules/gateways/callback -> 3 levels up
    dirname(__DIR__, 2), // modules/gateways/callback -> 2 levels up  
    dirname(__FILE__, 4), // alternative calculation
    $_SERVER['DOCUMENT_ROOT'] // web root
];

$whmcsRoot = null;
foreach ($possiblePaths as $path) {
    if (file_exists($path . '/init.php')) {
        $whmcsRoot = $path;
        break;
    }
}

if (!$whmcsRoot) {
    die('WHMCS root directory not found');
}

require_once $whmcsRoot . '/init.php';
require_once $whmcsRoot . '/includes/gatewayfunctions.php';
require_once $whmcsRoot . '/includes/invoicefunctions.php';



// Get your API Token from module settings
$gatewayParams = getGatewayVariables('cryptobot');
$apiToken = $gatewayParams['apiToken'] ?? '';

// --- Get webhook data ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Check if JSON is valid
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo 'Invalid JSON';
    exit;
}

// --- Handle invoice_paid event ---
if (isset($data['update_type']) && $data['update_type'] === 'invoice_paid') {
    $payload = $data['payload'] ?? [];
    $invoiceId = $payload['payload'] ?? null; // payload is invoiceid from WHMCS
    $amount = $payload['amount'] ?? 0;
    $asset = $payload['asset'] ?? '';
    $transactionId = $payload['invoice_id'] ?? ($payload['id'] ?? uniqid('cryptobot_'));

    if ($invoiceId && $amount > 0) {
        // Validate that invoice exists and is unpaid
        $invoice = localAPI('GetInvoice', ['invoiceid' => $invoiceId]);
        
        if ($invoice['result'] === 'success' && $invoice['status'] !== 'Paid') {
            // Add payment to WHMCS invoice
            $result = addInvoicePayment(
                $invoiceId, // WHMCS invoice ID
                $transactionId, // Unique transaction ID
                $amount, // Amount
                0, // Fee (0)
                'cryptobot' // Gateway name
            );
            
            if ($result) {
                http_response_code(200);
                echo 'Payment processed successfully';
                exit;
            } else {
                http_response_code(500);
                echo 'Failed to process payment';
                exit;
            }
        } else {
            http_response_code(400);
            echo 'Invoice not found or already paid';
            exit;
        }
    } else {
        http_response_code(400);
        echo 'Invalid invoice ID or amount';
        exit;
    }
}

http_response_code(400);
echo 'Unsupported webhook type';
?>