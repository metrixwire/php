<?php

declare(strict_types=1);

namespace MetrixWire;

/**
 * Drop-in replacement for the native PDO. Change `new \PDO(...)` to
 * `new \MetrixWire\PDO(...)` and every query — direct (`query`/`exec`) or
 * prepared (`prepare()->execute()`) — is captured as a `db_query` span with no
 * further code. This is the zero-framework path to automatic DB instrumentation
 * (framework adapters like Laravel/Symfony hook the ORM instead).
 */
class PDO extends \PDO
{
    private ?float $txStart = null;

    /**
     * @param array<int, mixed>|null $options
     */
    public function __construct(string $dsn, ?string $username = null, ?string $password = null, ?array $options = null)
    {
        parent::__construct($dsn, $username, $password, $options ?? []);
        // Route prepared statements through the instrumented statement class.
        try {
            $this->setAttribute(\PDO::ATTR_STATEMENT_CLASS, [PDOStatement::class, [$this]]);
        } catch (\Throwable $e) {
            // Some drivers reject a custom statement class — direct queries still record.
        }
    }

    public function exec(string $statement): int|false
    {
        $start = microtime(true);
        $affected = false;
        try {
            return $affected = parent::exec($statement);
        } finally {
            $this->record($statement, $start, is_int($affected) ? $affected : null);
        }
    }

    #[\ReturnTypeWillChange]
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): \PDOStatement|false
    {
        $start = microtime(true);
        $stmt = false;
        try {
            return $stmt = $fetchMode === null
                ? parent::query($query)
                : parent::query($query, $fetchMode, ...$fetchModeArgs);
        } finally {
            // On PostgreSQL, rowCount() reports SELECT row counts — enough for the
            // large_result_set detector.
            $rows = $stmt instanceof \PDOStatement ? @$stmt->rowCount() : null;
            $this->record($query, $start, is_int($rows) ? $rows : null);
        }
    }

    public function beginTransaction(): bool
    {
        $this->txStart = microtime(true);

        return parent::beginTransaction();
    }

    public function commit(): bool
    {
        $ok = parent::commit();
        $this->recordTransaction();

        return $ok;
    }

    public function rollBack(): bool
    {
        $ok = parent::rollBack();
        $this->recordTransaction();

        return $ok;
    }

    private function recordTransaction(): void
    {
        try {
            if ($this->txStart !== null) {
                MetrixWire::recordTransaction((microtime(true) - $this->txStart) * 1000);
            }
        } catch (\Throwable $e) {
            // swallow
        }
        $this->txStart = null;
    }

    private function record(string $sql, float $start, ?int $rowCount = null): void
    {
        try {
            $meta = $rowCount !== null && $rowCount >= 0 ? ['rowCount' => $rowCount] : null;
            MetrixWire::addSpan('db_query', $sql, (microtime(true) - $start) * 1000, null, $meta);
        } catch (\Throwable $e) {
            // never let instrumentation break a query
        }
    }
}
