<?php

namespace App\Controller;

use App\Service\ContactMessageStore;
use App\Service\ContactMessageMailer;
use App\Repository\EventRepository;
use App\Repository\AttendanceRepository;
use App\Entity\Attendance;
use App\Entity\Event;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Twig\Environment;

final class SiteController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly ContactMessageStore $messages,
        private readonly ContactMessageMailer $contactMailer,
        private readonly CsrfTokenManagerInterface $csrfTokens,
        private readonly EventRepository $eventRepository,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[Autowire('%app.shop_enabled%')]
        private readonly bool $shopEnabled,
    ) {
    }

    #[Route('/', name: 'site_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        $this->attendanceRepository->removeForPastEvents(new \DateTimeImmutable('today'));
        $events = $this->eventRepository->findUpcoming();
        $attendanceEventIds = [];
        $user = $this->security->getUser();
        if ($user instanceof User) {
            foreach ($this->attendanceRepository->findForUserAndEvents($user, $events) as $attendance) {
                $attendanceEventIds[] = $attendance->getEvent()->getId();
            }
        }

        return new Response($this->twig->render('home/index.html.twig', [
            'events' => $events,
            'attendance_event_ids' => $attendanceEventIds,
            'csrf_token' => $this->csrfTokens->getToken('contact')->getValue(),
            'message_error' => $request->getSession()->getFlashBag()->get('contact_error')[0] ?? null,
            'message_success' => $request->getSession()->getFlashBag()->get('contact_success')[0] ?? null,
        ]));
    }

    #[Route('/events/{id}/attendance', name: 'event_attendance_toggle', methods: ['POST'])]
    public function toggleAttendance(Event $event, Request $request): RedirectResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new RedirectResponse('/connexion');
        }

        if (!$this->csrfTokens->isTokenValid(new CsrfToken('attendance-'.$event->getId(), (string) $request->request->get('_token')))) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('Jeton CSRF invalide.');
        }

        $attendance = $this->attendanceRepository->findOneBy(['event' => $event, 'user' => $user]);
        if ($attendance instanceof Attendance) {
            $this->entityManager->remove($attendance);
            $message = 'Votre présence a été annulée.';
        } else {
            $this->entityManager->persist(new Attendance($event, $user));
            $message = 'Votre présence est enregistrée.';
        }
        $this->entityManager->flush();

        $request->getSession()->getFlashBag()->add('attendance', $message);
        return new RedirectResponse($request->headers->get('referer') ?: '/#agenda');
    }

    #[Route('/boutique', name: 'site_shop', methods: ['GET'])]
    public function shop(Request $request): Response
    {
        // Boutique conservée telle quelle mais masquée tant que SHOP_ENABLED vaut 0.
        if (!$this->shopEnabled) {
            throw new NotFoundHttpException();
        }

        return new Response($this->twig->render('shop/index.html.twig', [
            'csrf_token' => $this->csrfTokens->getToken('contact')->getValue(),
            'message_error' => $request->getSession()->getFlashBag()->get('contact_error')[0] ?? null,
            'message_success' => $request->getSession()->getFlashBag()->get('contact_success')[0] ?? null,
        ]));
    }

    #[Route('/contact', name: 'site_contact', methods: ['POST'])]
    public function contact(Request $request): RedirectResponse
    {
        $token = new CsrfToken('contact', (string) $request->request->get('_token'));
        $name = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));
        $message = trim((string) $request->request->get('message'));
        $redirect = $request->headers->get('referer') ?: '/';
        $account = $this->security->getUser();
        if ($account instanceof User) {
            $name = trim($account->getFirstName().' '.$account->getLastName());
            $email = $account->getEmail();
        }

        if (!$this->csrfTokens->isTokenValid($token)) {
            $request->getSession()->getFlashBag()->add('contact_error', 'Le formulaire a expiré. Merci de réessayer.');
            return new RedirectResponse($redirect);
        }

        if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || mb_strlen($message) < 3 || mb_strlen($message) > 5000) {
            $request->getSession()->getFlashBag()->add('contact_error', 'Vérifiez les informations saisies puis réessayez.');
            return new RedirectResponse($redirect);
        }

        try {
            $this->contactMailer->send($name, $email, $message);
            $this->messages->save($name, $email, $message);
        } catch (\Throwable $exception) {
            $request->getSession()->getFlashBag()->add('contact_error', 'Votre message n’a pas pu être envoyé. Vérifiez la configuration email.');
            return new RedirectResponse($redirect);
        }

        $request->getSession()->getFlashBag()->add('contact_success', 'Merci ! Votre message a bien été envoyé.');
        return new RedirectResponse($redirect);
    }

}
