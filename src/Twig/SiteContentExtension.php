<?php

namespace App\Twig;

use App\Service\SiteLinks;
use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

/**
 * Expose aux gabarits les liens modifiables depuis l'admin : licence_sheet_url, image_rights_url,
 * rugby5_rules_url et image_rights_qr.
 */
final class SiteContentExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly SiteLinks $links)
    {
    }

    public function getGlobals(): array
    {
        return $this->links->all();
    }
}
