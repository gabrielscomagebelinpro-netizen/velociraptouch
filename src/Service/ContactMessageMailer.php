<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
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
        $mail = (new Email())
            ->from($email)
            ->to($this->recipient)
            ->replyTo($email)
            ->subject(sprintf('Nouveau message de contact — %s', $name))
            ->text(sprintf("Nom : %s\n\nMessage :\n%s", $name, $message));

        $this->mailer->send($mail);
    }
}
