<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Images publiques envoyées depuis l'administration (logos des partenaires, QR code).
 * Elles sont rangées dans public/uploads/<dossier>/ sous un nom aléatoire, puis servies directement.
 */
final class PublicImageStorage
{
    /** Types acceptés (pas de SVG : il peut contenir du code). */
    private const TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public const MAX_SIZE = 5 * 1024 * 1024;

    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    /**
     * @return array{path: string, width: int, height: int}
     *
     * @throws \InvalidArgumentException message affichable si l'image est refusée
     */
    public function store(UploadedFile $file, string $folder): array
    {
        if (!preg_match('/\A[a-z0-9-]+\z/', $folder)) {
            throw new \LogicException('Nom de dossier invalide.');
        }
        if (!$file->isValid()) {
            throw new \InvalidArgumentException(in_array($file->getError(), [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)
                ? sprintf('L’image dépasse la taille autorisée par le serveur (%s).', ini_get('upload_max_filesize'))
                : 'L’image n’a pas pu être envoyée.');
        }
        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('L’image dépasse la limite de 5 Mo.');
        }
        $mimeType = $file->getMimeType();
        $size = @getimagesize($file->getPathname());
        if (!is_string($mimeType) || !isset(self::TYPES[$mimeType]) || $size === false) {
            throw new \InvalidArgumentException('L’image doit être au format JPEG, PNG ou WebP.');
        }

        $directory = $this->publicDir.'/uploads/'.$folder;
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new \RuntimeException('Le dossier des images ne peut pas être créé.');
        }
        $filename = bin2hex(random_bytes(16)).'.'.self::TYPES[$mimeType];
        $file->move($directory, $filename);

        return ['path' => '/uploads/'.$folder.'/'.$filename, 'width' => (int) $size[0], 'height' => (int) $size[1]];
    }

    /** Supprime une image envoyée depuis l'admin. Les images fournies avec le site (/assets/…) ne sont jamais effacées. */
    public function remove(string $path): void
    {
        if (!preg_match('#\A/uploads/[a-z0-9-]+/[a-f0-9]{32}\.(jpg|png|webp)\z#', $path)) {
            return;
        }
        $file = $this->publicDir.$path;
        if (is_file($file)) {
            unlink($file);
        }
    }
}
