<?php

declare(strict_types=1);

/*
 * Loopback HTTP client shared by the disposable Evidence Hub HTTP checks.
 * Callers authorize the disposable test environment before using it.
 */

function httpEvidenceRequest(string $method, string $path, ?array $fields = null, ?string $cookie = null, array $headers = [], string $locale = 'en'): array
{
    $handle = curl_init('http://127.0.0.1' . $path);
    if ($handle === false) throw new RuntimeException('HTTP client unavailable.');
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 10,
        // Sent per request and never stored in the shared session jars.
        CURLOPT_COOKIE => 'ather_owner_locale=' . $locale,
    ]);
    if ($cookie !== null) {
        curl_setopt($handle, CURLOPT_COOKIEFILE, $cookie);
        curl_setopt($handle, CURLOPT_COOKIEJAR, $cookie);
    }
    if ($fields !== null) curl_setopt($handle, CURLOPT_POSTFIELDS, http_build_query($fields));
    $response = curl_exec($handle);
    if (!is_string($response)) throw new RuntimeException('HTTP request failed: ' . curl_error($handle));
    $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
    curl_close($handle);
    return ['status' => $status, 'headers' => substr($response, 0, $headerSize), 'body' => substr($response, $headerSize)];
}

function requireHttp(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
