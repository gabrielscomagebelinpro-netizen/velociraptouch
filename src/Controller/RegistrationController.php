<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
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
    ): Response {
        $user = new User();
        $form = $this->createForm(RegistrationType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = (string) $form->get('plainPassword')->getData();
            // Recommandation CNIL (délibération n° 2022-100) : 12 caractères mêlant 4 types de caractères.
            if (mb_strlen($plainPassword) < 12
                || !preg_match('/\p{Lu}/u', $plainPassword)
                || !preg_match('/\p{Ll}/u', $plainPassword)
                || !preg_match('/\d/', $plainPassword)
                || !preg_match('/[^\p{L}\d]/u', $plainPassword)) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 12 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.');
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
