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
}
