<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * En-têtes de sécurité ajoutés à toutes les pages générées par Symfony.
 * La politique de sécurité du contenu (CSP) n'autorise que les ressources du site lui-même,
 * plus la carte Google Maps (chargée au clic) : un script injecté ne pourrait ni s'exécuter ni charger de ressource externe.
 */
final class SecurityHeadersSubscriber implements EventSubscriberInterface
{
    private const CONTENT_SECURITY_POLICY = [
        "default-src 'self'",
        "script-src 'self'",
        // Les attributs style="…" du HTML restent autorisés (sans risque d'exécution de code).
        "style-src 'self' 'unsafe-inline'",
        "img-src 'self' data: blob:",
        "font-src 'self'",
        "connect-src 'self'",
        'frame-src https://www.google.com',
        "object-src 'none'",
        "base-uri 'self'",
        "form-action 'self' mailto:",
        "frame-ancestors 'self'",
    ];

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::RESPONSE => 'onResponse'];
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $headers = $event->getResponse()->headers;
        $headers->set('Content-Security-Policy', implode('; ', self::CONTENT_SECURITY_POLICY));
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'SAMEORIGIN');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($event->getRequest()->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
    }
}
