<?php
/**
 * WHMCS Sample Payment Gateway Module
 *
 * Payment Gateway modules allow you to integrate payment solutions with the
 * WHMCS platform.
 *
 * This sample file demonstrates how a payment gateway module for WHMCS should
 * be structured and all supported functionality it can contain.
 *
 * Within the module itself, all functions must be prefixed with the module
 * filename, followed by an underscore, and then the function name. For this
 * example file, the filename is "gatewaymodule" and therefore all functions
 * begin "gatewaymodule_".
 *
 * If your module or third party API does not support a given function, you
 * should not define that function within your module. Only the _config
 * function is required.
 *
 * For more information, please refer to the online documentation.
 *
 * @see https://developers.whmcs.com/payment-gateways/
 *
 * @copyright Copyright (c) WHMCS Limited 2017
 * @license http://www.whmcs.com/license/ WHMCS Eula
 */

if (!defined("WHMCS")) {
    die("This file cannot be accessed directly");
}

/**
 * Module meta data
 */
function cryptobot_MetaData()
{
    return array(
        'DisplayName' => 'CryptoBot (Telegram)',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    );
}

/**
 * Module configuration
 */
function cryptobot_config()
{
    return array(
        'FriendlyName' => array(
            'Type' => 'System',
            'Value' => 'CryptoBot (Telegram)',
        ),
        'apiToken' => array(
            'FriendlyName' => 'Crypto Pay API Token',
            'Type' => 'text',
            'Size' => '60',
            'Description' => 'Enter your API Token from @CryptoBot',
        ),
        'exchangeRate' => array(
            'FriendlyName' => 'Rate',
            'Type' => 'text',
            'Size' => '20',
            'Description' => 'Enter the current rate (default 1)',
            'Default' => '1',
        ),
        'testMode' => array(
            'FriendlyName' => 'Test Mode',
            'Type' => 'yesno',
            'Description' => 'Use test network',
        ),
    );
}

/**
 * Generate payment button via CryptoBot
 */
function cryptobot_link($params)
{
    $apiToken = $params['apiToken'];
    $testMode = $params['testMode'];
    $invoiceId = $params['invoiceid'];
    $amount = $params['amount'];
    $exchangeRate = $params['exchangeRate'];
    $description = $params['description'];
    $returnUrl = $params['returnurl'];

    // Calculate the crypto amount based on the exchange rate
    $cryptoAmount = $amount * $exchangeRate;

    $apiUrl = $testMode
        ? 'https://testnet-pay.crypt.bot/api/createInvoice'
        : 'https://pay.crypt.bot/api/createInvoice';

    $postData = array(
        'currency_type' => 'fiat',
        'amount' => $cryptoAmount,
        'fiat' => 'USD',
        'description' => $description,
        'payload' => $invoiceId,
        'paid_btn_name' => 'viewItem',
        'paid_btn_url' => $returnUrl,
    );

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Crypto-Pay-API-Token: ' . $apiToken
    ));
    $response = curl_exec($ch);
    $curlError = curl_error($ch);
    curl_close($ch);

    $result = json_decode($response, true);

    if (isset($result['ok']) && $result['ok'] && isset($result['result']['bot_invoice_url'])) {
        $payUrl = $result['result']['bot_invoice_url'];
        return '<a href="' . htmlspecialchars($payUrl) . '" target="_blank" class="btn btn-primary">Pay with CryptoBot</a>';
    } else {
        $errorMsg = isset($result['error']) ? $result['error'] : ($curlError ?: 'Unknown error');
        return '<div class="alert alert-danger">Invoice creation error: ' . htmlspecialchars($errorMsg) . '</div>';
    }
}