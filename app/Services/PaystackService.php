<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Class PaystackService
 * @package App\Services
 */
class PaystackService
{

    protected $secretKey;
    protected $baseUrl = 'https://api.paystack.co';

    public function __construct()
    {
        $this->secretKey = config('services.paystack.secret_key');
    }

    public function initializeTransaction(array $data)
    {
        return $this->makeRequest('POST', '/transaction/initialize', $data);
    }

    public function verifyTransaction(string $reference)
    {
        return $this->makeRequest('GET', "/transaction/verify/{$reference}");
    }

    public function createPlan(array $data)
    {
        return $this->makeRequest('POST', '/plan', $data);
    }

    public function createSubscription(array $data)
    {
        return $this->makeRequest('POST', '/subscription', $data);
    }

    public function fetchSubscription(string $code)
    {
        return $this->makeRequest('GET', "/subscription/{$code}");
    }

    public function cancelSubscription(string $code, string $emailToken)
    {
        return $this->makeRequest('POST', '/subscription/disable', [
            'code' => $code,
            'token' => $emailToken,
        ]);
    }

    protected function makeRequest(string $method, string $endpoint, array $data = [])
    {
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . $this->secretKey,
            'Content-Type' => 'application/json',
        ])->{strtolower($method)}($this->baseUrl . $endpoint, $data);

        return $response->json();
    }
}
