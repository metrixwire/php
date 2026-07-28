<?php

declare(strict_types=1);

namespace MetrixWire;

/**
 * Instrumented PDOStatement. Registered automatically by {@see PDO} via
 * `PDO::ATTR_STATEMENT_CLASS`, so every prepared-statement `execute()` is timed
 * and recorded as a `db_query` span. Never throws into the host app.
 */
class PDOStatement extends \PDOStatement
{
    /**
     * PDO instantiates the statement class with the ctor args registered on the
     * connection. The constructor must be protected.
     */
    protected function __construct(protected \PDO $metrixwirePdo)
    {
    }

    public function execute(?array $params = null): bool
    {
        $start = microtime(true);
        $ok = false;
        try {
            return $ok = parent::execute($params);
        } finally {
            try {
                // On PostgreSQL, rowCount() reports SELECT row counts too.
                $rows = $ok ? @$this->rowCount() : null;
                $meta = is_int($rows) && $rows >= 0 ? ['rowCount' => $rows] : null;
                MetrixWire::addSpan('db_query', (string) $this->queryString, (microtime(true) - $start) * 1000, null, $meta);
            } catch (\Throwable $e) {
                // never let instrumentation break a query
            }
        }
    }
}
