<?php

namespace App\Service;

use RuntimeException;

final class ContactMessageStore
{
    public function __construct(private readonly string $file)
    {
    }

    public function save(string $name, string $email, string $message): void
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Impossible de créer le dossier de stockage des messages.');
        }

        $messages = [];
        if (is_file($this->file)) {
            $decoded = json_decode((string) file_get_contents($this->file), true);
            if (is_array($decoded)) {
                $messages = $decoded;
            }
        }

        $messages[] = [
            'name' => $name,
            'email' => $email,
            'message' => $message,
            'created_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];

        if (file_put_contents($this->file, json_encode($messages, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Impossible d’enregistrer le message.');
        }
    }

    /**
     * Supprime les messages reçus avant la date limite (durée de conservation RGPD) et retourne leur nombre.
     */
    public function purgeOlderThan(\DateTimeImmutable $limit, bool $dryRun = false): int
    {
        if (!is_file($this->file)) {
            return 0;
        }
        $messages = json_decode((string) file_get_contents($this->file), true);
        if (!is_array($messages)) {
            return 0;
        }

        $kept = array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => is_array($message)
                && isset($message['created_at'])
                && new \DateTimeImmutable((string) $message['created_at']) >= $limit,
        ));
        $removed = count($messages) - count($kept);
        if ($removed > 0 && !$dryRun
            && file_put_contents($this->file, json_encode($kept, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
            throw new RuntimeException('Impossible de mettre à jour le fichier des messages.');
        }

        return $removed;
    }
}
