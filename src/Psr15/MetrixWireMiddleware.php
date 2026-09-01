<?php

declare(strict_types=1);

namespace MetrixWire\Psr15;

use MetrixWire\MetrixWire;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PSR-15 middleware — works with Slim, Mezzio, and any PSR-15 stack. Add it as
 * the outermost middleware and every request becomes a trace:
 *
 *   $app->add(new \MetrixWire\Psr15\MetrixWireMiddleware());
 *
 * Config is read from METRIXWIRE_* env vars. Database queries are captured via a
 * framework adapter or the drop-in \MetrixWire\PDO. Never throws into the app.
 */
final class MetrixWireMiddleware implements MiddlewareInterface
{
    public function __construct()
    {
        MetrixWire::init([
            'apiKey' => (string) (getenv('METRIXWIRE_KEY') ?: ''),
            'endpoint' => (string) (getenv('METRIXWIRE_ENDPOINT') ?: 'https://metrixwire.com'),
            'enabled' => filter_var(getenv('METRIXWIRE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (! MetrixWire::isEnabled()) {
            return $handler->handle($request);
        }

        $method = $request->getMethod();
        $path = $request->getUri()->getPath() ?: '/';
        MetrixWire::startTrace($method.' '.$path, $method);

        try {
            $response = $handler->handle($request);
        } catch (\Throwable $e) {
            MetrixWire::captureException($e);
            MetrixWire::endTrace(500);
            MetrixWire::flush();
            throw $e;
        }

        try {
            $len = $response->getHeaderLine('Content-Length');
            $bytes = is_numeric($len) && $len !== '' ? (int) $len : (int) ($response->getBody()->getSize() ?? 0);
            if ($bytes > 0) {
                MetrixWire::addTraceMeta('responseBytes', $bytes);
            }
        } catch (\Throwable $e) {
            // best effort
        }

        MetrixWire::endTrace($response->getStatusCode());
        MetrixWire::flush();

        return $response;
    }
}
