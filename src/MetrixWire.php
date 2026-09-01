<?php

declare(strict_types=1);

namespace MetrixWire;

/**
 * Singleton facade for the SDK. Holds config + the current trace, buffers spans,
 * and sends everything at the end of the request. All public methods are
 * defensive: instrumentation must never throw into the host application.
 */
final class MetrixWire
{
    private static ?MetrixWire $instance = null;

    private bool $enabled = false;

    private string $apiKey = '';

    private string $endpoint = 'https://metrixwire.com/ingest';

    private int $timeoutMs = 3000;

    private ?Trace $trace = null;

    private bool $sent = false;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * @param array{apiKey?: string, endpoint?: string, enabled?: bool, timeoutMs?: int} $config
     */
    public static function init(array $config): void
    {
        $i = self::instance();
        $i->apiKey = (string) ($config['apiKey'] ?? '');
        $i->endpoint = (string) ($config['endpoint'] ?? $i->endpoint);
        $i->timeoutMs = (int) ($config['timeoutMs'] ?? $i->timeoutMs);
        $i->enabled = ($config['enabled'] ?? true) && $i->apiKey !== '';
    }

    public static function isEnabled(): bool
    {
        return self::instance()->enabled;
    }

    /** Open a trace for the current request. */
    public static function startTrace(string $route, ?string $method): void
    {
        $i = self::instance();
        if (! $i->enabled) {
            return;
        }
        $now = microtime(true);
        $i->trace = new Trace($route, $method, self::iso($now), $now);
        $i->sent = false;
    }

    /**
     * Record a span against the active trace. Durations are in milliseconds.
     *
     * @param array<string, mixed>|null $meta Optional signal (e.g. rowCount, statusCode).
     */
    public static function addSpan(string $type, string $description, float $durationMs, ?string $sourceLocation = null, ?array $meta = null): void
    {
        $i = self::instance();
        if (! $i->enabled || $i->trace === null || $description === '') {
            return;
        }
        // Approximate the span start relative to now minus its duration.
        $startedAt = self::iso(microtime(true) - ($durationMs / 1000));
        $i->trace->addSpan(new Span($type, $description, $startedAt, (int) round(max(0, $durationMs)), $sourceLocation, $meta));
    }

    /**
     * Set a trace-level meta value (e.g. `responseBytes`). No-op when disabled or
     * outside a trace. Used by detectors like large_response_payload.
     */
    public static function addTraceMeta(string $key, mixed $value): void
    {
        $i = self::instance();
        if (! $i->enabled || $i->trace === null || $key === '') {
            return;
        }
        $meta = $i->trace->meta ?? [];
        $meta[$key] = $value;
        $i->trace->meta = $meta;
    }

    /**
     * Record a cache operation as a `custom` span tagged `meta.kind='cache'` so
     * the `slow_cache_op` detector can flag slow lookups. Time the operation
     * yourself and pass the duration in milliseconds; `hit` is optional.
     */
    public static function cacheSpan(string $op, float $durationMs, ?bool $hit = null): void
    {
        $meta = ['kind' => 'cache', 'op' => $op];
        if ($hit !== null) {
            $meta['hit'] = $hit;
        }
        self::addSpan('custom', $op, $durationMs, null, $meta);
    }

    /**
     * Record a database transaction as a `custom` span tagged
     * `meta.kind='transaction'` for the long_transaction detector.
     */
    public static function recordTransaction(float $durationMs, string $label = 'DB transaction'): void
    {
        self::addSpan('custom', $label, $durationMs, null, ['kind' => 'transaction']);
    }

    /**
     * Attach an exception to the active trace so the `unhandled_exception`
     * detector can surface it. Also flags the trace as an error. Never throws.
     */
    public static function captureException(\Throwable $e): void
    {
        $i = self::instance();
        if (! $i->enabled || $i->trace === null) {
            return;
        }
        $meta = $i->trace->meta ?? [];
        $meta['exception'] = [
            'type' => (new \ReflectionClass($e))->getShortName(),
            'message' => $e->getMessage(),
            // Top lines only — enough for fingerprinting & fix guidance.
            'stack' => implode("\n", array_slice(explode("\n", $e->getTraceAsString()), 0, 8)),
        ];
        $i->trace->meta = $meta;
        $i->trace->status = 'error';
    }

    /** Close the trace, compute its duration, set status, record peak memory. */
    public static function endTrace(int $statusCode = 200): void
    {
        $i = self::instance();
        if (! $i->enabled || $i->trace === null) {
            return;
        }
        $i->trace->durationMs = (int) round((microtime(true) - $i->trace->startMicro) * 1000);
        // captureException() may already have flagged this as an error — keep it.
        if ($statusCode >= 500) {
            $i->trace->status = 'error';
        }
        // Peak memory this request touched (PHP is process-per-request, so peak ≈ request cost).
        $memoryMb = (int) round(memory_get_peak_usage(true) / (1024 * 1024));
        if ($memoryMb > 0) {
            $meta = $i->trace->meta ?? [];
            $meta['memoryMb'] = $memoryMb;
            $i->trace->meta = $meta;
        }
    }

    /**
     * Flush the trace to the ingest endpoint. Safe to call multiple times — it
     * sends at most once per trace. Intended for a shutdown/terminate hook so it
     * doesn't add to the latency the user perceives.
     */
    public static function flush(): void
    {
        $i = self::instance();
        if (! $i->enabled || $i->trace === null || $i->sent) {
            return;
        }
        if ($i->trace->durationMs === 0) {
            self::endTrace();
        }
        $i->sent = true;
        try {
            (new Transport($i->endpoint, $i->apiKey, $i->timeoutMs))->send([$i->trace->toArray()]);
        } catch (\Throwable $e) {
            // swallow
        }
        $i->trace = null;
    }

    private static function iso(float $micro): string
    {
        $sec = (int) floor($micro);
        $ms = (int) round(($micro - $sec) * 1000);
        return gmdate('Y-m-d\TH:i:s', $sec).sprintf('.%03dZ', $ms);
    }
}
