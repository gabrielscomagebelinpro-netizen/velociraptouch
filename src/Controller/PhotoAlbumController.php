<?php

namespace App\Controller;

use App\Entity\EventPhoto;
use App\Entity\PhotoAlbum;
use App\Form\PhotoAlbumType;
use App\Repository\EventPhotoRepository;
use App\Repository\PhotoAlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Routing\Attribute\Route;

final class PhotoAlbumController extends AbstractController
{
    public function __construct(private readonly string $photoDirectory)
    {
    }

    #[Route('/photos', name: 'photo_albums', methods: ['GET'])]
    public function publicAlbums(PhotoAlbumRepository $albums, EventPhotoRepository $photos): Response
    {
        return $this->render('home/photo_albums.html.twig', [
            'albums' => $albums->findNewest(),
            'photoCounts' => $photos->countByAlbum(),
        ]);
    }

    #[Route('/photos/{id}', name: 'photo_album_view', methods: ['GET'])]
    public function publicAlbum(PhotoAlbum $album, EventPhotoRepository $photos): Response
    {
        return $this->render('home/photo_album.html.twig', [
            'album' => $album,
            'photos' => $photos->findForAlbum($album),
        ]);
    }

    #[Route('/photos/{id}/{filename}', name: 'photo_album_photo_view', methods: ['GET'])]
    public function publicPhoto(PhotoAlbum $album, string $filename, EventPhotoRepository $photos): BinaryFileResponse
    {
        $photo = $photos->findOneBy(['album' => $album, 'filename' => $filename]);
        $path = $this->photoDirectory.'/'.$filename;
        if (!$photo instanceof EventPhoto || !is_file($path)) {
            throw $this->createNotFoundException('Photo introuvable.');
        }
        $response = new BinaryFileResponse($path, 200, ['Content-Type' => $photo->getMimeType()]);
        $response->setPrivate();
        return $response;
    }

