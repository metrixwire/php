<?php

declare(strict_types=1);

namespace MetrixWire;

/** One request/page-load. Holds its spans. */
final class Trace
{
    /** @var Span[] */
    public array $spans = [];

    public string $status = 'success';

    public int $durationMs = 0;

    /** @var array<string, mixed>|null Optional trace-level signal (e.g. memoryMb, exception). */
    public ?array $meta = null;

    public function __construct(
        public string $route,
        public ?string $method,
        public string $startedAt, // ISO8601
        public float $startMicro, // microtime(true) at open
    ) {
    }

    public function addSpan(Span $span): void
    {
        $this->spans[] = $span;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'route' => $this->route,
            'method' => $this->method,
            'startedAt' => $this->startedAt,
            'durationMs' => $this->durationMs,
            'status' => $this->status,
            'spans' => array_map(fn (Span $s) => $s->toArray(), $this->spans),
        ];
        if ($this->meta !== null && $this->meta !== []) {
            $out['meta'] = $this->meta;
        }

        return $out;
    }
}
