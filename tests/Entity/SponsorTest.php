<?php

namespace App\Tests\Entity;

use App\Entity\Sponsor;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SponsorTest extends TestCase
{
    #[DataProvider('instagramLinks')]
    public function testInstagramHandleComesFromTheLink(string $url, string $expected): void
    {
        $sponsor = new Sponsor();
        $sponsor->setInstagramUrl($url);

        self::assertSame($expected, $sponsor->getInstagramHandle());
    }

    /** @return iterable<string, array{string, string}> */
    public static function instagramLinks(): iterable
    {
        yield 'lien simple' => ['https://www.instagram.com/novasrugbymontpellier/', '@novasrugbymontpellier'];
        yield 'avec paramètres' => ['https://www.instagram.com/lafumerie_mtp/?hl=fr', '@lafumerie_mtp'];
        yield 'sans barre finale' => ['https://instagram.com/sarda_avocat', '@sarda_avocat'];
        yield 'lien vers une publication' => ['https://www.instagram.com/novasrugbymontpellier/p/DeHHj52ituD/', '@novasrugbymontpellier'];
        yield 'sans compte' => ['https://www.instagram.com/', 'Instagram'];
    }

    public function testEmptyLinksAreStoredAsNull(): void
    {
        $sponsor = new Sponsor();
        $sponsor->setInstagramUrl('   ');
        $sponsor->setWebsiteUrl('');

        self::assertNull($sponsor->getInstagramUrl());
        self::assertNull($sponsor->getInstagramHandle());
        self::assertNull($sponsor->getWebsiteUrl());
        self::assertNull($sponsor->getWebsiteLabel());
    }

    public function testWebsiteLabelIsTheDomainWithoutWww(): void
    {
        $sponsor = new Sponsor();
        $sponsor->setWebsiteUrl('https://www.avocat-sarda.fr/contact');

        self::assertSame('avocat-sarda.fr', $sponsor->getWebsiteLabel());
    }

    public function testEachLineOfTheDescriptionIsAParagraph(): void
    {
        $sponsor = new Sponsor();
        $sponsor->setDescription("Première ligne.\r\n\r\n  Deuxième ligne.  \nTroisième ligne.\n");

        self::assertSame(['Première ligne.', 'Deuxième ligne.', 'Troisième ligne.'], $sponsor->getParagraphs());
    }

    public function testAltTextFallsBackToTheName(): void
    {
        $sponsor = new Sponsor();
        $sponsor->setName('Les Novas');

        self::assertSame('', $sponsor->getImageAlt(), 'Le champ du formulaire reste vide.');
        self::assertSame('Logo Les Novas', $sponsor->getAltText());

        $sponsor->setImageAlt('Logo des Novas Rugby');
        self::assertSame('Logo des Novas Rugby', $sponsor->getAltText());
    }
}
