<?php
/**
 * HunterTechPay SDK for PHP
 *
 * @version 1.0.1
 * @author HunterTechPay
 * @license MIT
 *
 * Usage:
 *   require_once 'HunterTechPay.php';
 *
 *   $hunter = new HunterTechPay(
 *       'htp_live_abc123...',
 *       'sk_live_xyz789...',
 *       'https://api.huntertechpay.com' // optional
 *   );
 *
 *   // Get providers
 *   $providers = $hunter->getProviders('CM');
 *
 *   // Initiate payment
 *   $payment = $hunter->initiatePayment([
 *       'amount' => 5000,
 *       'currency' => 'XAF',
 *       'country' => 'CM',
 *       'phone' => '+237690000000',
 *       'provider' => 'orange_money',
 *       'reference' => 'ORDER_123'
 *   ]);
 */

class HunterTechPayError extends Exception
{
    /**
     * @var string Original error message from API (unmodified)
     */
    public $apiMessage;

    /**
     * @var int HTTP status code
     */
    public $statusCode;

    /**
     * @var string Error code from API
     */
    public $errorCode;

    /**
     * @var array Complete API response data
     */
    public $data;

    /**
     * @var string Request ID for tracing
     */
    public $requestId;

    /**
     * Create a new HunterTechPayError instance
     *
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array|null $data Complete API response data
     * @param string|null $apiMessage Original API message (unmodified)
     * @param string|null $errorCode Error code from API
     * @param string|null $requestId Request ID for tracing
     */
    public function __construct(
        $message,
        $statusCode = 0,
        $data = null,
        $apiMessage = null,
        $errorCode = null,
        $requestId = null
    ) {
        parent::__construct($message);
        $this->statusCode = $statusCode;
        $this->data = $data ?: [];
        $this->apiMessage = $apiMessage ?: $message;
        $this->errorCode = $errorCode;
        $this->requestId = $requestId;
    }

    /**
     * Convert exception to array format for logging
     *
     * @return array Complete exception details
     */
    public function toArray()
    {
        return [
            'error_type' => get_class($this),
            'message' => $this->getMessage(),
            'api_message' => $this->apiMessage,
            'status_code' => $this->statusCode,
            'error_code' => $this->errorCode,
            'request_id' => $this->requestId,
            'data' => $this->data
        ];
    }

    /**
     * Get specific detail from error data
     *
     * @param string $key The key to retrieve
     * @param mixed $default Default value if key not found
     * @return mixed The value or default
     */
    public function getDetail($key, $default = null)
    {
        return isset($this->data[$key]) ? $this->data[$key] : $default;
    }

    /**
     * Get string representation with all details
     *
     * @return string
     */
    public function __toString()
    {
        $parts = [$this->getMessage()];

        if ($this->statusCode) {
            $parts[] = "Status: {$this->statusCode}";
        }

        if ($this->errorCode) {
            $parts[] = "Code: {$this->errorCode}";
        }

        if ($this->requestId) {
            $parts[] = "Request ID: {$this->requestId}";
        }

        // Add additional error details from API response
        if (!empty($this->data)) {
            $excluded = ['detail', 'message', 'error', 'error_code', 'error_message', 'code'];
            $extraDetails = array_diff_key($this->data, array_flip($excluded));

            if (!empty($extraDetails)) {
                $parts[] = "Details: " . json_encode($extraDetails);
            }
        }

        return implode(' | ', $parts);
    }
}

class HunterTechPay
{
    private $apiKey;
    private $secretKey;
    private $baseUrl;
    private $timeout;
    private $verifySsl;

    /**
     * Initialize HunterTechPay SDK
     *
     * @param string $apiKey Your API key (htp_live_...)
     * @param string $secretKey Your secret key (sk_live_...)
     * @param string $baseUrl Base API URL (optional)
     * @param bool|int $verifySslOrTimeout SSL verification (bool) or timeout (int) for backwards compatibility
     * @param int $timeout Request timeout in seconds (optional, only if 4th param is bool)
     */
    public function __construct($apiKey, $secretKey, $baseUrl = 'http://localhost:8007', $verifySslOrTimeout = true, $timeout = 30)
    {
        if (empty($apiKey) || empty($secretKey)) {
            throw new InvalidArgumentException('API key and secret key are required');
        }

        $this->apiKey = $apiKey;
        $this->secretKey = $secretKey;
        $this->baseUrl = rtrim($baseUrl, '/');

        // Handle backwards compatibility: 4th parameter can be bool (verifySsl) or int (timeout)
        if (is_bool($verifySslOrTimeout)) {
            $this->verifySsl = $verifySslOrTimeout;
            $this->timeout = $timeout;
        } else {
            $this->timeout = $verifySslOrTimeout;
            $this->verifySsl = true;
        }
    }

