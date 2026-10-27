<?php

namespace App\Service;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Journal des actions sensibles (qui a fait quoi, et quand), une ligne JSON par action.
 * Permet de retrouver l'auteur d'une suppression. Conservé 1 an (voir app:rgpd:purge).
 */
final class AdminActionLog
{
    public function __construct(
        private readonly Security $security,
        #[Autowire('%kernel.logs_dir%/admin-actions.log')]
        private readonly string $file,
    ) {
    }

    /**
     * @param array<string, scalar|null> $details
     */
    public function record(string $action, array $details = []): void
    {
        $line = json_encode([
            'date' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'auteur' => $this->security->getUser()?->getUserIdentifier() ?? 'anonyme',
            'action' => $action,
            'details' => $details,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $directory = dirname($this->file);
        if (!is_dir($directory)) {
            mkdir($directory, 0750, true);
        }
        file_put_contents($this->file, $line."\n", FILE_APPEND | LOCK_EX);
    }

    /**
     * Supprime les entrées antérieures à la date limite et retourne leur nombre.
     */
    public function purgeOlderThan(\DateTimeImmutable $limit, bool $dryRun = false): int
    {
        if (!is_file($this->file)) {
            return 0;
        }

        $lines = file($this->file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        $kept = array_values(array_filter($lines, static function (string $line) use ($limit): bool {
            $entry = json_decode($line, true);

            return is_array($entry) && isset($entry['date']) && new \DateTimeImmutable((string) $entry['date']) >= $limit;
        }));
        $removed = count($lines) - count($kept);
        if ($removed > 0 && !$dryRun) {
            file_put_contents($this->file, $kept === [] ? '' : implode("\n", $kept)."\n", LOCK_EX);
        }

        return $removed;
    }
}
