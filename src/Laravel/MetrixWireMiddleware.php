<?php

declare(strict_types=1);

namespace MetrixWire\Laravel;

use Closure;
use Illuminate\Http\Request;
use MetrixWire\MetrixWire;
use Symfony\Component\HttpFoundation\Response;

/**
 * Opens a trace at the start of each request and closes it once the response is
 * ready. The actual send happens in `terminate()` — after the response has been
 * sent to the client — so instrumentation adds no latency the user can feel.
 */
final class MetrixWireMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->method().' '.'/'.ltrim(
            optional($request->route())->uri() ?? $request->path(),
            '/'
        );
        MetrixWire::startTrace($route, $request->method());

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        MetrixWire::endTrace($response->getStatusCode());

        // Response body size for the large_response_payload detector. Prefer the
        // Content-Length header; fall back to measuring the buffered body.
        $bytes = 0;
        $len = $response->headers->get('Content-Length');
        if ($len !== null && is_numeric($len)) {
            $bytes = (int) $len;
        } else {
            $content = $response->getContent();
            if (is_string($content)) {
                $bytes = strlen($content);
            }
        }
        if ($bytes > 0) {
            MetrixWire::addTraceMeta('responseBytes', $bytes);
        }

        MetrixWire::flush();
    }
}
