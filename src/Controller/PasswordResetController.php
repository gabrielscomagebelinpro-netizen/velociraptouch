<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class PasswordResetController extends AbstractController
{
    private const VALIDITY_MINUTES = 60;
    private const SESSION_TOKEN_KEY = 'password_reset_token';

    public function __construct(
        private readonly string $sender,
        #[Autowire('%kernel.project_dir%/public/assets/images/brand/logo-velo-svg.png')]
        private readonly string $logoPath,
    ) {
    }

    #[Route('/mot-de-passe-oublie', name: 'password_forgot', methods: ['GET', 'POST'])]
    public function forgot(
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator,
        RateLimiterFactory $passwordResetLimiter,
        LoggerInterface $logger,
    ): Response {
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password-reset-request', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré. Recharge la page puis réessaie.');
                return $this->redirectToRoute('password_forgot');
            }

            // Champ piège invisible : un robot le remplit, un humain non. On répond comme si tout allait bien.
            if (trim((string) $request->request->get('website')) !== '') {
                $this->addFlash('reset', 'Si un compte correspond à cet email, un lien de réinitialisation vient d’être envoyé.');
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
                // Le jeton n'est envoyé que par mail ; en base, seule son empreinte est gardée.
                $token = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
                $user->setPasswordResetTokenHash(hash('sha256', $token));
                $user->setPasswordResetExpiresAt(new \DateTimeImmutable(sprintf('+%d minutes', self::VALIDITY_MINUTES)));
                $entityManager->flush();

                $resetUrl = $urlGenerator->generate('password_reset', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
                try {
                    $mailer->send($this->buildResetEmail($user, $resetUrl));
                } catch (TransportExceptionInterface $exception) {
                    // Même message à l'écran (on ne révèle pas si l'adresse existe), mais l'échec est tracé.
                    $logger->error('Envoi du mail de réinitialisation impossible.', ['exception' => $exception, 'user_id' => $user->getId()]);
                }
            }

            $this->addFlash('reset', 'Si un compte correspond à cet email, un lien de réinitialisation vient d’être envoyé. Pense à regarder dans tes spams.');
            return $this->redirectToRoute('password_forgot');
        }

        return $this->render('security/password_forgot.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'password_reset', defaults: ['token' => null], methods: ['GET', 'POST'])]
    public function reset(
        ?string $token,
        Request $request,
        UserRepository $users,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
    ): Response {
        // Le jeton reçu par mail est rangé en session puis retiré de l'adresse :
        // il n'apparaît ni dans l'historique du navigateur ni dans l'en-tête Referer envoyé à d'autres sites.
        if ($token !== null) {
            $request->getSession()->set(self::SESSION_TOKEN_KEY, $token);

            return $this->redirectToRoute('password_reset');
        }

        $token = (string) $request->getSession()->get(self::SESSION_TOKEN_KEY, '');
        $user = $token !== '' ? $users->findOneBy(['passwordResetTokenHash' => hash('sha256', $token)]) : null;

        if (!$user instanceof User
            || $user->getPasswordResetExpiresAt() === null
            || $user->getPasswordResetExpiresAt() <= new \DateTimeImmutable()
        ) {
            $request->getSession()->remove(self::SESSION_TOKEN_KEY);

            return $this->render('security/password_reset.html.twig', ['invalid' => true]);
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('password-reset', (string) $request->request->get('_token'))) {
                $this->addFlash('error', 'Le formulaire a expiré. Recharge la page puis réessaie.');
                return $this->redirectToRoute('password_reset');
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
                $request->getSession()->remove(self::SESSION_TOKEN_KEY);

                $this->addFlash('account', sprintf(
                    'Ton mot de passe a été changé. Connecte-toi avec ton prénom (%s), ton nom (%s) et ce nouveau mot de passe.',
                    $user->getFirstName(),
                    $user->getLastName(),
                ));
                return $this->redirectToRoute('app_login');
            }

            return $this->redirectToRoute('password_reset');
        }

        return $this->render('security/password_reset.html.twig', ['invalid' => false, 'user' => $user]);
    }

    private function buildResetEmail(User $user, string $resetUrl): Email
    {
        $context = ['user' => $user, 'reset_url' => $resetUrl, 'validity_minutes' => self::VALIDITY_MINUTES];

        return (new Email())
            ->from(new Address($this->sender, 'Vélociraptouch'))
            ->to(new Address($user->getEmail(), trim($user->getFirstName().' '.$user->getLastName())))
            ->subject('Ton lien pour changer de mot de passe — Vélociraptouch')
            ->text($this->renderView('emails/password_reset.txt.twig', $context))
            ->html($this->renderView('emails/password_reset.html.twig', $context))
            // Logo joint au mail (et non chargé depuis le site) : il s'affiche même hors ligne ou en local.
            ->embedFromPath($this->logoPath, 'logo', 'image/png');
    }
}
