<?php

namespace App\Security;

/**
 * Règle des mots de passe : recommandation CNIL (délibération n° 2022-100), 12 caractères mêlant 4 types de caractères.
 */
final class PasswordPolicy
{
    public const RULE = 'Le mot de passe doit contenir au moins 12 caractères, dont une majuscule, une minuscule, un chiffre et un caractère spécial.';

    public static function isStrong(string $password): bool
    {
        return mb_strlen($password) >= 12
            && preg_match('/\p{Lu}/u', $password)
            && preg_match('/\p{Ll}/u', $password)
            && preg_match('/\d/', $password)
            && preg_match('/[^\p{L}\d]/u', $password);
    }
}
