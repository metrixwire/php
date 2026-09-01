<?php

declare(strict_types=1);

namespace MetrixWire\Symfony;

use MetrixWire\MetrixWire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Symfony event subscriber. Register it as a service (autoconfigure tags it
 * automatically) and every request is traced — trace opens on kernel.request,
 * closes and sends on kernel.terminate, and exceptions are captured. Config is
 * read from METRIXWIRE_* env vars, so no code beyond registering the service.
 *
 * For database queries, register {@see DoctrineSqlLogger} on your DBAL
 * connection (or use the drop-in \MetrixWire\PDO).
 */
final class MetrixWireSubscriber implements EventSubscriberInterface
{
    public function __construct()
    {
        MetrixWire::init([
            'apiKey' => (string) (getenv('METRIXWIRE_KEY') ?: ''),
            'endpoint' => (string) (getenv('METRIXWIRE_ENDPOINT') ?: 'https://metrixwire.com'),
            'enabled' => filter_var(getenv('METRIXWIRE_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        ]);
    }

    /** @return array<string, array{0: string, 1: int}> */
    public static function getSubscribedEvents(): array
    {
        return [
            // High priority so the trace is open before controllers run.
            KernelEvents::REQUEST => ['onRequest', 512],
            KernelEvents::EXCEPTION => ['onException', 0],
            KernelEvents::RESPONSE => ['onResponse', -512],
            KernelEvents::TERMINATE => ['onTerminate', 0],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (! $event->isMainRequest() || ! MetrixWire::isEnabled()) {
            return;
        }
        $request = $event->getRequest();
        $method = $request->getMethod();
        // Prefer the matched route pattern once known; fall back to path.
        $path = $request->attributes->get('_route') ?: ($request->getPathInfo() ?: '/');
        MetrixWire::startTrace($method.' '.$path, $method);
    }

    public function onException(ExceptionEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }
        MetrixWire::captureException($event->getThrowable());
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }
        $response = $event->getResponse();
        $len = $response->headers->get('Content-Length');
        $bytes = ($len !== null && is_numeric($len)) ? (int) $len : strlen((string) $response->getContent());
        if ($bytes > 0) {
            MetrixWire::addTraceMeta('responseBytes', $bytes);
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        if (! $event->isMainRequest()) {
            return;
        }
        MetrixWire::endTrace($event->getResponse()->getStatusCode());
        MetrixWire::flush();
    }
}
