<?php

declare(strict_types=1);

namespace MetrixWire\Symfony;

use Doctrine\DBAL\Logging\SQLLogger;
use MetrixWire\MetrixWire;

/**
 * Doctrine DBAL SQL logger — records every query as a `db_query` span. Register
 * it on the connection configuration (DBAL 2/3):
 *
 *   $config->setSQLLogger(new \MetrixWire\Symfony\DoctrineSqlLogger());
 *
 * DBAL 4 removed SQLLogger in favour of driver middlewares — on DBAL 4 use the
 * drop-in {@see \MetrixWire\PDO} instead. Never throws into the app.
 */
final class DoctrineSqlLogger implements SQLLogger
{
    private ?float $start = null;

    private ?string $sql = null;

    /**
     * @param array<mixed>|null $params
     * @param array<mixed>|null $types
     */
    public function startQuery($sql, ?array $params = null, ?array $types = null): void
    {
        $this->start = microtime(true);
        $this->sql = is_string($sql) ? $sql : null;
    }

    public function stopQuery(): void
    {
        try {
            if ($this->start !== null && $this->sql !== null) {
                MetrixWire::addSpan('db_query', $this->sql, (microtime(true) - $this->start) * 1000);
            }
        } catch (\Throwable $e) {
            // swallow — monitoring must never break a query
        }
        $this->start = null;
        $this->sql = null;
    }
}
