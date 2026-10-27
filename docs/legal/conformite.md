# Conformité légale du site — ce qui est fait, ce qui reste à faire

Document interne. Dernière mise à jour : 27 octobre 2026.

## Ce que le site fait déjà

### Mentions légales (LCEN, art. 6)
- Page `/mentions-legales` : éditeur, siège, RNA, directeur de la publication, contact, hébergeur, propriété intellectuelle, droit applicable.
- Lien dans le pied de page de toutes les pages.

### RGPD et loi Informatique et Libertés
- Page `/confidentialite` : responsable, finalités, bases légales, données, destinataires, transferts hors UE, durées de conservation, sécurité, droits, réclamation CNIL, mineurs, cookies.
- Information au moment de la collecte sous les formulaires d’inscription et de contact (art. 13).
- Espace « Mon compte » : consulter et corriger ses données, les télécharger (PDF), supprimer son compte (art. 15 à 20).
- Durées de conservation appliquées automatiquement par `php bin/console app:rgpd:purge` (à lancer chaque jour, voir le README).
- Mots de passe : 12 caractères minimum avec 4 types de caractères (recommandation CNIL 2022), stockés hachés.
- Registre des traitements : `docs/legal/registre-des-traitements.md` (art. 30).

### Cookies (art. 82 de la loi Informatique et Libertés)
- Un seul cookie, la session, strictement nécessaire : pas de bandeau de consentement à afficher.
- Aucune ressource tierce chargée à l’ouverture des pages : polices hébergées sur le site, Bootstrap (inutilisé) retiré, carte Google Maps chargée seulement après un clic de consentement.
- Aucun outil de mesure d’audience. **Si un jour vous en ajoutez un** (Google Analytics, pixel Meta…), il faudra un bandeau de consentement conforme (refuser aussi simple qu’accepter). Matomo configuré en mode exempté par la CNIL n’en a pas besoin.

## À faire par l’association (le site ne peut pas le faire à votre place)

1. **Compléter les informations légales** dans `config/packages/twig.yaml` (bloc `legal`) : adresse du siège, numéro RNA, président·e, hébergeur. Tant qu’elles manquent, elles sont surlignées en jaune sur les pages légales : **ne pas mettre le site en ligne avant**.
2. **Planifier la purge quotidienne** sur l’hébergeur (tâche cron, voir README) et la tâche de nettoyage des présences.
3. **Supprimer chaque année** de la boîte Gmail les messages de contact de plus d’un an, et dans Google Forms les autorisations de droit à l’image périmées.
4. **Photos** : ne publier (réseaux sociaux, affiches) que les photos dont toutes les personnes reconnaissables ont signé l’autorisation ; supprimer sur demande.
5. **PDF de présence** : ne pas les conserver après l’événement, ne pas les diffuser hors du bureau.
6. **Sécurité des accès** : comptes admin réservés aux membres du bureau ; retirer le rôle admin quand quelqu’un quitte le bureau (onglet « Gestion des utilisateurs »).
7. **Violation de données** : notifier la CNIL sous 72 h si nécessaire (procédure dans le registre).
8. **Sous-traitants** : vérifier que les conditions de l’hébergeur choisi contiennent bien les clauses de l’article 28 du RGPD (c’est le cas chez les hébergeurs français et européens courants).
9. **Révoquer** le mot de passe d’application Gmail de l’ancien compte, visible dans l’historique Git, s’il est toujours actif.

## Aspects fiscaux

Le site, dans son état actuel (boutique masquée, aucune vente ni don en ligne), **n’a pas d’obligation fiscale à afficher**. Points de vigilance pour l’association :

- **Boutique** : avant de passer `SHOP_ENABLED=1`, il faudra ajouter des conditions générales de vente (prix TTC, frais de livraison, droit de rétractation de 14 jours, médiateur de la consommation, garanties légales) et la mention TVA adaptée à la situation de l’association. Les ventes sont une activité lucrative : à suivre en comptabilité.
- **Franchise des impôts commerciaux** : une association à but non lucratif reste exonérée d’impôts commerciaux tant que ses recettes lucratives (ventes, prestations de parrainage avec contrepartie publicitaire…) restent sous le seuil de l’article 206-1 bis du CGI, revalorisé chaque année : vérifier le montant en vigueur sur impots.gouv.fr.
- **Partenaires** : un partenariat avec contrepartie publicitaire (logo affiché sur le site) est du parrainage, pas du mécénat ; il compte dans les recettes lucratives et ne donne pas droit à réduction d’impôt pour le partenaire.
- **Dons** : ne délivrer de reçus fiscaux (Cerfa 11580) que si l’association est d’intérêt général au sens des articles 200 et 238 bis du CGI ; dans ce cas, déclarer chaque année le montant des dons ayant donné lieu à reçu (article 222 bis du CGI).

En cas de doute sur la situation fiscale, demander un rescrit fiscal à l’administration ou l’avis d’un expert-comptable.