    /**
     * Generate HMAC-SHA512 signature
     *
     * @param array|string $payload Request payload (array or string)
     * @param string $timestamp Unix timestamp
     * @return string HMAC signature in hex format
     */
    private function generateSignature($payload, $timestamp)
    {
        // If payload is a string, use as-is; if array, JSON-encode it
        $jsonPayload = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $message = $timestamp . '.' . $jsonPayload;
        $signature = hash_hmac('sha512', $message, $this->secretKey);
        return $signature;
    }

    /**
     * Make HTTP request to HunterTechPay API
     *
     * @param string $endpoint API endpoint
     * @param string $method HTTP method
     * @param array|null $body Request body
     * @return array API response
     * @throws HunterTechPayError
     */
    private function request($endpoint, $method = 'GET', $body = null)
    {
        $timestamp = (string) time();
        // For GET requests, sign empty string; for POST/PUT/etc, sign the body
        $payload = $body ?: ($method === 'GET' ? '' : []);
        $signature = $this->generateSignature($payload, $timestamp);

        $headers = [
            'Content-Type: application/json',
            'X-Api-Key: ' . $this->apiKey,
            'X-Hunter-Signature: ' . $signature,
            'X-Hunter-Timestamp: ' . $timestamp,
        ];

        $url = $this->baseUrl . $endpoint;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // SSL verification (can be disabled for local development)
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifySsl);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $this->verifySsl ? 2 : 0);

        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            if ($body !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new HunterTechPayError('Network error: ' . $error, 0);
        }

        $data = json_decode($response, true);

        if ($httpCode >= 400) {
            // Extract original API message (unmodified)
            $apiMessage = $data['detail'] ?? $data['message'] ?? $data['error'] ?? $data['error_message'] ?? '';
            $message = $apiMessage ?: 'API request failed';

            // Extract error code from API
            $errorCode = $data['error_code'] ?? $data['code'] ?? null;

            // Extract request ID from response headers (if available in data)
            $requestId = $data['request_id'] ?? null;

            // Store complete API response including raw response if JSON parsing failed
            if ($data === null && !empty($response)) {
                $data = ['raw_response' => substr($response, 0, 1000)];
                $apiMessage = $response;
                $message = "HTTP {$httpCode}: {$response}";
            }

            throw new HunterTechPayError(
                $message,
                $httpCode,
                $data,
                $apiMessage,
                $errorCode,
                $requestId
            );
        }

        return $data;
    }

    /**
     * Get available payment providers for a country
     *
     * @param string|null $countryCode Optional country code (CM, SN, CI, etc.)
     *                                  If not provided, returns all providers
     * @return array Providers list with service codes
     *
     * Example:
     *   // Get all providers
     *   $providers = $hunter->getProviders();
     *   // Get providers for Cameroon
     *   $providers = $hunter->getProviders('CM');
     *   print_r($providers['providers']);
     */
    public function getProviders($countryCode = null)
    {
        if ($countryCode === null) {
            return $this->request("/api/v1/payments/providers");
        }
        return $this->request("/api/v1/payments/providers?country_code={$countryCode}");
    }

    /**
     * Initiate a payment
     *
     * @param array $paymentData Payment details
     *   - amount (float): Amount in local currency
     *   - currency (string): Currency code (XAF, XOF)
     *   - country (string): Country code (CM, SN, etc.)
     *   - phone (string): Customer phone number
     *   - provider (string): Provider code (orange_money, mtn_momo, etc.)
     *   - reference (string): Your unique reference
     *   - description (string, optional): Payment description
     *   - callback_url (string, optional): Webhook callback URL
     *   - return_url (string, optional): Redirect URL after payment
     *   - metadata (array, optional): Custom metadata
     * @return array Payment response
     *
     * Example:
     *   $payment = $hunter->initiatePayment([
     *       'amount' => 5000,
     *       'currency' => 'XAF',
     *       'country' => 'CM',
     *       'phone' => '+237690000000',
     *       'provider' => 'orange_money',
     *       'reference' => 'ORDER_123',
     *       'description' => 'Achat produit',
     *       'callback_url' => 'https://mysite.com/webhook',
     *       'return_url' => 'https://mysite.com/success'
     *   ]);
     *   echo $payment['transaction_id'];
     */
    public function initiatePayment($paymentData)
    {
        return $this->request('/api/v1/payments/initiate', 'POST', $paymentData);
    }

    /**
     * Deposit (CASHIN) - From mobile money to wallet
     *
     * Transfer money from a mobile money account to the HunterTechPay wallet.
     * Uses the provider's CASHIN service code (e.g., OM_CM_CASHIN, MTN_SN_CASHIN).
     *
     * @param array $depositData Deposit details
     *   - amount (float): Amount to deposit (in main currency units)
     *   - currency (string): Currency code (XAF, XOF)
     *   - country (string): Country code (CM, SN, etc.)
     *   - phone (string): Customer phone number
     *   - service_code (string): Service code (e.g., 'OM_CM_CASHIN', 'MTN_CM_CASHIN')
     *   - partner_id (string, optional): Your unique reference
     *   - description (string, optional): Deposit description
     *   - callback_url (string, optional): Webhook callback URL
     * @return array Deposit response with transaction_id and status
     *
     * Example:
     *   // Deposit 5000 XAF from Orange Money Cameroon to wallet
     *   $deposit = $hunter->deposit([
     *       'amount' => 5000,
     *       'currency' => 'XAF',
     *       'country' => 'CM',
     *       'phone' => '+237690000000',
     *       'service_code' => 'OM_CM_CASHIN',  // Use the service code directly
     *       'partner_id' => 'DEPOSIT_' . time(),
     *       'description' => 'Deposit to wallet'
     *   ]);
     *   echo "Transaction ID: " . $deposit['transaction_id'];
     *   echo "Status: " . $deposit['status']; // 'pending', 'success', etc.
     */
    public function deposit($depositData)
    {
        return $this->request('/api/v1/payments/deposit', 'POST', $depositData);
    }

    /**
     * Withdraw (CASHOUT) - From wallet to mobile money
     *
     * Transfer money from the HunterTechPay wallet to a mobile money account.
     * Uses the provider's CASHOUT service code (e.g., OM_CM_CASHOUT, MTN_SN_CASHOUT).
     *
     * @param array $withdrawData Withdrawal details
     *   - amount (float): Amount to withdraw (in main currency units)
     *   - currency (string): Currency code (XAF, XOF)
     *   - country (string): Country code (CM, SN, etc.)
     *   - phone (string): Recipient phone number
     *   - service_code (string): Service code (e.g., 'OM_CM_CASHOUT', 'MTN_CM_CASHOUT')
     *   - partner_id (string, optional): Your unique reference
     *   - description (string, optional): Withdrawal description
     *   - callback_url (string, optional): Webhook callback URL
     * @return array Withdrawal response with transaction_id and status
     *
     * Example:
     *   // Withdraw 3000 XAF from wallet to MTN Mobile Money Cameroon
     *   $withdrawal = $hunter->withdraw([
     *       'amount' => 3000,
     *       'currency' => 'XAF',
     *       'country' => 'CM',
     *       'phone' => '+237670000000',
     *       'service_code' => 'MTN_CM_CASHOUT',  // Use the service code directly
     *       'partner_id' => 'WITHDRAW_' . time(),
     *       'description' => 'Withdrawal to mobile money'
     *   ]);
     *   echo "Transaction ID: " . $withdrawal['transaction_id'];
     *   echo "Status: " . $withdrawal['status'];
     */
    public function withdraw($withdrawData)
    {
        return $this->request('/api/v1/payments/withdraw', 'POST', $withdrawData);
    }

    /**
     * Check payment status using partner_id
     *
     * @param string $partnerId Your partner_id (merchant reference) for the transaction
     * @return array Payment status
     *
     * Example:
     *   $status = $hunter->checkStatus('ORDER_123');
     *   echo "Status: " . $status['status'];
     *   echo "Amount: " . $status['amount'];
     */
    public function checkStatus($partnerId)
    {
        if (empty($partnerId) || !is_string($partnerId)) {
            throw new InvalidArgumentException('partner_id must be a non-empty string');
        }
        return $this->request("/api/v1/payments/status/{$partnerId}", 'GET');
    }

    /**
     * Verify KYC (Know Your Customer) information for a phone number
     *
     * @param array $kycData KYC verification details
     *   - phone_number (string): Phone number to verify
     *   - country (string): Country code (CM, SN, etc.)
     *   - provider_code (string): Provider code (orange_cm, mtn_cm, etc.)
     *   - partner_id (string, optional): Unique reference
     *   - metadata (array, optional): Custom metadata
     * @return array KYC verification result
     *
     * Example:
     *   $kycResult = $hunter->kyc([
     *       'phone_number' => '+237690000000',
     *       'country' => 'CM',
     *       'provider_code' => 'orange_cm',
     *       'partner_id' => 'KYC-123'
     *   ]);
     *   echo "Status: " . $kycResult['status'];
     *   echo "KYC Data: " . json_encode($kycResult['kyc_data']);
     */
    public function kyc($kycData)
    {
        if (empty($kycData) || !is_array($kycData)) {
            throw new InvalidArgumentException('kycData must be a non-empty array');
        }
        if (empty($kycData['phone_number'])) {
            throw new InvalidArgumentException('phone_number is required');
        }
        if (empty($kycData['country'])) {
            throw new InvalidArgumentException('country is required');
        }
        if (empty($kycData['provider_code'])) {
            throw new InvalidArgumentException('provider_code is required');
        }

        return $this->request('/api/v1/payments/kyc', 'POST', $kycData);
    }

    /**
     * List transactions
     *
     * @param array $filters Filter options
     *   - page (int): Page number
     *   - page_size (int): Items per page
     *   - status (string): Filter by status
     *   - start_date (string): Start date (ISO 8601)
     *   - end_date (string): End date (ISO 8601)
     * @return array Transactions list
     *
     * Example:
     *   $transactions = $hunter->listTransactions([
     *       'page' => 1,
     *       'page_size' => 50,
     *       'status' => 'success'
     *   ]);
     */
    public function listTransactions($filters = [])
    {
        $params = [];

        if (isset($filters['page'])) {
            $params[] = 'page=' . $filters['page'];
        }
        if (isset($filters['page_size'])) {
            $params[] = 'page_size=' . $filters['page_size'];
        }
        if (isset($filters['status'])) {
            $params[] = 'status=' . $filters['status'];
        }
        if (isset($filters['start_date'])) {
            $params[] = 'start_date=' . $filters['start_date'];
        }
        if (isset($filters['end_date'])) {
            $params[] = 'end_date=' . $filters['end_date'];
        }

        $queryString = implode('&', $params);
        $endpoint = '/api/v1/payments/transactions' . ($queryString ? '?' . $queryString : '');

        return $this->request($endpoint);
    }

    /**
     * Get wallet balances for all currencies
     *
     * @return array Balance information with wallets
     *
     * Example:
     *   $balance = $hunter->getBalance();
     *   foreach ($balance['wallets'] as $wallet) {
     *       echo "{$wallet['currency']}: {$wallet['balance']}\n";
     *   }
     */
    public function getBalance()
    {
        return $this->request('/api/v1/payments/balance');
    }

    /**
     * Verify webhook signature
     *
     * @param array|string $payload Webhook payload (array or JSON string)
     * @param string $timestamp X-Hunter-Timestamp header value
     * @param string $providedSignature X-Hunter-Signature header value
     * @param int $maxAgeSeconds Maximum age for timestamp (default: 300 seconds)
     * @return bool True if signature is valid and timestamp is fresh
     *
     * Example:
     *   // In your webhook endpoint
     *   $payload = json_decode(file_get_contents('php://input'), true);
     *   $timestamp = $_SERVER['HTTP_X_HUNTER_TIMESTAMP'];
     *   $signature = $_SERVER['HTTP_X_HUNTER_SIGNATURE'];
     *
     *   if (!$hunter->verifyWebhookSignature($payload, $timestamp, $signature)) {
     *       http_response_code(401);
     *       exit('Invalid signature');
     *   }
     *
     *   // Process webhook
     *   if ($payload['status'] === 'success') {
     *       fulfillOrder($payload['partner_id']);
     *   }
     */
    public function verifyWebhookSignature($payload, $timestamp, $providedSignature, $maxAgeSeconds = 300)
    {
        // Check timestamp freshness (prevent replay attacks)
        $currentTime = time();
        $requestTime = (int) $timestamp;
        $age = $currentTime - $requestTime;

        // Check if timestamp is not from future (60s tolerance for clock skew)
        if ($age < -60) {
            error_log('Webhook timestamp is from the future');
            return false;
        }

        // Check if timestamp is not too old
        if ($age > $maxAgeSeconds) {
            error_log("Webhook timestamp too old: {$age}s (max: {$maxAgeSeconds}s)");
            return false;
        }

        // Generate expected signature
        $expectedSignature = $this->generateSignature($payload, $timestamp);

        // Constant-time comparison (prevent timing attacks)
        return hash_equals($expectedSignature, $providedSignature);
    }

    /**
     * Parse webhook event
     *
     * @param array $payload Webhook payload
     * @return array Parsed webhook event
     * @throws Exception If required fields are missing
     *
     * Example:
     *   $event = $hunter->parseWebhookEvent($payload);
     *   echo "Event: {$event['event_type']}\n";
     *   echo "Status: {$event['status']}\n";
     *   echo "Transaction ID: {$event['transaction_id']}\n";
     */
    public function parseWebhookEvent($payload)
    {
        $requiredFields = ['event_type', 'transaction_id', 'status'];

        foreach ($requiredFields as $field) {
            if (!isset($payload[$field])) {
                throw new Exception("Missing required field in webhook: {$field}");
            }
        }

        return $payload;
    }
}
