<?php

declare(strict_types=1);

namespace MetrixWire;

/** A single unit of work within a trace (db_query | http_call | custom). */
final class Span
{
    /**
     * @param array<string, mixed>|null $meta Sinyal opsional (mis. statusCode, rowCount).
     */
    public function __construct(
        public string $type,
        public string $description,
        public string $startedAt, // ISO8601
        public int $durationMs,
        public ?string $sourceLocation = null,
        public ?array $meta = null,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'type' => $this->type,
            'description' => $this->description,
            'startedAt' => $this->startedAt,
            'durationMs' => $this->durationMs,
        ];
        if ($this->sourceLocation !== null) {
            $out['sourceLocation'] = $this->sourceLocation;
        }
        if ($this->meta !== null && $this->meta !== []) {
            $out['meta'] = $this->meta;
        }

        return $out;
    }
}
