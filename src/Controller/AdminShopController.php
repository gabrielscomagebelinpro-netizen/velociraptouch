<?php

namespace App\Controller;

use App\Entity\Preorder;
use App\Entity\Product;
use App\Repository\PreorderRepository;
use App\Repository\ProductRepository;
use App\Service\AdminActionLog;
use App\Service\PublicImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

#[Route('/admin/boutique')]
final class AdminShopController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductRepository $products,
        private readonly PreorderRepository $preorders,
        private readonly PublicImageStorage $images,
        private readonly AdminActionLog $actionLog,
    ) {}

    #[Route('', name: 'admin_shop', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/shop.html.twig', [
            'products' => $this->products->findAllOrdered(),
            'preorders' => $this->preorders->findRecent(),
        ]);
    }

    #[Route('/precommandes.pdf', name: 'admin_shop_preorders_pdf', methods: ['GET'])]
    public function preordersPdf(): Response
    {
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->renderView('admin/shop_preorders_pdf.html.twig', [
            'preorders' => $this->preorders->findRecent(),
            'exportedAt' => new \DateTimeImmutable(),
        ]), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();
        $response = new Response($pdf->output(), Response::HTTP_OK, ['Content-Type' => 'application/pdf']);
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'precommandes-boutique-'.(new \DateTimeImmutable())->format('Y-m-d').'.pdf',
        ));
        return $response;
    }

    #[Route('/produits/nouveau', name: 'admin_shop_product_new', methods: ['GET', 'POST'])]
    #[Route('/produits/{id}/modifier', name: 'admin_shop_product_edit', methods: ['GET', 'POST'])]
    public function product(Request $request, ?Product $product = null): Response
    {
        $product ??= new Product();
        $oldImage = $product->getImagePath();
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('shop-product', (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $name = trim((string) $request->request->get('name'));
            $description = trim((string) $request->request->get('description'));
            $price = str_replace(',', '.', trim((string) $request->request->get('price')));
            $priceCents = is_numeric($price) ? (int) round((float) $price * 100) : -1;
            if ($name === '' || mb_strlen($name) > 120 || $description === '' || mb_strlen($description) > 2000 || $priceCents < 0 || $priceCents > 1000000) {
                $this->addFlash('error', 'Vérifie le nom, la description et le prix du produit.');
            } else {
                $product->setName($name)->setDescription($description)->setPriceCents($priceCents)
                    ->setImageAlt((string) $request->request->get('image_alt'))
                    ->setRequiresJerseySize($request->request->getBoolean('requires_jersey_size'))
                    ->setRequiresShortSize($request->request->getBoolean('requires_short_size'))
                    ->setActive($request->request->getBoolean('active'));
                $file = $request->files->get('image');
                if ($file instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    try {
                        $stored = $this->images->store($file, 'products');
                        $product->setImagePath($stored['path']);
                    } catch (\InvalidArgumentException $exception) {
                        $this->addFlash('error', $exception->getMessage());
                        return $this->render('admin/shop_product_form.html.twig', ['product' => $product]);
                    }
                }
                if ($product->getId() === null) {
                    $product->setPosition(count($this->products->findAllOrdered()) + 1);
                    $this->entityManager->persist($product);
                }
                $this->entityManager->flush();
                if ($oldImage !== $product->getImagePath()) {
                    $this->images->remove($oldImage);
                }
                $this->actionLog->record($product->getId() ? 'produit boutique modifié' : 'produit boutique ajouté', ['id' => $product->getId(), 'nom' => $product->getName()]);
                $this->addFlash('success', 'Produit enregistré.');
                return $this->redirectToRoute('admin_shop');
            }
        }
        return $this->render('admin/shop_product_form.html.twig', ['product' => $product]);
    }

    #[Route('/produits/{id}/supprimer', name: 'admin_shop_product_delete', methods: ['POST'])]
    public function deleteProduct(Product $product, Request $request): Response
    {
        if (!$this->isCsrfTokenValid('delete-shop-product-'.$product->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $this->entityManager->remove($product);
        $this->entityManager->flush();
        $this->images->remove($product->getImagePath());
        $this->addFlash('success', 'Produit supprimé.');
        return $this->redirectToRoute('admin_shop');
    }

}
