<?php

declare(strict_types=1);

namespace MetrixWire;

/**
 * Sends a batch of traces to the ingest endpoint. Uses a short cURL timeout and
 * swallows every error — monitoring must never slow down or break the app.
 */
final class Transport
{
    public function __construct(
        private string $endpoint,
        private string $apiKey,
        private int $timeoutMs = 3000,
    ) {
    }

    /** @param array<int, array<string, mixed>> $traces */
    public function send(array $traces): void
    {
        if ($traces === [] || $this->apiKey === '') {
            return;
        }

        $url = rtrim($this->endpoint, '/');
        // Allow passing either the base URL or the full /ingest URL.
        if (! str_ends_with($url, '/ingest')) {
            $url .= '/ingest';
        }

        $body = json_encode(['traces' => $traces]);
        if ($body === false) {
            return;
        }

        try {
            if (function_exists('curl_init')) {
                $ch = curl_init($url);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $body,
                    CURLOPT_HTTPHEADER => [
                        'Content-Type: application/json',
                        'x-api-key: '.$this->apiKey,
                    ],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT_MS => $this->timeoutMs,
                    CURLOPT_CONNECTTIMEOUT_MS => $this->timeoutMs,
                    CURLOPT_NOSIGNAL => true,
                ]);
                curl_exec($ch);
                curl_close($ch);

                return;
            }

            // Fallback when cURL isn't available.
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/json\r\nx-api-key: {$this->apiKey}\r\n",
                    'content' => $body,
                    'timeout' => $this->timeoutMs / 1000,
                    'ignore_errors' => true,
                ],
            ]);
            @file_get_contents($url, false, $ctx);
        } catch (\Throwable $e) {
            // Silently ignore — never propagate transport failures.
        }
    }
}
