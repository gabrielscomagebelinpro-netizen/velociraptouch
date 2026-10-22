<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

final class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $firstName = trim((string) $request->request->get('_first_name'));
        $lastName = trim((string) $request->request->get('_last_name'));
        $identifier = json_encode([$firstName, $lastName], JSON_THROW_ON_ERROR);

        return new Passport(
            new UserBadge($identifier, function (string $identifier): User {
                [$firstName, $lastName] = json_decode($identifier, true, 2, JSON_THROW_ON_ERROR);

                $users = $this->entityManager->createQueryBuilder()
                    ->select('user')
                    ->from(User::class, 'user')
                    ->where('LOWER(user.firstName) = LOWER(:firstName)')
                    ->andWhere('LOWER(user.lastName) = LOWER(:lastName)')
                    ->setParameter('firstName', $firstName)
                    ->setParameter('lastName', $lastName)
                    ->getQuery()
                    ->getResult();

                if (count($users) !== 1) {
                    throw new \Symfony\Component\Security\Core\Exception\UserNotFoundException();
                }

                return $users[0];
            }),
            new PasswordCredentials((string) $request->request->get('_password')),
            [
                new CsrfTokenBadge('authenticate', (string) $request->request->get('_csrf_token')),
            ],
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName,
    ): ?Response {
        return new RedirectResponse($this->urlGenerator->generate('site_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('admin_login');
    }
}