    #[Route('/photos/{id}/{filename}/telecharger', name: 'photo_album_photo_download', methods: ['GET'])]
    public function downloadPhoto(PhotoAlbum $album, string $filename, EventPhotoRepository $photos): BinaryFileResponse
    {
        $photo = $photos->findOneBy(['album' => $album, 'filename' => $filename]);
        $path = $photo instanceof EventPhoto ? $this->photoDirectory.'/'.$photo->getFilename() : '';
        if (!$photo instanceof EventPhoto || !is_file($path)) {
            throw $this->createNotFoundException('Photo introuvable.');
        }
        $response = new BinaryFileResponse($path);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $photo->getOriginalName());
        return $response;
    }

    #[Route('/photos/{id}/photos.zip', name: 'photo_album_zip', methods: ['GET'])]
    public function zipAlbum(PhotoAlbum $album, EventPhotoRepository $photos): BinaryFileResponse
    {
        $albumPhotos = $photos->findForAlbum($album);
        if ($albumPhotos === []) {
            throw $this->createNotFoundException('Aucune photo dans cet album.');
        }
        $zipPath = tempnam(sys_get_temp_dir(), 'album-');
        $zip = new \ZipArchive();
        if ($zipPath === false || $zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('L’archive ne peut pas être créée.');
        }
        $used = [];
        foreach ($albumPhotos as $photo) {
            $path = $this->photoDirectory.'/'.$photo->getFilename();
            if (!is_file($path)) continue;
            $name = trim(str_replace(['/', '\\', "\0"], '-', $photo->getOriginalName())) ?: 'photo';
            $extension = pathinfo($name, PATHINFO_EXTENSION);
            if ($extension === '') $name .= '.'.pathinfo($photo->getFilename(), PATHINFO_EXTENSION);
            $key = mb_strtolower($name);
            for ($i = 2; isset($used[$key]); $i++) {
                $base = pathinfo($name, PATHINFO_FILENAME);
                $ext = pathinfo($name, PATHINFO_EXTENSION);
                $name = sprintf('%s (%d).%s', $base, $i, $ext);
                $key = mb_strtolower($name);
            }
            $used[$key] = true;
            $zip->addFile($path, $name);
            $zip->setCompressionName($name, \ZipArchive::CM_STORE);
        }
        $zip->close();
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $album->getTitle()) ?: 'album'));
        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'photos-'.$slug.'.zip');
        $response->deleteFileAfterSend();
        return $response;
    }

    #[Route('/admin/albums', name: 'admin_photo_albums', methods: ['GET'])]
    public function adminAlbums(PhotoAlbumRepository $albums, EventPhotoRepository $photos): Response
    {
        return $this->render('admin/photo_albums.html.twig', [
            'albums' => $albums->findNewest(),
            'photoCounts' => $photos->countByAlbum(),
        ]);
    }

    #[Route('/admin/albums/nouveau', name: 'admin_photo_album_new', methods: ['GET', 'POST'])]
    public function newAlbum(Request $request, EntityManagerInterface $entityManager): Response
    {
        $album = new PhotoAlbum();
        $form = $this->createForm(PhotoAlbumType::class, $album);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($album);
            $entityManager->flush();
            $this->addFlash('success', 'Album créé. Vous pouvez maintenant ajouter les photos.');
            return $this->redirectToRoute('admin_photo_album_edit', ['id' => $album->getId(), '_fragment' => 'album-photos']);
        }
        return $this->render('admin/photo_album_form.html.twig', ['form' => $form, 'album' => null]);
    }

    #[Route('/admin/albums/{id}', name: 'admin_photo_album_edit', methods: ['GET', 'POST'])]
    public function editAlbum(PhotoAlbum $album, Request $request, EntityManagerInterface $entityManager, EventPhotoRepository $photos): Response
    {
        $form = $this->createForm(PhotoAlbumType::class, $album);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', 'Album enregistré.');
            return $this->redirectToRoute('admin_photo_album_edit', ['id' => $album->getId()]);
        }
        return $this->render('admin/photo_album_form.html.twig', ['form' => $form, 'album' => $album, 'photos' => $photos->findForAlbum($album)]);
    }

    #[Route('/admin/albums/{id}/photos', name: 'admin_photo_album_upload', methods: ['POST'])]
    public function uploadAlbumPhotos(PhotoAlbum $album, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('upload-photo-album-'.$album->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $files = $request->files->all('photos');
        if ($files === []) {
            $this->addFlash('error', 'Sélectionne au moins une photo.');
            return $this->redirectToRoute('admin_photo_album_edit', ['id' => $album->getId(), '_fragment' => 'album-photos']);
        }
        $files = array_values(array_filter(is_array($files) ? $files : [$files], static fn(mixed $file): bool => $file instanceof UploadedFile));
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/heic' => 'heic', 'image/heif' => 'heif'];
        if (!is_dir($this->photoDirectory) && !mkdir($this->photoDirectory, 0750, true) && !is_dir($this->photoDirectory)) {
            throw new \RuntimeException('Le dossier de stockage des photos ne peut pas être créé.');
        }
        $uploaded = 0;
        $errors = [];
        foreach ($files as $file) {
            if (!$file->isValid()) {
                $errors[] = sprintf('« %s » n’a pas pu être envoyée.', $file->getClientOriginalName());
                continue;
            }

            $pathname = $file->getPathname();
            if (!is_string($pathname) || !is_file($pathname)) {
                $errors[] = sprintf('« %s » n’a pas pu être lue.', $file->getClientOriginalName());
                continue;
            }

            try {
                $size = $file->getSize();
                $mime = $file->getMimeType();
            } catch (\Throwable) {
                $errors[] = sprintf('« %s » n’a pas pu être lue.', $file->getClientOriginalName());
                continue;
            }
            if ($size === false || $size > 25*1024*1024) {
                $errors[] = sprintf('« %s » dépasse la limite de 25 Mo.', $file->getClientOriginalName());
                continue;
            }
            if (!isset($allowed[$mime])) {
                $errors[] = sprintf('« %s » n’est pas une image JPEG, PNG, WebP, HEIC ou HEIF.', $file->getClientOriginalName());
                continue;
            }

            $originalName = mb_substr($file->getClientOriginalName(), 0, 255);
            $filename = bin2hex(random_bytes(16)).'.'.$allowed[$mime];
            $file->move($this->photoDirectory, $filename);
            $entityManager->persist(new EventPhoto($album, $filename, $originalName, $mime, (int) $size));
            ++$uploaded;
        }
        $entityManager->flush();
        $message = $uploaded > 0 ? sprintf('%d photo%s ajoutée%s.', $uploaded, $uploaded > 1 ? 's' : '', $uploaded > 1 ? 's' : '') : 'Aucune photo valide ajoutée.';
        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['uploaded' => $uploaded, 'errors' => $errors], Response::HTTP_OK);
        }
        $this->addFlash($uploaded > 0 ? 'success' : 'error', $message);
        return $this->redirectToRoute('admin_photo_album_edit', ['id' => $album->getId(), '_fragment' => 'album-photos']);
    }

    #[Route('/admin/albums/{id}/photos/{photoId}/supprimer', name: 'admin_photo_album_photo_delete', methods: ['POST'], requirements: ['photoId' => '\d+'])]
    public function deleteAlbumPhoto(PhotoAlbum $album, int $photoId, Request $request, EventPhotoRepository $photos, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-album-photo-'.$photoId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $photo = $photos->find($photoId);
        if (!$photo instanceof EventPhoto || $photo->getAlbum()?->getId() !== $album->getId()) {
            throw $this->createNotFoundException('Photo introuvable.');
        }

        $path = $this->photoDirectory.'/'.$photo->getFilename();
        if (is_file($path)) {
            unlink($path);
        }
        $entityManager->remove($photo);
        $entityManager->flush();
        $this->addFlash('success', 'Photo supprimée de l’album.');

        return $this->redirectToRoute('admin_photo_album_edit', ['id' => $album->getId(), '_fragment' => 'album-photos']);
    }

    #[Route('/admin/albums/{id}/supprimer', name: 'admin_photo_album_delete', methods: ['POST'])]
    public function deleteAlbum(PhotoAlbum $album, Request $request, EntityManagerInterface $entityManager): Response
    {
        if (!$this->isCsrfTokenValid('delete-photo-album-'.$album->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        foreach ($album->getPhotos() as $photo) {
            $path = $this->photoDirectory.'/'.$photo->getFilename();
            if (is_file($path)) unlink($path);
        }
        $entityManager->remove($album);
        $entityManager->flush();
        $this->addFlash('success', 'Album supprimé.');
        return $this->redirectToRoute('admin_photo_albums');
    }
}
