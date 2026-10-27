<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

final class ContactMessageMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $recipient,
    ) {
    }

    public function send(string $name, string $email, string $message): void
    {
        // L'expéditeur est toujours l'adresse de l'association (pas d'usurpation possible) ;
        // l'adresse du visiteur sert seulement de « Répondre à ».
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $name));
        $mail = (new Email())
            ->from(new Address($this->recipient, 'Site Vélociraptouch'))
            ->to($this->recipient)
            ->replyTo($email)
            ->subject(sprintf('Nouveau message de contact — %s', $name))
            ->text(sprintf("Nom : %s\n\nMessage :\n%s", $name, $message));

        $this->mailer->send($mail);
    }
}
