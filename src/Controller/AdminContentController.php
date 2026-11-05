<?php

namespace App\Controller;

use App\Entity\Sponsor;
use App\Form\SponsorType;
use App\Repository\SponsorRepository;
use App\Service\AdminActionLog;
use App\Service\PublicImageStorage;
use App\Service\SiteLinks;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Onglet « Liens et partenaires » : liens Google Sheets / Forms, QR code du droit à l'image et partenaires.
 */
#[Route('/admin/contenu')]
final class AdminContentController extends AbstractController
{
    /** En dessous de cette largeur, l'image est affichée à sa taille réelle pour ne pas être floue. */
    private const LOWRES_WIDTH = 230;

    public function __construct(
        private readonly SiteLinks $links,
        private readonly PublicImageStorage $images,
        private readonly AdminActionLog $actionLog,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'admin_content', methods: ['GET', 'POST'])]
    public function index(Request $request, SponsorRepository $sponsors): Response
    {
        $form = $this->linksForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $changed = [];
            foreach (array_keys(SiteLinks::LINKS) as $key) {
                $value = trim((string) $form->get($key)->getData());
                if ($value !== $this->links->get($key)) {
                    $this->links->set($key, $value);
                    $changed[] = $key;
                }
            }

            $qrCode = $form->get('qrCode')->getData();
            $oldQrCode = $this->links->get(SiteLinks::IMAGE_RIGHTS_QR);
            if ($qrCode instanceof UploadedFile) {
                try {
                    $stored = $this->images->store($qrCode, 'site');
                } catch (\InvalidArgumentException $exception) {
                    $this->addFlash('error', $exception->getMessage());

                    return $this->redirectToRoute('admin_content');
                }
                $this->links->set(SiteLinks::IMAGE_RIGHTS_QR, $stored['path']);
                $changed[] = SiteLinks::IMAGE_RIGHTS_QR;
            }

            $this->entityManager->flush();
            if ($qrCode instanceof UploadedFile) {
                $this->images->remove($oldQrCode);
            }

            if ($changed === []) {
                $this->addFlash('success', 'Aucun lien n’a changé.');
            } else {
                $this->actionLog->record('liens du site modifiés', ['reglages' => implode(', ', $changed)]);
                $this->addFlash('success', 'Liens enregistrés.');
                if (in_array('image_rights_url', $changed, true) && !$qrCode instanceof UploadedFile) {
                    $this->addFlash('error', 'Le lien du droit à l’image a changé : pense à remplacer aussi le QR code, sinon il mène toujours à l’ancien formulaire.');
                }
            }

            return $this->redirectToRoute('admin_content');
        }

        return $this->render('admin/content.html.twig', [
            'linksForm' => $form,
            'linkDefinitions' => SiteLinks::LINKS,
            'currentLinks' => $this->links->all(),
            'qrCodePath' => $this->links->get(SiteLinks::IMAGE_RIGHTS_QR),
            'sponsorsByTier' => $sponsors->findGroupedByTier(),
            'tiers' => Sponsor::TIERS,
        ]);
    }

    #[Route('/partenaires/nouveau', name: 'admin_sponsor_new', methods: ['GET', 'POST'])]
    public function newSponsor(Request $request, SponsorRepository $sponsors): Response
    {
        $sponsor = new Sponsor();
        $sponsor->setTier((string) $request->query->get('emplacement', 'tertiary'));
        if (!isset(Sponsor::TIERS[$sponsor->getTier()])) {
            $sponsor->setTier('tertiary');
        }
        $form = $this->createForm(SponsorType::class, $sponsor, ['require_image' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Position calculée avant l'envoi de l'image : une erreur ici ne laisse pas de fichier orphelin.
            $sponsor->setPosition($sponsors->nextPosition($sponsor->getTier()));
            if ($this->applyImage($form, $sponsor)) {
                $this->entityManager->persist($sponsor);
                $this->entityManager->flush();
                $this->actionLog->record('partenaire ajouté', ['id' => $sponsor->getId(), 'nom' => $sponsor->getName()]);
                $this->addFlash('success', sprintf('« %s » ajouté aux partenaires.', $sponsor->getName()));

                return $this->redirectToRoute('admin_content', ['_fragment' => 'partenaires']);
            }
        }

        return $this->render('admin/sponsor_form.html.twig', ['form' => $form, 'sponsor' => $sponsor]);
    }

    #[Route('/partenaires/{id}/modifier', name: 'admin_sponsor_edit', methods: ['GET', 'POST'])]
    public function editSponsor(Sponsor $sponsor, Request $request, SponsorRepository $sponsors): Response
    {
        $previousTier = $sponsor->getTier();
        $previousImage = $sponsor->getImagePath();
        $form = $this->createForm(SponsorType::class, $sponsor);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($sponsor->getTier() !== $previousTier) {
                // Changement d'emplacement : le partenaire passe en fin de liste.
                $sponsor->setPosition($sponsors->nextPosition($sponsor->getTier()));
            }
            if ($this->applyImage($form, $sponsor)) {
                $this->entityManager->flush();
                if ($sponsor->getImagePath() !== $previousImage) {
                    $this->images->remove($previousImage);
                }
                $this->actionLog->record('partenaire modifié', ['id' => $sponsor->getId(), 'nom' => $sponsor->getName()]);
                $this->addFlash('success', sprintf('« %s » modifié.', $sponsor->getName()));

                return $this->redirectToRoute('admin_content', ['_fragment' => 'partenaires']);
            }
        }

        if ($form->isSubmitted()) {
            // Formulaire refusé : on réaffiche l'image encore en place, pas un chemin modifié en mémoire.
            $sponsor->setImagePath($previousImage);
        }

        return $this->render('admin/sponsor_form.html.twig', ['form' => $form, 'sponsor' => $sponsor]);
    }

    #[Route('/partenaires/{id}/deplacer', name: 'admin_sponsor_move', methods: ['POST'])]
    public function moveSponsor(Sponsor $sponsor, Request $request, SponsorRepository $sponsors): Response
    {
        if (!$this->isCsrfTokenValid('move-sponsor-'.$sponsor->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $siblings = $sponsors->findInTier($sponsor->getTier());
        $index = array_search($sponsor, $siblings, true);
        $target = $index === false ? false : $index + ($request->request->get('direction') === 'up' ? -1 : 1);
        if ($index !== false && isset($siblings[$target])) {
            [$siblings[$index], $siblings[$target]] = [$siblings[$target], $siblings[$index]];
            foreach ($siblings as $position => $sibling) {
                $sibling->setPosition($position + 1);
            }
            $this->entityManager->flush();
            $this->actionLog->record('partenaire déplacé', ['id' => $sponsor->getId(), 'nom' => $sponsor->getName()]);
        }

        return $this->redirectToRoute('admin_content', ['_fragment' => 'partenaire-'.$sponsor->getId()]);
    }

    #[Route('/partenaires/{id}/supprimer', name: 'admin_sponsor_delete', methods: ['POST'])]
    public function deleteSponsor(Sponsor $sponsor, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-sponsor-'.$sponsor->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $details = ['id' => $sponsor->getId(), 'nom' => $sponsor->getName()];
        $image = $sponsor->getImagePath();
        $this->entityManager->remove($sponsor);
        $this->entityManager->flush();
        $this->images->remove($image);
        $this->actionLog->record('partenaire supprimé', $details);
        $this->addFlash('success', sprintf('« %s » retiré des partenaires.', $details['nom']));

        return $this->redirectToRoute('admin_content', ['_fragment' => 'partenaires']);
    }

    /** Enregistre l'image envoyée, s'il y en a une. Renvoie false (avec l'erreur sur le champ) si elle est refusée. */
    private function applyImage(FormInterface $form, Sponsor $sponsor): bool
    {
        $file = $form->get('image')->getData();
        if (!$file instanceof UploadedFile) {
            return true;
        }
        try {
            $stored = $this->images->store($file, 'sponsors');
        } catch (\InvalidArgumentException $exception) {
            $form->get('image')->addError(new \Symfony\Component\Form\FormError($exception->getMessage()));

            return false;
        }
        $sponsor->setImagePath($stored['path']);
        $sponsor->setImageLowres($stored['width'] < self::LOWRES_WIDTH);

        return true;
    }

    private function linksForm(): FormInterface
    {
        $builder = $this->createFormBuilder(array_intersect_key($this->links->all(), SiteLinks::LINKS));
        foreach (SiteLinks::LINKS as $key => [$label, $help]) {
            $builder->add($key, UrlType::class, [
                'label' => $label,
                'help' => $help,
                'required' => false,
                'default_protocol' => null,
                'empty_data' => '',
                'constraints' => [
                    new Assert\Url(message: 'Indique une adresse complète qui commence par https://', protocols: ['https', 'http'], requireTld: true),
                    new Assert\Length(max: 1000),
                ],
            ]);
        }
        $builder->add('qrCode', FileType::class, [
            'label' => 'Remplacer le QR code du droit à l’image (facultatif)',
            'required' => false,
            'help' => 'JPEG, PNG ou WebP, 5 Mo maximum. À changer en même temps que le lien du Google Forms.',
            'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
            'constraints' => [new Assert\Image(
                maxSize: PublicImageStorage::MAX_SIZE,
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                mimeTypesMessage: 'Le QR code doit être une image JPEG, PNG ou WebP.',
                maxSizeMessage: 'L’image dépasse la limite de 5 Mo.',
            )],
        ]);

        return $builder->getForm();
    }
}
