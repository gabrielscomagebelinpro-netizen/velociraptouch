<?php

namespace App\Form;

use App\Entity\Sponsor;
use App\Service\PublicImageStorage;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

final class SponsorType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nom', 'empty_data' => ''])
            ->add('tier', ChoiceType::class, [
                'label' => 'Emplacement sur la page',
                'choices' => array_flip(Sponsor::TIERS),
            ])
            ->add('label', TextType::class, [
                'label' => 'Type de partenariat',
                'empty_data' => '',
                'help' => 'Petit texte au-dessus du nom, par exemple « Partenaire convivialité ».',
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'empty_data' => '',
                'attr' => ['rows' => 4],
                'help' => 'Un retour à la ligne crée un nouveau paragraphe.',
            ])
            ->add('instagramUrl', UrlType::class, [
                'label' => 'Lien Instagram (facultatif)',
                'required' => false,
                'default_protocol' => null,
                'attr' => ['placeholder' => 'https://www.instagram.com/compte/'],
            ])
            ->add('websiteUrl', UrlType::class, [
                'label' => 'Site internet (facultatif)',
                'required' => false,
                'default_protocol' => null,
                'attr' => ['placeholder' => 'https://exemple.fr/'],
            ])
            ->add('image', FileType::class, [
                'label' => $options['require_image'] ? 'Logo ou photo' : 'Remplacer l’image (facultatif)',
                'mapped' => false,
                'required' => $options['require_image'],
                'help' => 'JPEG, PNG ou WebP, 5 Mo maximum. Une image carrée rend le mieux.',
                'attr' => ['accept' => 'image/jpeg,image/png,image/webp'],
                'constraints' => array_filter([
                    $options['require_image'] ? new Assert\NotNull(message: 'Ajoute le logo ou une photo du partenaire.') : null,
                    new Assert\Image(
                        maxSize: PublicImageStorage::MAX_SIZE,
                        mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                        mimeTypesMessage: 'L’image doit être au format JPEG, PNG ou WebP.',
                        maxSizeMessage: 'L’image dépasse la limite de 5 Mo.',
                    ),
                ]),
            ])
            ->add('imageAlt', TextType::class, [
                'label' => 'Description de l’image (facultatif)',
                'required' => false,
                'empty_data' => '',
                'help' => 'Lue par les lecteurs d’écran. Vide : « Logo » suivi du nom du partenaire.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Sponsor::class, 'require_image' => false]);
        $resolver->setAllowedTypes('require_image', 'bool');
    }
}
