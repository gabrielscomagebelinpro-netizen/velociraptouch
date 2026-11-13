<?php

namespace App\Controller;

use App\Service\AgendaCalendar;
use App\Service\ContactMessageStore;
use App\Service\ContactMessageMailer;
use App\Repository\EventRepository;
use App\Repository\EventPhotoRepository;
use App\Repository\AttendanceRepository;
use App\Repository\SponsorRepository;
use App\Entity\Attendance;
use App\Entity\Event;
use App\Entity\User;
use App\Entity\Preorder;
use App\Entity\Product;
use App\Repository\ProductRepository;
use App\Repository\PreorderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
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
        private readonly EventPhotoRepository $eventPhotoRepository,
        private readonly AttendanceRepository $attendanceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        #[Autowire('%app.shop_enabled%')]
        private readonly bool $shopEnabled,
        private readonly RateLimiterFactory $contactFormLimiter,
        private readonly AgendaCalendar $agendaCalendar,
        private readonly SponsorRepository $sponsorRepository,
        private readonly ProductRepository $productRepository,
        private readonly PreorderRepository $preorderRepository,
        #[Autowire('%kernel.project_dir%/var/uploads/events')]
        private readonly string $eventPhotoDirectory,
    ) {
    }

    #[Route('/', name: 'site_home', methods: ['GET'])]
    public function home(Request $request): Response
    {
        $today = new \DateTimeImmutable('today');
        $this->attendanceRepository->removeForPastEvents($today);
        // Le planning remonte quelques mois en arrière ; la liste en dessous ne garde que l'à-venir.
        $calendarEvents = $this->eventRepository->findSince(AgendaCalendar::windowStart($today));
        $events = AgendaCalendar::upcoming($calendarEvents, $today);
        $attendanceEventIds = [];
        $user = $this->security->getUser();
        if ($user instanceof User) {
            foreach ($this->attendanceRepository->findForUserAndEvents($user, $events) as $attendance) {
                $attendanceEventIds[] = $attendance->getEvent()->getId();
            }
        }

        return new Response($this->twig->render('home/index.html.twig', [
            'events' => $events,
            'photo_counts' => $this->eventPhotoRepository->countByEvent(),
            'past_events' => $this->eventRepository->findPast(),
            'calendar_entries' => $this->agendaCalendar->entries($calendarEvents, $today, $this->security->isGranted('ROLE_ADMIN')),
            'attendance_event_ids' => $attendanceEventIds,
            'sponsors' => $this->sponsorRepository->findGroupedByTier(),
            'csrf_token' => $this->csrfTokens->getToken('contact')->getValue(),
            'message_error' => $request->getSession()->getFlashBag()->get('contact_error')[0] ?? null,
            'message_success' => $request->getSession()->getFlashBag()->get('contact_success')[0] ?? null,
        ]));
    }

    #[Route('/evenements/{id}/photos', name: 'event_photos', methods: ['GET'])]
    public function photos(Event $event): Response
    {
        return new Response($this->twig->render('home/event_photos.html.twig', [
            'event' => $event,
            'photos' => $this->eventPhotoRepository->findForEvent($event),
        ]));
    }

    #[Route('/evenements/{id}/photos/{filename}', name: 'event_photo_view', methods: ['GET'])]
    public function photo(Event $event, string $filename): BinaryFileResponse
    {
        if (!preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp|heic|heif)\z/', $filename)) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        $photo = $this->eventPhotoRepository->findOneBy(['event' => $event, 'filename' => $filename]);
        $path = $this->eventPhotoDirectory.'/'.$filename;
        if ($photo === null || !is_file($path)) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        $response = new BinaryFileResponse($path, 200, ['Content-Type' => $photo->getMimeType()]);
        $response->setPrivate();
        $response->setMaxAge(31536000);
        $response->setImmutable(true);

        return $response;
    }

    #[Route('/evenements/{id}/photos/{filename}/telecharger', name: 'event_photo_download', methods: ['GET'])]
    public function downloadPhoto(Event $event, string $filename): BinaryFileResponse
    {
        if (!preg_match('/\A[a-f0-9]{32}\.(jpg|jpeg|png|webp|heic|heif)\z/', $filename)) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        $photo = $this->eventPhotoRepository->findOneBy(['event' => $event, 'filename' => $filename]);
        $path = $this->eventPhotoDirectory.'/'.$filename;
        if ($photo === null || !is_file($path)) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        $response = new BinaryFileResponse($path, 200, ['Content-Type' => $photo->getMimeType()]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $photo->getOriginalName());

        return $response;
    }

    #[Route('/evenements/{id}/photos.zip', name: 'event_photos_zip', methods: ['GET'])]
    public function downloadPhotosZip(Event $event): BinaryFileResponse
    {
        $eventPhotos = $this->eventPhotoRepository->findForEvent($event);
        if ($eventPhotos === []) {
            throw new NotFoundHttpException('Aucune photo pour cet événement.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'photos-');
        $zip = new \ZipArchive();
        if ($zipPath === false || $zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('L’archive des photos ne peut pas être créée.');
        }

        $usedNames = [];
        foreach ($eventPhotos as $photo) {
            $path = $this->eventPhotoDirectory.'/'.$photo->getFilename();
            if (!is_file($path)) {
                continue;
            }

            $name = trim(str_replace(['/', '\\', "\0"], '-', $photo->getOriginalName())) ?: 'photo';
            $base = pathinfo($name, PATHINFO_FILENAME);
            $extension = pathinfo($name, PATHINFO_EXTENSION) ?: pathinfo($photo->getFilename(), PATHINFO_EXTENSION);
            if ($extension !== '' && pathinfo($name, PATHINFO_EXTENSION) === '') {
                $name .= '.'.$extension;
            }
            for ($i = 2; isset($usedNames[mb_strtolower($name)]); $i++) {
                $name = sprintf('%s (%d).%s', $base, $i, $extension);
            }
            $usedNames[mb_strtolower($name)] = true;
            $zip->addFile($path, $name);
            $zip->setCompressionName($name, \ZipArchive::CM_STORE);
        }
        $zip->close();

        $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $event->getTitle()) ?: 'evenement';
        $slug = trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $slug)), '-') ?: 'evenement';
        $filename = sprintf('photos-%s-%s.zip', $event->getDate()?->format('Y-m-d') ?? 'sans-date', $slug);

        $response = new BinaryFileResponse($zipPath, 200, ['Content-Type' => 'application/zip']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->deleteFileAfterSend();

        return $response;
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
        return new RedirectResponse($this->safeReferer($request, '/#agenda'));
    }

    /**
     * Page d'origine (en-tête Referer) seulement si elle appartient à ce site ; sinon la page par défaut.
     * Empêche un autre site de se servir du nôtre pour rediriger vers une adresse piégée.
     */
    private function safeReferer(Request $request, string $fallback): string
    {
        $referer = (string) $request->headers->get('referer');
        $parts = parse_url($referer);
        if (!is_array($parts) || ($parts['host'] ?? null) !== $request->getHost()) {
            return $fallback;
        }

        $path = $parts['path'] ?? '/';
        if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return $fallback;
        }

        return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    #[Route('/boutique', name: 'site_shop', methods: ['GET'])]
    public function shop(Request $request): Response
    {
        if (!$this->shopEnabled) {
            throw new NotFoundHttpException();
        }

        $user = $this->security->getUser();
        $preordersByProduct = [];
        if ($user instanceof User) {
            foreach ($this->preorderRepository->findForUser($user) as $preorder) {
                $preordersByProduct[$preorder->getProduct()->getId()] = $preorder;
            }
        }

        return new Response($this->twig->render('shop/index.html.twig', [
            'csrf_token' => $this->csrfTokens->getToken('contact')->getValue(),
            'message_error' => $request->getSession()->getFlashBag()->get('contact_error')[0] ?? null,
            'message_success' => $request->getSession()->getFlashBag()->get('contact_success')[0] ?? null,
            'products' => $this->productRepository->findActive(),
            'preordersByProduct' => $preordersByProduct,
            'shopSuccess' => $request->getSession()->getFlashBag()->get('shop_success')[0] ?? null,
        ]));
    }

    #[Route('/boutique/{id}/precommander', name: 'site_shop_preorder', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function preorder(Product $product, Request $request): Response
    {
        if (!$this->shopEnabled || !$product->isActive()) {
            throw new NotFoundHttpException();
        }
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            $request->getSession()->getFlashBag()->add('shop_error', 'Connecte-toi pour préparer une précommande.');
            return new RedirectResponse('/connexion');
        }
        $existingPreorder = $this->preorderRepository->findForUserAndProduct($user, $product);

        if ($request->isMethod('GET')) {
            if ($existingPreorder instanceof Preorder) {
                return new RedirectResponse('/mon-compte/precommandes/'.$existingPreorder->getId().'/modifier');
            }
            return new Response($this->twig->render('shop/preorder.html.twig', [
                'product' => $product,
                'preorderUser' => $user,
                'shopError' => $request->getSession()->getFlashBag()->get('shop_error')[0] ?? null,
            ]));
        }

        $redirect = new RedirectResponse('/boutique');
        if ($existingPreorder instanceof Preorder) {
            $request->getSession()->getFlashBag()->add('error', 'Tu as déjà une précommande pour ce produit. Tu peux la modifier depuis ton compte.');
            return new RedirectResponse('/mon-compte#precommandes');
        }
        if (!$this->csrfTokens->isTokenValid(new CsrfToken('preorder-'.$product->getId(), (string) $request->request->get('_token')))) {
            $request->getSession()->getFlashBag()->add('shop_error', 'Le formulaire a expiré. Merci de réessayer.');
            return $redirect;
        }
        $name = $user->getFirstName().' '.$user->getLastName();
        $email = $user->getEmail();
        $quantity = filter_var($request->request->get('quantity'), FILTER_VALIDATE_INT);
        $jerseySize = trim((string) $request->request->get('jersey_size'));
        $jerseyNumber = filter_var($request->request->get('jersey_number'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
        $shortSize = trim((string) $request->request->get('short_size'));
        $notes = trim((string) $request->request->get('notes'));
        if ($name === '' || mb_strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
            || $quantity === false || $quantity < 1 || $quantity > 20
            || ($product->isRequiresJerseySize() && !in_array($jerseySize, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true))
            || ($product->isRequiresJerseySize() && $jerseyNumber === false)
            || ($product->isRequiresShortSize() && !in_array($shortSize, ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true))
            || mb_strlen($notes) > 1000) {
            $request->getSession()->getFlashBag()->add('shop_error', 'Vérifie les informations et les tailles choisies.');
            return $redirect;
        }
        $order = (new Preorder($product))
            ->setName($name)->setEmail($email)->setQuantity($quantity)
            ->setJerseySize($product->isRequiresJerseySize() ? $jerseySize : null)
            ->setJerseyNumber($product->isRequiresJerseySize() ? $jerseyNumber : null)
            ->setShortSize($product->isRequiresShortSize() ? $shortSize : null)
            ->setNotes($notes);
        $order->setUser($user)->setName($name)->setEmail($email);
        $this->entityManager->persist($order);
        $this->entityManager->flush();
        $request->getSession()->getFlashBag()->add('shop_success', 'Ta précommande est enregistrée. Nous reviendrons vers toi pour la suite.');
        return $redirect;
    }

    #[Route('/contact', name: 'site_contact', methods: ['POST'])]
    public function contact(Request $request): RedirectResponse
    {
        $token = new CsrfToken('contact', (string) $request->request->get('_token'));
        $name = trim((string) $request->request->get('name'));
        $email = trim((string) $request->request->get('email'));
        $message = trim((string) $request->request->get('message'));
        $redirect = $this->safeReferer($request, '/').'#contact';
        $account = $this->security->getUser();

        // Champ piège invisible : seuls les robots le remplissent. On fait comme si l'envoi avait réussi.
        if (trim((string) $request->request->get('website')) !== '') {
            $request->getSession()->getFlashBag()->add('contact_success', 'Merci ! Votre message a bien été envoyé.');
            return new RedirectResponse($redirect);
        }
        if (!$this->contactFormLimiter->create($request->getClientIp() ?? 'inconnu')->consume()->isAccepted()) {
            $request->getSession()->getFlashBag()->add('contact_error', 'Trop de messages envoyés. Réessaie dans quelques minutes.');
            return new RedirectResponse($redirect);
        }
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
