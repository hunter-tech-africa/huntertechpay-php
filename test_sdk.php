<?php
/**
 * HunterTechPay PHP SDK - Test Example
 *
 * This example demonstrates how to use the HunterTechPay SDK with PHP
 *
 * Usage:
 *   php test_sdk.php
 */

require_once __DIR__ . '/HunterTechPay.php';

// Configuration
$config = [
    'apiKey' => getenv('HTP_API_KEY') ?: 'htp_live_your_api_key',
    'secretKey' => getenv('HTP_SECRET_KEY') ?: 'sk_live_your_secret_key',
    'baseUrl' => getenv('HTP_BASE_URL') ?: 'http://localhost:8007',
    'timeout' => 30
];

// Test data
$testData = [
    'phone' => getenv('TEST_PHONE') ?: '+237690000000',
    'country' => 'CM',
    'currency' => 'XAF',
    'cashinServiceCode' => 'HT_PAIEMENTMARCHAND_MTN_CM',  // MTN Cameroon CASHIN
    'cashoutServiceCode' => 'HT_PAYOUTMARCHAND_MTN_CM'    // MTN Cameroon CASHOUT
];

function main() {
    global $config, $testData;

    echo "🚀 HunterTechPay PHP SDK - Test Suite\n\n";

    try {
        // Initialize SDK
        $hunter = new HunterTechPay(
            $config['apiKey'],
            $config['secretKey'],
            $config['baseUrl'],
            $config['timeout']
        );
        echo "✅ SDK initialized\n\n";

        // ==================== Test 1: Get Providers ====================
        echo "📋 Test 1: Get Providers for Cameroon\n";
        $providers = $hunter->getProviders($testData['country']);
        echo "✅ Found {$providers['total_providers']} providers\n";
        echo "Providers:\n";
        foreach ($providers['providers'] as $p) {
            echo "  - {$p['provider_display_name']}\n";
            echo "    CASHIN: {$p['cashin_service_code']}\n";
            echo "    CASHOUT: {$p['cashout_service_code']}\n";
        }
        echo "\n";

        // ==================== Test 2: Deposit (CASHIN) ====================
        echo "💰 Test 2: Deposit (CASHIN) - 100 XAF\n";
        $depositResult = $hunter->deposit([
            'amount' => 100.0,  // Amount in main currency units
            'currency' => $testData['currency'],
            'country' => $testData['country'],
            'phone' => $testData['phone'],
            'service_code' => $testData['cashinServiceCode'],
            'partner_id' => 'PHP_DEPOSIT_' . time(),
            'description' => 'Test deposit from PHP SDK'
        ]);

        echo "✅ Deposit initiated successfully\n";
        echo "  Transaction ID: {$depositResult['transaction_id']}\n";
        echo "  Partner ID: {$depositResult['partner_id']}\n";
        echo "  Status: {$depositResult['status']}\n";
        echo "  Amount: {$depositResult['amount']} {$depositResult['currency']}\n";
        echo "  Fees: {$depositResult['fees']} {$depositResult['currency']}\n";
        echo "  Total: {$depositResult['total_amount']} {$depositResult['currency']}\n";
        echo "\n";

        // Wait a bit before checking status
        sleep(2);

        // ==================== Test 3: Check Status ====================
        echo "🔍 Test 3: Check Transaction Status\n";
        $status = $hunter->checkStatus($depositResult['partner_id'], 'partner_id');
        echo "✅ Status retrieved\n";
        echo "  Transaction ID: {$status['transaction_id']}\n";
        echo "  Status: {$status['status']}\n";
        echo "  Amount: {$status['amount']} {$status['currency']}\n";
        echo "  Created: {$status['created_at']}\n";
        echo "\n";

        // ==================== Test 4: Withdraw (CASHOUT) ====================
        echo "💸 Test 4: Withdraw (CASHOUT) - 50 XAF\n";
        $withdrawResult = $hunter->withdraw([
            'amount' => 50.0,  // Amount in main currency units
            'currency' => $testData['currency'],
            'country' => $testData['country'],
            'phone' => $testData['phone'],
            'service_code' => $testData['cashoutServiceCode'],
            'partner_id' => 'PHP_WITHDRAW_' . time(),
            'description' => 'Test withdrawal from PHP SDK'
        ]);

        echo "✅ Withdrawal initiated successfully\n";
        echo "  Transaction ID: {$withdrawResult['transaction_id']}\n";
        echo "  Partner ID: {$withdrawResult['partner_id']}\n";
        echo "  Status: {$withdrawResult['status']}\n";
        echo "  Amount: {$withdrawResult['amount']} {$withdrawResult['currency']}\n";
        echo "  Fees: {$withdrawResult['fees']} {$withdrawResult['currency']}\n";
        echo "\n";

        // ==================== Test 5: List Transactions ====================
        echo "📜 Test 5: List Recent Transactions\n";
        $transactions = $hunter->listTransactions([
            'page' => 1,
            'page_size' => 5
        ]);

        echo "✅ Found {$transactions['total']} transactions\n";
        echo "Recent transactions:\n";
        foreach (array_slice($transactions['transactions'], 0, 3) as $tx) {
            echo "  - {$tx['transaction_id']}: {$tx['amount']} {$tx['currency']} ({$tx['status']})\n";
        }
        echo "\n";

        // ==================== Test 6: Get Balance ====================
        echo "💼 Test 6: Get Wallet Balance\n";
        $balance = $hunter->getBalance();
        echo "✅ Balance retrieved\n";
        foreach ($balance['wallets'] as $wallet) {
            echo "  {$wallet['currency']}: {$wallet['available_balance_decimal']} available\n";
        }
        echo "\n";

        echo "✅ All tests completed successfully!\n";

    } catch (HunterTechPayError $e) {
        echo "❌ Error: {$e->getMessage()}\n";
        if ($e->data) {
            echo "Details: " . json_encode($e->data, JSON_PRETTY_PRINT) . "\n";
        }
        exit(1);
    } catch (Exception $e) {
        echo "❌ Unexpected error: {$e->getMessage()}\n";
        exit(1);
    }
}

// Run tests
main();
