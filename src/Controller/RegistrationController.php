<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use App\Security\PasswordPolicy;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class RegistrationController extends AbstractController
{
    #[Route('/connexion', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('admin/login.html.twig', [
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'last_username' => $authenticationUtils->getLastUsername(),
        ]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        RateLimiterFactory $registrationLimiter,
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // Anti-robots : champ piège invisible, puis nombre de tentatives limité par adresse IP.
            if (trim((string) $request->request->get('website')) !== '') {
                return $this->redirectToRoute('admin_login');
            }
            if (!$registrationLimiter->create($request->getClientIp() ?? 'inconnu')->consume()->isAccepted()) {
                $this->addFlash('error', 'Trop de tentatives d’inscription depuis cette connexion. Réessaie dans une heure.');

                return $this->redirectToRoute('app_register');
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            if (!PasswordPolicy::isStrong($plainPassword)) {
                $this->addFlash('error', PasswordPolicy::RULE);
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $entityManager->persist($user);
                $entityManager->flush();
                $this->addFlash('success', 'Compte créé. Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('admin_login');
            }
        }

        return $this->render('security/register.html.twig', ['form' => $form]);
    }
}
