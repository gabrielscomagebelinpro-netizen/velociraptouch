<?php

namespace App\Controller;

use App\Entity\Event;
use App\Entity\EventPhoto;
use App\Form\EventType;
use App\Repository\EventPhotoRepository;
use App\Repository\EventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Repository\AttendanceRepository;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/admin')]
final class AdminController extends AbstractController
{
    public function __construct(private readonly string $eventPhotoDirectory)
    {
    }

    #[Route('', name: 'admin_dashboard', methods: ['GET', 'POST'])]
    public function dashboard(Request $request, EntityManagerInterface $entityManager, EventRepository $events, AttendanceRepository $attendances, EventPhotoRepository $photos): Response
    {
        $attendances->removeForPastEvents(new \DateTimeImmutable('today'));
        $event = new Event();
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($event);
            $entityManager->flush();
            $this->addFlash('success', 'Événement ajouté à l’agenda.');
            return $this->redirectToRoute('admin_dashboard');
        }

        return $this->render('admin/dashboard.html.twig', [
            'form' => $form,
            'events' => $events->findUpcoming(),
            'photoAlbums' => $photos->findEventsWithPhotos(),
        ]);
    }

    #[Route('/events/{id}/edit', name: 'admin_event_edit', methods: ['GET', 'POST'])]
    public function edit(Event $event, Request $request, EntityManagerInterface $entityManager, EventPhotoRepository $photos): Response
    {
        $form = $this->createForm(EventType::class, $event);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Événement modifié.');
            return $this->redirectToRoute('admin_dashboard');
        }
        return $this->render('admin/event_form.html.twig', ['form' => $form, 'event' => $event, 'photos' => $photos->findForEvent($event)]);
    }

    #[Route('/events/{id}/photos', name: 'admin_event_photos_upload', methods: ['POST'])]
    public function uploadPhotos(Event $event, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('event-photos-'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $files = $request->files->all('photos');
        $files = array_values(array_filter(
            is_array($files) ? $files : [$files],
            static fn (mixed $file): bool => $file instanceof UploadedFile,
        ));
        if ($files === []) {
            $error = (int) $request->server->get('CONTENT_LENGTH', 0) > 0 && $request->request->all() === []
                ? sprintf('Les photos dépassent la taille maximale autorisée par le serveur (%s par envoi).', ini_get('post_max_size'))
                : 'Sélectionne au moins une photo.';

            return $this->photoUploadResponse($request, $event, 0, [$error]);
        }

        if (!is_dir($this->eventPhotoDirectory) && !mkdir($this->eventPhotoDirectory, 0750, true) && !is_dir($this->eventPhotoDirectory)) {
            throw new \RuntimeException('Le dossier de stockage des photos ne peut pas être créé.');
        }

        $allowedTypes = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
        ];
        $uploaded = 0;
        $errors = [];

        foreach ($files as $file) {
            $name = $file->getClientOriginalName();
            if (!$file->isValid()) {
                $errors[] = in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                    ? sprintf('« %s » dépasse la taille autorisée par le serveur (%s).', $name, ini_get('upload_max_filesize'))
                    : sprintf('« %s » n’a pas pu être envoyée.', $name);
                continue;
            }
            if ($file->getSize() > 25 * 1024 * 1024) {
                $errors[] = sprintf('« %s » dépasse la limite de 25 Mo.', $name);
                continue;
            }

            $mimeType = $file->getMimeType();
            $isSupportedImage = in_array($mimeType, ['image/heic', 'image/heif'], true)
                || @getimagesize($file->getPathname()) !== false;
            if (!is_string($mimeType) || !isset($allowedTypes[$mimeType]) || !$isSupportedImage) {
                $errors[] = sprintf('« %s » n’est pas une image JPEG, PNG, WebP, HEIC ou HEIF.', $name);
                continue;
            }

            $filename = bin2hex(random_bytes(16)).'.'.$allowedTypes[$mimeType];
            // La taille doit être lue avant le déplacement : le fichier temporaire n'existe plus ensuite.
            $size = (int) $file->getSize();
            $file->move($this->eventPhotoDirectory, $filename);
            $entityManager->persist(new EventPhoto(
                $event,
                $filename,
                mb_substr($name, 0, 255),
                $mimeType,
                $size,
            ));
            $uploaded++;
        }

        $entityManager->flush();

        return $this->photoUploadResponse($request, $event, $uploaded, $errors);
    }

    /**
     * Réponse JSON pour l'envoi photo par photo en JavaScript, redirection avec messages flash sinon.
     *
     * @param list<string> $errors
     */
    private function photoUploadResponse(Request $request, Event $event, int $uploaded, array $errors): Response
    {
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(
                ['uploaded' => $uploaded, 'errors' => $errors],
                $uploaded === 0 && $errors !== [] ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK,
            );
        }

        foreach ($errors as $error) {
            $this->addFlash('error', $error);
        }
        if ($uploaded > 0) {
            $this->addFlash('success', sprintf('%d photo%s ajoutée%s.', $uploaded, $uploaded > 1 ? 's' : '', $uploaded > 1 ? 's' : ''));
        }

        return $this->redirectToRoute('admin_event_edit', ['id' => $event->getId(), '_fragment' => 'event-photos']);
    }

    #[Route('/events/{id}/photos/{filename}', name: 'admin_event_photo_view', methods: ['GET'])]
    public function viewPhoto(Event $event, string $filename, EventPhotoRepository $photos): BinaryFileResponse
    {
        if (!preg_match('/\A[a-f0-9]{32}\.(jpg|png|webp|heic|heif)\z/', $filename)) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        $photo = $photos->findOneBy(['event' => $event, 'filename' => $filename]);
        $path = $this->eventPhotoDirectory.'/'.$filename;
        if (!$photo instanceof EventPhoto || !is_file($path)) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        return new BinaryFileResponse($path, 200, ['Content-Type' => $photo->getMimeType()]);
    }

    #[Route('/events/{id}/photos/{photoId}/download', name: 'admin_event_photo_download', methods: ['GET'], requirements: ['photoId' => '\d+'])]
    public function downloadPhoto(Event $event, int $photoId, EventPhotoRepository $photos): BinaryFileResponse
    {
        $photo = $photos->find($photoId);
        $path = $photo instanceof EventPhoto ? $this->eventPhotoDirectory.'/'.$photo->getFilename() : '';
        if (!$photo instanceof EventPhoto || $photo->getEvent()->getId() !== $event->getId() || !is_file($path)) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        $response = new BinaryFileResponse($path, 200, ['Content-Type' => $photo->getMimeType()]);
        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            $this->downloadName($photo),
            $this->asciiName($this->downloadName($photo)),
        );

        return $response;
    }

    #[Route('/events/{id}/photos.zip', name: 'admin_event_photos_zip', methods: ['GET'])]
    public function downloadPhotosZip(Event $event, EventPhotoRepository $photos): BinaryFileResponse
    {
        $eventPhotos = $photos->findForEvent($event);
        if ($eventPhotos === []) {
            throw $this->createNotFoundException('Aucune photo pour cet événement.');
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
            // Deux photos peuvent porter le même nom d'origine : on suffixe les doublons.
            $name = $this->downloadName($photo);
            $base = pathinfo($name, PATHINFO_FILENAME);
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            for ($i = 2; isset($usedNames[mb_strtolower($name)]); $i++) {
                $name = sprintf('%s (%d).%s', $base, $i, $extension);
            }
            $usedNames[mb_strtolower($name)] = true;
            $zip->addFile($path, $name);
            // Les photos sont déjà compressées : on les stocke telles quelles, plus rapide et sans perte.
            $zip->setCompressionName($name, \ZipArchive::CM_STORE);
        }
        $zip->close();

        $eventSlug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $event->getTitle()) ?: 'evenement';
        $eventSlug = trim(strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $eventSlug)), '-') ?: 'evenement';
        $filename = sprintf('photos-%s-%s.zip', $event->getDate()?->format('Y-m-d') ?? 'sans-date', $eventSlug);

        $response = new BinaryFileResponse($zipPath, 200, ['Content-Type' => 'application/zip']);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $response->deleteFileAfterSend();

        return $response;
    }

    private function downloadName(EventPhoto $photo): string
    {
        $extension = pathinfo($photo->getFilename(), PATHINFO_EXTENSION);
        $name = trim(str_replace(['/', '\\', "\0"], '-', $photo->getOriginalName()));
        if ($name === '' || $name === '.' || $name === '..') {
            $name = 'photo';
        }
        if (strtolower(pathinfo($name, PATHINFO_EXTENSION)) === '') {
            $name .= '.'.$extension;
        }

        return $name;
    }

    private function asciiName(string $name): string
    {
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $name) ?: 'photo';

        return (string) preg_replace('/[^A-Za-z0-9._ ()-]+/', '-', $ascii);
    }

    #[Route('/events/{id}/photos/{photoId}/delete', name: 'admin_event_photo_delete', methods: ['POST'])]
    public function deletePhoto(Event $event, int $photoId, Request $request, EventPhotoRepository $photos, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-event-photo-'.$photoId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $photo = $photos->find($photoId);
        if (!$photo instanceof EventPhoto || $photo->getEvent()->getId() !== $event->getId()) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        $path = $this->eventPhotoDirectory.'/'.$photo->getFilename();
        if (is_file($path) && !unlink($path)) {
            throw new \RuntimeException('La photo ne peut pas être supprimée du stockage.');
        }
        $entityManager->remove($photo);
        $entityManager->flush();

        $this->addFlash('success', 'Photo supprimée.');
        return $this->redirectToRoute('admin_event_edit', ['id' => $event->getId()]);
    }

    #[Route('/events/{id}/delete', name: 'admin_event_delete', methods: ['POST'])]
    public function delete(Event $event, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-event-'.$event->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $entityManager->remove($event);
        $entityManager->flush();
        $this->addFlash('success', 'Événement supprimé.');
        return $this->redirectToRoute('admin_dashboard');
    }

    #[Route('/events/{id}/attendance.pdf', name: 'admin_event_attendance_pdf', methods: ['GET'])]
    public function attendancePdf(Event $event, AttendanceRepository $attendances): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($pdfOptions);
        $pdf->loadHtml($this->renderView('admin/attendance_pdf.html.twig', [
            'event' => $event,
            'attendances' => $attendances->findForEvent($event),
        ]), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $eventSlug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $event->getTitle()) ?: 'evenement';
        $eventSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $eventSlug));
        $eventSlug = trim($eventSlug, '-') ?: 'evenement';
        $filename = sprintf('presence-%s-%s.pdf', (new \DateTimeImmutable())->format('Y-m-d'), $eventSlug);

        return new Response($pdf->output(), Response::HTTP_OK, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/login', name: 'admin_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute(
                $this->isGranted('ROLE_ADMIN') ? 'admin_dashboard' : 'site_home',
            );
        }

        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route('/logout', name: 'admin_logout', methods: ['GET'])]
    public function logout(): never
    {
        throw new \LogicException('Cette route est interceptée par le firewall Symfony.');
    }
}
