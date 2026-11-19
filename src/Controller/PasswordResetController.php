<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetController extends AbstractController
{
    public function __construct(private readonly string $sender)
    {
    }

    #[Route('/mot-de-passe-oublie', name: 'password_forgot', methods: ['GET', 'POST'])]
    public function forgot(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        RateLimiterFactory $passwordResetLimiter,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password-reset-request', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré. Recharge la page puis réessaie.');
                return $this->redirectToRoute('password_forgot');
            }

            $limiter = $passwordResetLimiter->create($request->getClientIp() ?? 'inconnu');
            if (!$limiter->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de demandes. Réessaie dans une heure.');
                return $this->redirectToRoute('password_forgot');
            }

            $email = mb_strtolower(trim((string) $request->request->get('email')));
            $user = $email !== '' ? $users->findOneBy(['email' => $email]) : null;

            if ($user instanceof User) {
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $user->setPasswordResetTokenHash(hash('sha256', $token));
                $user->setPasswordResetExpiresAt(new \DateTimeImmutable('+1 hour'));
                $entityManager->flush();

                $resetUrl = $urlGenerator->generate('password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                $mail = (new Email())
                    ->from(new Address($this->sender, 'Vélociraptouch'))
                    ->to($user->getEmail())
                    ->subject('Réinitialisation de votre mot de passe')
                    ->text("Bonjour {$user->getFirstName()},\n\nTu as demandé la réinitialisation de ton mot de passe. Ce lien est valable 1 heure :\n{$resetUrl}\n\nSi tu n'es pas à l'origine de cette demande, ignore cet email.\n");
                $mailer->send($mail);
            }

            $this->addFlash('reset', 'Si un compte correspond à cet email, un lien de réinitialisation vient d’être envoyé.');
            return $this->redirectToRoute('password_forgot');
        }

        return $this->render('security/password_forgot.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'password_reset', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        $tokenHash = hash('sha256', $token);
        $user = $users->findOneBy(['passwordResetTokenHash' => $tokenHash]);
        $now = new \DateTimeImmutable();

        if (!$user instanceof User
            || $user->getPasswordResetExpiresAt() === null
            || $user->getPasswordResetExpiresAt() <= $now
        ) {
            return $this->render('security/password_reset.html.twig', ['invalid' => true, 'token' => $token]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password-reset', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré. Recharge la page puis réessaie.');
                return $this->redirectToRoute('password_reset', ['token' => $token]);
            }

            $password = (string) $request->request->get('password');
            $confirmation = (string) $request->request->get('confirmation');
            if ($password !== $confirmation) {
                $this->addFlash('error', 'Les deux mots de passe ne correspondent pas.');
            } elseif (!PasswordPolicy::isStrong($password)) {
                $this->addFlash('error', PasswordPolicy::RULE);
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
                $user->setPasswordResetTokenHash(null);
                $user->setPasswordResetExpiresAt(null);
                $entityManager->flush();

                $this->addFlash('account', 'Votre mot de passe a été réinitialisé. Vous pouvez vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/password_reset.html.twig', ['invalid' => false, 'token' => $token]);
    }
}
