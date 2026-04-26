# HunterTechPay SDK - PHP

Official SDK for integrating HunterTechPay into your PHP applications.

**Version**: 1.1.0
**Last Updated**: March 14, 2026

---

## Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Available Methods](#available-methods)
- [Complete Examples](#complete-examples)
- [Security](#security)
- [Error Handling](#error-handling)
- [Support](#support)

---

## Installation

Copy the `HunterTechPay.php` file into your project.

```php
<?php
require_once 'HunterTechPay.php';
```

---

## Quick Start

### Initialization

```php
<?php
require_once 'HunterTechPay.php';

$hunter = new HunterTechPay(
    'htp_live_abc123...',  // API Key provided by HunterTechPay
    'sk_live_xyz789...',   // Secret Key (NEVER expose on client side!)
    'https://api.huntertechpay.com'  // Production URL
);
?>
```

---

## Available Methods

### 1. Get Available Providers

```php
<?php
$providers = $hunter->getProviders('CM');

print_r($providers);
/*
Array(
    'success' => true,
    'country_code' => 'CM',
    'currency' => 'XAF',
    'providers' => Array(
        Array(
            'provider_code' => 'orange_money',
            'name' => 'Orange Money Cameroun',
            'cashin_service_code' => 'OM_CM_CASHIN',   // ✅ Code for deposit
            'cashout_service_code' => 'OM_CM_CASHOUT', // ✅ Code for withdrawal
            'supports_cashin' => true,
            'supports_cashout' => true,
            'logo_url' => 'https://...',
            'is_active' => true
        )
    )
)
*/
?>
```

---

### 2. Deposit (CASHIN) - Mobile Money → Wallet

Transfer money from a mobile money account to the HunterTechPay wallet.

```php
<?php
$deposit = $hunter->deposit([
    'amount' => 5000,                    // Amount in XAF
    'currency' => 'XAF',                 // Currency (must match country)
    'country' => 'CM',                   // Country code (CM, SN, CI, etc.)
    'phone' => '+237690000000',          // Customer phone number
    'provider' => 'orange_money',        // Selected provider
    'reference' => 'DEPOSIT_' . time(),  // Your unique reference
    'description' => 'Deposit to wallet',   // Description (optional)
    'callback_url' => 'https://mysite.com/webhook.php',  // Webhook (optional)
]);

echo 'Deposit initiated:' . PHP_EOL;
echo 'Transaction ID: ' . $deposit['transaction_id'] . PHP_EOL;
echo 'Status: ' . $deposit['status'] . PHP_EOL;  // 'pending', 'success', etc.
?>
```

**Flow**:
1. User initiates a deposit
2. System automatically uses the CASHIN service code (e.g., `OM_CM_CASHIN`)
3. User receives a USSD push to confirm payment
4. Amount is debited from mobile money and credited to wallet

---

### 3. Withdraw (CASHOUT) - Wallet → Mobile Money

Transfer money from the HunterTechPay wallet to a mobile money account.

```php
<?php
$withdrawal = $hunter->withdraw([
    'amount' => 3000,                       // Amount in XAF
    'currency' => 'XAF',                    // Currency (must match country)
    'country' => 'CM',                      // Country code
    'phone' => '+237670000000',             // Recipient phone number
    'provider' => 'mtn_momo',               // Selected provider
    'reference' => 'WITHDRAW_' . time(),    // Your unique reference
    'description' => 'Withdrawal to mobile money',  // Description (optional)
    'callback_url' => 'https://mysite.com/webhook.php',  // Webhook (optional)
]);

echo 'Withdrawal initiated:' . PHP_EOL;
echo 'Transaction ID: ' . $withdrawal['transaction_id'] . PHP_EOL;
echo 'Status: ' . $withdrawal['status'] . PHP_EOL;
?>
```

**Validation**:
- System checks available balance before authorizing withdrawal
- System verifies CASHOUT is enabled for merchant

---

### 4. Initiate Generic Payment

```php
<?php
$payment = $hunter->initiatePayment([
    'amount' => 5000,
    'currency' => 'XAF',
    'country' => 'CM',
    'phone' => '+237690000000',
    'provider' => 'orange_money',
    'reference' => 'ORDER_123',
    'description' => 'Purchase product XYZ',
    'callback_url' => 'https://mysite.com/webhook.php',
    'return_url' => 'https://mysite.com/success.php'
]);

echo "Payment initiated: " . $payment['transaction_id'];
?>
```

---

### 5. Check Transaction Status

```php
<?php
// By transaction_id
$status = $hunter->checkStatus('txn_abc123');

// By reference
$status = $hunter->checkStatus('ORDER_123', 'reference');

echo "Status: " . $status['status'] . PHP_EOL;  // 'pending', 'success', 'failed'
echo "Amount: " . $status['amount'] . PHP_EOL;
echo "Currency: " . $status['currency'] . PHP_EOL;
?>
```

---

### 6. List Transactions

```php
<?php
$transactions = $hunter->listTransactions([
    'page' => 1,
    'page_size' => 50,
    'status' => 'success',            // Filter by status (optional)
    'start_date' => '2026-03-01',     // Start date (optional)
    'end_date' => '2026-03-14'        // End date (optional)
]);

echo "Total transactions: " . $transactions['total'] . PHP_EOL;
foreach ($transactions['transactions'] as $tx) {
    echo $tx['transaction_id'] . ': ' . $tx['amount'] . ' ' . $tx['currency'] . PHP_EOL;
}
?>
```

---

### 7. Get Balance

```php
<?php
$balance = $hunter->getBalance('merchant_123');

echo "Available balance: " . $balance['available_balance'] . ' ' . $balance['currency'] . PHP_EOL;
echo "Pending balance: " . $balance['pending_balance'] . ' ' . $balance['currency'] . PHP_EOL;
?>
```

---

## Complete Examples

### Laravel Example

#### Controller (PaymentController.php)

```php
<?php
// app/Http/Controllers/PaymentController.php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use HunterTechPayError;

class PaymentController extends Controller
{
    private $hunter;

    public function __construct()
    {
        require_once app_path('Services/HunterTechPay.php');

        // ⚠️ IMPORTANT: Use environment variables
        $this->hunter = new \HunterTechPay(
            env('HUNTER_API_KEY'),
            env('HUNTER_SECRET_KEY'),
        );
    }

    /**
     * Get available providers
     */
    public function getProviders($country = 'CM')
    {
        try {
            $providers = $this->hunter->getProviders($country);
            return response()->json($providers);
        } catch (HunterTechPayError $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], $e->statusCode ?: 500);
        }
    }

    /**
     * Initiate a deposit (CASHIN)
     */
    public function deposit(Request $request)
    {
        try {
            $deposit = $this->hunter->deposit([
                'amount' => $request->amount,
                'currency' => 'XAF',
                'country' => 'CM',
                'phone' => $request->phone,
                'provider' => $request->provider,
                'reference' => 'DEPOSIT_' . time(),
            ]);

            return response()->json($deposit, 201);

        } catch (HunterTechPayError $e) {
            return response()->json([
                'error' => $e->getMessage()
            ], $e->statusCode ?: 500);
        }
    }

    /**
     * Initiate a withdrawal (CASHOUT)
     */
    public function withdraw(Request $request)
    {
        try {
            $withdrawal = $this->hunter->withdraw([
                'amount' => $request->amount,
                'currency' => 'XAF',
                'country' => 'CM',
                'phone' => $request->phone,
                'provider' => $request->provider,
                'reference' => 'WITHDRAW_' . time(),
            ]);

            return response()->json($withdrawal, 201);

        } catch (HunterTechPayError $e) {
            // Specific error handling
            $errorMsg = $e->getMessage();

            if ($e->statusCode === 400 && strpos($errorMsg, 'Insufficient balance') !== false) {
                return response()->json([
                    'error' => 'Insufficient balance',
                    'details' => $errorMsg
                ], 400);
            } elseif ($e->statusCode === 403 && strpos($errorMsg, 'frozen') !== false) {
                return response()->json([
                    'error' => 'Account frozen',
                    'details' => $errorMsg
                ], 403);
            } else {
                return response()->json([
                    'error' => $errorMsg
                ], $e->statusCode ?: 500);
            }
        }
    }
}
```

#### Routes (web.php)

```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/api/providers/{country}', [PaymentController::class, 'getProviders']);
Route::post('/api/deposit', [PaymentController::class, 'deposit']);
Route::post('/api/withdraw', [PaymentController::class, 'withdraw']);
```

#### Configuration (.env)

```
HUNTER_API_KEY=htp_live_abc123...
HUNTER_SECRET_KEY=sk_live_xyz789...
```

---

### Plain PHP Example

```php
<?php
require_once 'HunterTechPay.php';

// ⚠️ IMPORTANT: Use environment variables
$hunter = new HunterTechPay(
    getenv('HUNTER_API_KEY'),
    getenv('HUNTER_SECRET_KEY')
);

// Get providers
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $country = $_GET['country'] ?? 'CM';
        $providers = $hunter->getProviders($country);

        header('Content-Type: application/json');
        echo json_encode($providers);
        exit;
    } catch (HunterTechPayError $e) {
        http_response_code($e->statusCode ?: 500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}

// Initiate a deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);

        $deposit = $hunter->deposit([
            'amount' => $data['amount'],
            'currency' => 'XAF',
            'country' => 'CM',
            'phone' => $data['phone'],
            'provider' => $data['provider'],
            'reference' => 'DEPOSIT_' . time(),
        ]);

        http_response_code(201);
        header('Content-Type: application/json');
        echo json_encode($deposit);
        exit;

    } catch (HunterTechPayError $e) {
        http_response_code($e->statusCode ?: 500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        exit;
    }
}
?>
```

---

## Security - VERY IMPORTANT

### ❌ NEVER DO THIS

```php
<?php
// ❌ DANGER: Secret key in source code
$hunter = new HunterTechPay(
    'htp_live_...',
    'sk_live_...'  // ❌ EXPOSED in code!
);
?>
```

### ✅ CORRECT APPROACH

#### Using .env file

**.env** (NEVER commit to Git):
```
HUNTER_API_KEY=htp_live_abc123...
HUNTER_SECRET_KEY=sk_live_xyz789...
```

**.gitignore**:
```
.env
vendor/
composer.lock
```

**Code**:
```php
<?php
// ✅ SECURE
$hunter = new HunterTechPay(
    getenv('HUNTER_API_KEY'),
    getenv('HUNTER_SECRET_KEY')
);
?>
```

#### Laravel (.env)

```
HUNTER_API_KEY=htp_live_abc123...
HUNTER_SECRET_KEY=sk_live_xyz789...
```

**Laravel Code**:
```php
<?php
$hunter = new HunterTechPay(
    env('HUNTER_API_KEY'),
    env('HUNTER_SECRET_KEY')
);
?>
```

---

## Error Handling

```php
<?php
try {
    $deposit = $hunter->deposit([
        'amount' => 5000,
        'currency' => 'XAF',
        'country' => 'CM',
        'phone' => '+237690000000',
        'provider' => 'orange_money',
        'reference' => 'DEP_001'
    ]);

} catch (HunterTechPayError $e) {
    echo "Error code: " . $e->statusCode . PHP_EOL;
    echo "Message: " . $e->getMessage() . PHP_EOL;
    print_r($e->data);

    // Specific error handling by code
    if ($e->statusCode === 400) {
        if (strpos($e->getMessage(), 'Insufficient balance') !== false) {
            echo 'Insufficient balance';
        } elseif (strpos($e->getMessage(), 'Invalid currency') !== false) {
            echo 'Invalid currency for this country';
        } else {
            echo 'Invalid parameters: ' . $e->getMessage();
        }

    } elseif ($e->statusCode === 403) {
        if (strpos($e->getMessage(), 'frozen') !== false) {
            echo 'Wallet frozen';
        } elseif (strpos($e->getMessage(), 'CASHOUT is disabled') !== false) {
            echo 'Withdrawal disabled for your account';
        } else {
            echo 'Access denied: ' . $e->getMessage();
        }

    } elseif ($e->statusCode === 404) {
        echo 'Provider or wallet not found';

    } else {
        echo 'Error: ' . $e->getMessage();
    }

} catch (Exception $e) {
    echo 'Network error: ' . $e->getMessage();
}
?>
```
---

## Currency/Country Validation

The SDK automatically validates that the currency matches the country:

| Country | Accepted Currency | Rejected Currency |
|---------|-------------------|-------------------|
| CM (Cameroon) | ✅ XAF | ❌ XOF |
| SN (Senegal) | ✅ XOF | ❌ XAF |

**Error Example**:
```php
<?php
// Attempt: XOF in Cameroon
$deposit = $hunter->deposit([
    'currency' => 'XOF',  // ❌ Error
    'country' => 'CM'
]);

// Error returned:
// "Invalid currency for country CM. Expected XAF, got XOF"
?>
```

---

## License

MIT

---

## Support

- **Complete Documentation**: `DOCUMENTATION_INDEX.md`
- **Email**: support@huntertechpay.com
- **GitHub**: https://github.com/hunter-tech-africa
