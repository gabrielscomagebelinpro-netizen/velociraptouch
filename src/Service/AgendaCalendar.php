<?php

namespace App\Service;

use App\Entity\Event;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Règles d'affichage de l'agenda public.
 *
 * - La liste sous le planning ne montre que les événements du jour et à venir.
 * - Le planning montre en plus les événements des derniers mois, grisés : ils restent
 *   repérables et, pour un administrateur, mènent à leurs photos.
 */
final class AgendaCalendar
{
    /** Nombre de mois passés consultables dans le planning (en plus du mois en cours). */
    public const PAST_MONTHS = 3;

    public function __construct(private readonly UrlGeneratorInterface $urls)
    {
    }

    /** Premier jour affiché par le planning : le 1er du mois, PAST_MONTHS mois en arrière. */
    public static function windowStart(\DateTimeImmutable $today): \DateTimeImmutable
    {
        return $today->setTime(0, 0)->modify('first day of this month')->modify(sprintf('-%d months', self::PAST_MONTHS));
    }

    /** Un événement du jour n'est pas encore passé. */
    public static function isPast(Event $event, \DateTimeImmutable $today): bool
    {
        return $event->getDate() !== null && $event->getDate()->format('Y-m-d') < $today->format('Y-m-d');
    }

    /**
     * Événements à lister sous le planning (du jour et à venir), ordre conservé.
     *
     * @param iterable<Event> $events
     * @return list<Event>
     */
    public static function upcoming(iterable $events, \DateTimeImmutable $today): array
    {
        $upcoming = [];
        foreach ($events as $event) {
            if ($event->getDate() !== null && !self::isPast($event, $today)) {
                $upcoming[] = $event;
            }
        }

        return $upcoming;
    }

    /**
     * Données JSON du planning. Un événement passé n'a pas d'ancre dans la liste :
     * il pointe vers ses photos pour un administrateur, et vers rien pour les autres.
     *
     * @param iterable<Event> $events
     * @return list<array{id: int|null, date: string, title: string, past: bool, url: string|null}>
     */
    public function entries(iterable $events, \DateTimeImmutable $today, bool $canManagePhotos): array
    {
        $entries = [];
        foreach ($events as $event) {
            if ($event->getDate() === null) {
                continue;
            }
            $past = self::isPast($event, $today);
            $url = match (true) {
                !$past => '#event-'.$event->getId(),
                $canManagePhotos => $this->urls->generate('admin_event_edit', ['id' => $event->getId()]).'#event-photos',
                default => null,
            };
            $entries[] = [
                'id' => $event->getId(),
                'date' => $event->getDate()->format('Y-m-d'),
                'title' => $event->getTitle(),
                'past' => $past,
                'url' => $url,
            ];
        }

        return $entries;
    }
}
