<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\Preorder;
use App\Repository\PreorderRepository;
use App\Form\AccountType;
use App\Repository\AttendanceRepository;
use App\Security\PasswordPolicy;
use App\Service\AdminActionLog;
use Symfony\Component\Form\FormError;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Espace « Mon compte » : droits d'accès, de rectification, de portabilité et d'effacement (RGPD).
 */
#[Route('/mon-compte')]
final class AccountController extends AbstractController
{
    #[Route('', name: 'account', methods: ['GET', 'POST'])]
    public function show(Request $request, EntityManagerInterface $entityManager, AttendanceRepository $attendances, PreorderRepository $preorders, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->currentUser();
        $form = $this->createForm(AccountType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Le prénom, le nom et l'email servent à se connecter : toute modification exige le mot de passe actuel.
            if ($hasher->isPasswordValid($user, (string) $form->get('currentPassword')->getData())) {
                $entityManager->flush();
                $this->addFlash('success', 'Tes informations ont été mises à jour.');

                return $this->redirectToRoute('account');
            }
            $form->get('currentPassword')->addError(new FormError('Mot de passe incorrect : tes informations n’ont pas été modifiées.'));
        }
        if ($form->isSubmitted()) {
            // Ne garde pas en mémoire des valeurs invalides pour la suite de la requête.
            $entityManager->refresh($user);
        }

        return $this->render('account/show.html.twig', [
            'form' => $form,
            'attendances' => $attendances->findBy(['user' => $user]),
            'preorders' => $preorders->findForUser($user),
        ]);
    }

    #[Route('/mot-de-passe', name: 'account_password', methods: ['POST'])]
    public function changePassword(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('change-password', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $newPassword = (string) $request->request->get('new_password');
        if (!$hasher->isPasswordValid($user, (string) $request->request->get('current_password'))) {
            $this->addFlash('error', 'Mot de passe actuel incorrect : ton mot de passe n’a pas été changé.');
        } elseif (!PasswordPolicy::isStrong($newPassword)) {
            $this->addFlash('error', PasswordPolicy::RULE);
        } elseif ($newPassword !== (string) $request->request->get('new_password_confirm')) {
            $this->addFlash('error', 'Les deux nouveaux mots de passe ne sont pas identiques.');
        } else {
            $user->setPassword($hasher->hashPassword($user, $newPassword));
            $entityManager->flush();
            $this->addFlash('success', 'Ton mot de passe a été changé.');
        }

        return $this->redirectToRoute('account', ['_fragment' => 'mot-de-passe']);
    }

    #[Route('/precommandes/{id}/modifier', name: 'account_preorder_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function editPreorder(Preorder $preorder, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->currentUser();
        if ($preorder->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('edit-preorder-'.$preorder->getId(), (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $this->applyPreorderValues($preorder, $request);
            if ($this->preorderValuesAreValid($preorder)) {
                $entityManager->flush();
                $this->addFlash('success', 'Ta précommande a été modifiée.');
                return $this->redirectToRoute('site_shop');
            }
            $this->addFlash('error', 'Vérifie les tailles, le numéro et la quantité demandés.');
        }
        return $this->render('account/preorder_form.html.twig', ['preorder' => $preorder, 'product' => $preorder->getProduct()]);
    }

    #[Route('/precommandes/{id}/supprimer', name: 'account_preorder_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function deletePreorder(Preorder $preorder, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->currentUser();
        if ($preorder->getUser()?->getId() !== $user->getId()) {
            throw $this->createNotFoundException();
        }
        if (!$this->isCsrfTokenValid('delete-preorder-'.$preorder->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $entityManager->remove($preorder);
        $entityManager->flush();
        $this->addFlash('success', 'Ta précommande a été supprimée.');
        return $this->redirectToRoute('site_shop');
    }

    #[Route('/export', name: 'account_export', methods: ['GET'])]
    public function export(AttendanceRepository $attendances): Response
    {
        $user = $this->currentUser();
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($pdfOptions);
        $pdf->loadHtml($this->renderView('account/data_pdf.html.twig', [
            'user' => $user,
            'attendances' => $attendances->findBy(['user' => $user]),
            'exportedAt' => new \DateTimeImmutable(),
        ]), 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        $response = new Response($pdf->output(), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'mes-donnees-velociraptouch.pdf',
        ));

        return $response;
    }

    #[Route('/supprimer', name: 'account_delete', methods: ['POST'])]
    public function delete(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher, Security $security, AdminActionLog $actionLog): Response
    {
        $user = $this->currentUser();
        if (!$this->isCsrfTokenValid('delete-account', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        if (!$hasher->isPasswordValid($user, (string) $request->request->get('password'))) {
            $this->addFlash('error', 'Mot de passe incorrect : ton compte n’a pas été supprimé.');

            return $this->redirectToRoute('account', ['_fragment' => 'supprimer']);
        }
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $admins = array_filter(
                $entityManager->getRepository(User::class)->findAll(),
                static fn (User $other): bool => in_array('ROLE_ADMIN', $other->getRoles(), true),
            );
            if (count($admins) <= 1) {
                $this->addFlash('error', 'Tu es le dernier administrateur : nomme d’abord un autre administrateur avant de supprimer ton compte.');

                return $this->redirectToRoute('account', ['_fragment' => 'supprimer']);
            }
        }

        // Les présences sont supprimées avec le compte (suppression en cascade).
        $actionLog->record('compte supprimé par son titulaire', ['compte' => $user->getEmail()]);
        $security->logout(false);
        $entityManager->remove($user);
        $entityManager->flush();
        $request->getSession()->invalidate();
        $request->getSession()->getFlashBag()->add('account', 'Ton compte et toutes tes données ont été supprimés.');

        return $this->redirectToRoute('site_home');
    }

    private function currentUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }

    private function applyPreorderValues(Preorder $preorder, Request $request): void
    {
        $number = filter_var($request->request->get('jersey_number'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 99]]);
        $preorder->setQuantity((int) $request->request->get('quantity'))
            ->setJerseySize((string) $request->request->get('jersey_size'))
            ->setJerseyNumber($number === false ? null : $number)
            ->setShortSize((string) $request->request->get('short_size'))
            ->setNotes((string) $request->request->get('notes'));
    }

    private function preorderValuesAreValid(Preorder $preorder): bool
    {
        $product = $preorder->getProduct();
        return $preorder->getQuantity() >= 1 && $preorder->getQuantity() <= 20
            && (!$product->isRequiresJerseySize() || in_array($preorder->getJerseySize(), ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true))
            && (!$product->isRequiresJerseySize() || $preorder->getJerseyNumber() !== null)
            && (!$product->isRequiresShortSize() || in_array($preorder->getShortSize(), ['XS', 'S', 'M', 'L', 'XL', 'XXL'], true))
            && mb_strlen($preorder->getNotes()) <= 1000;
    }
}
