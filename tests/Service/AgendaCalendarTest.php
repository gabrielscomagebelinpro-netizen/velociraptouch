<?php

namespace App\Tests\Service;

use App\Entity\Event;
use App\Service\AgendaCalendar;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AgendaCalendarTest extends TestCase
{
    private const TODAY = '2026-11-05';

    /**
     * Exemple : on est le jeudi 5 novembre 2026.
     *  - 12/10 « Tournoi d'automne »  → passé, hors liste, grisé dans le planning
     *  - 04/11 « Entraînement »       → passé (hier), hors liste
     *  - 05/11 « Soirée du club »     → aujourd'hui, toujours listé
     *  - 20/12 « Tournoi de Noël »    → à venir, listé
     */
    private function sampleEvents(): array
    {
        return [
            $this->event(1, '2026-10-12', "Tournoi d'automne"),
            $this->event(2, '2026-11-04', 'Entraînement'),
            $this->event(3, '2026-11-05', 'Soirée du club'),
            $this->event(4, '2026-12-20', 'Tournoi de Noël'),
        ];
    }

    public function testListUnderPlanningKeepsOnlyTodayAndUpcomingEvents(): void
    {
        $upcoming = AgendaCalendar::upcoming($this->sampleEvents(), $this->today());

        self::assertSame(['Soirée du club', 'Tournoi de Noël'], array_map(static fn (Event $e): string => $e->getTitle(), $upcoming));
    }

    public function testListIsEmptyWhenEveryEventIsPast(): void
    {
        $events = [$this->event(1, '2026-09-01', 'Rentrée'), $this->event(2, '2026-10-01', 'Tournoi')];

        self::assertSame([], AgendaCalendar::upcoming($events, $this->today()));
    }

    public function testEventWithoutDateIsIgnored(): void
    {
        $undated = (new Event())->setTitle('Sans date');

        self::assertSame([], AgendaCalendar::upcoming([$undated], $this->today()));
        self::assertSame([], $this->calendar()->entries([$undated], $this->today(), true));
    }

    #[DataProvider('pastCases')]
    public function testIsPast(string $eventDate, bool $expected): void
    {
        self::assertSame($expected, AgendaCalendar::isPast($this->event(1, $eventDate, 'x'), $this->today()));
    }

    public static function pastCases(): iterable
    {
        yield 'hier' => ['2026-11-04', true];
        yield "aujourd'hui" => ['2026-11-05', false];
        yield 'demain' => ['2026-11-06', false];
        yield "l'an dernier" => ['2025-11-05', true];
    }

    public function testIsPastIgnoresTimeOfDay(): void
    {
        // À 23h59 le jour même, l'événement du jour n'est toujours pas passé.
        $lateEvening = new \DateTimeImmutable(self::TODAY.' 23:59:59');

        self::assertFalse(AgendaCalendar::isPast($this->event(1, self::TODAY, 'x'), $lateEvening));
    }

    #[DataProvider('windowCases')]
    public function testPlanningWindowStartsThreeMonthsBackOnTheFirstDay(string $today, string $expected): void
    {
        self::assertSame($expected, AgendaCalendar::windowStart(new \DateTimeImmutable($today))->format('Y-m-d H:i'));
    }

    public static function windowCases(): iterable
    {
        yield 'début novembre' => ['2026-11-05 18:30', '2026-08-01 00:00'];
        yield 'fin de mois (31 → pas de débordement)' => ['2026-05-31', '2026-02-01 00:00'];
        yield "changement d'année" => ['2027-01-15', '2026-10-01 00:00'];
    }

    public function testVisitorSeesPastEventsGreyedWithoutLink(): void
    {
        $entries = $this->calendar()->entries($this->sampleEvents(), $this->today(), canManagePhotos: false);

        self::assertSame([
            ['id' => 1, 'date' => '2026-10-12', 'title' => "Tournoi d'automne", 'past' => true, 'url' => null],
            ['id' => 2, 'date' => '2026-11-04', 'title' => 'Entraînement', 'past' => true, 'url' => null],
            ['id' => 3, 'date' => '2026-11-05', 'title' => 'Soirée du club', 'past' => false, 'url' => '#event-3'],
            ['id' => 4, 'date' => '2026-12-20', 'title' => 'Tournoi de Noël', 'past' => false, 'url' => '#event-4'],
        ], $entries);
    }

    public function testAdminPastEventsLeadToTheirPhotos(): void
    {
        $entries = $this->calendar()->entries($this->sampleEvents(), $this->today(), canManagePhotos: true);

        self::assertSame(
            ['/admin/events/1/edit#event-photos', '/admin/events/2/edit#event-photos', '#event-3', '#event-4'],
            array_column($entries, 'url'),
        );
    }

    private function calendar(): AgendaCalendar
    {
        $urls = $this->createStub(UrlGeneratorInterface::class);
        $urls->method('generate')->willReturnCallback(
            static fn (string $route, array $params): string => match ($route) {
                'admin_event_edit' => '/admin/events/'.$params['id'].'/edit',
            },
        );

        return new AgendaCalendar($urls);
    }

    private function today(): \DateTimeImmutable
    {
        return new \DateTimeImmutable(self::TODAY);
    }

    private function event(int $id, string $date, string $title): Event
    {
        $event = (new Event())->setDate(new \DateTimeImmutable($date))->setTitle($title)->setDescription('…');
        (new \ReflectionProperty(Event::class, 'id'))->setValue($event, $id);

        return $event;
    }
}
