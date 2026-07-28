<?php

declare(strict_types=1);

namespace MetrixWire\Laravel;

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use MetrixWire\MetrixWire;

/**
 * Auto-discovered via composer `extra.laravel.providers`. Wires up:
 *  - SDK config from METRIXWIRE_KEY / METRIXWIRE_ENDPOINT env vars
 *  - DB::listen → a db_query span per executed query
 *  - DB transaction events → a transaction span (long_transaction detector)
 *  - the request middleware (opens/closes the trace, sends on terminate)
 *
 * Cache monitoring: Laravel's cache events don't carry timing, so cache spans
 * aren't auto-recorded. Instrument slow cache reads manually where it matters
 * with `MetrixWire::cacheSpan($op, $durationMs, $hit)`.
 */
final class MetrixWireServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        MetrixWire::init([
            'apiKey' => (string) env('METRIXWIRE_KEY', ''),
            'endpoint' => (string) env('METRIXWIRE_ENDPOINT', 'http://localhost:3000'),
            'enabled' => (bool) env('METRIXWIRE_ENABLED', true),
        ]);

        if (! MetrixWire::isEnabled()) {
            return;
        }

        // Every query Laravel runs — Eloquent, query builder, raw — arrives here
        // already parameterised (`?` placeholders), with timing in milliseconds.
        DB::listen(function ($query): void {
            MetrixWire::addSpan('db_query', $query->sql, (float) $query->time);
        });

        // Time the outermost transaction: start when depth goes 0→1, record when
        // it returns to 0. Nested savepoints are folded into the outer span.
        $txStart = null;
        Event::listen(TransactionBeginning::class, function ($event) use (&$txStart): void {
            if ($event->connection->transactionLevel() === 1) {
                $txStart = microtime(true);
            }
        });
        $closeTx = function ($event) use (&$txStart): void {
            if ($txStart !== null && $event->connection->transactionLevel() === 0) {
                MetrixWire::recordTransaction((microtime(true) - $txStart) * 1000);
                $txStart = null;
            }
        };
        Event::listen(TransactionCommitted::class, $closeTx);
        Event::listen(TransactionRolledBack::class, $closeTx);

        // Queue jobs: trace each processed job as its own trace so db/http spans
        // made inside a job are attributed and slow/failed jobs surface. The HTTP
        // middleware doesn't run in a worker, so this is the queue equivalent.
        Event::listen(JobProcessing::class, function ($event): void {
            $name = method_exists($event->job, 'resolveName') ? $event->job->resolveName() : $event->job->getName();
            MetrixWire::startTrace('JOB '.$name, 'JOB');
        });
        $endJob = function ($event): void {
            MetrixWire::endTrace(200);
            MetrixWire::flush();
        };
        Event::listen(JobProcessed::class, $endJob);
        Event::listen(JobExceptionOccurred::class, function ($event): void {
            MetrixWire::captureException($event->exception);
            MetrixWire::endTrace(500);
            MetrixWire::flush();
        });
        Event::listen(JobFailed::class, function ($event): void {
            if ($event->exception !== null) {
                MetrixWire::captureException($event->exception);
            }
            MetrixWire::endTrace(500);
            MetrixWire::flush();
        });

        // Register the middleware globally so every HTTP request is traced.
        $kernel = $this->app->make(Kernel::class);
        if (method_exists($kernel, 'prependMiddleware')) {
            $kernel->prependMiddleware(MetrixWireMiddleware::class);
        }
    }
}
