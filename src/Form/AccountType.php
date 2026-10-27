<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Modification de ses propres informations (droit de rectification).
 */
final class AccountType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // empty_data : un champ vide devient '' (et non null) pour que la validation affiche l'erreur.
            ->add('firstName', TextType::class, ['label' => 'Prénom', 'empty_data' => ''])
            ->add('lastName', TextType::class, ['label' => 'Nom', 'empty_data' => ''])
            ->add('email', EmailType::class, ['label' => 'Email', 'empty_data' => ''])
            ->add('currentPassword', PasswordType::class, [
                'label' => 'Mot de passe actuel (pour confirmer)',
                'mapped' => false,
                'empty_data' => '',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => User::class]);
    }
}
