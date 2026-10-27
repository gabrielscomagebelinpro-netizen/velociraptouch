<?php

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * versioned_asset('/assets/css/site.css') → '/assets/css/site.css?v=<date de modification>'.
 * Le navigateur recharge ainsi le fichier dès qu'il change, au lieu de garder l'ancienne version en cache.
 */
final class AssetVersionExtension extends AbstractExtension
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/public')]
        private readonly string $publicDir,
    ) {
    }

    public function getFunctions(): array
    {
        return [new TwigFunction('versioned_asset', $this->versionedAsset(...))];
    }

    public function versionedAsset(string $path): string
    {
        $file = $this->publicDir.'/'.ltrim($path, '/');

        return is_file($file) ? $path.'?v='.filemtime($file) : $path;
    }
}
