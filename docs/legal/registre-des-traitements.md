# Registre des activités de traitement — Les Vélociraptouch

Document interne exigé par l’article 30 du RGPD. Il doit être tenu à jour et pouvoir être présenté à la CNIL sur demande. Il n’est pas publié sur le site.

- **Responsable du traitement** : association VELOCIRAP’TOUCH (« Les Vélociraptouch »), loi 1901, déclarée à la préfecture de l’Hérault le 17/03/2024 — siège : 64 allée Victor Schoelcher, 34070 Montpellier — RNA W343032161 — SIRET 932 697 329 00013
- **Représentant** : [À COMPLÉTER : président ou présidente]
- **Contact RGPD** : velociraptouch@gmail.com
- **Délégué à la protection des données** : non désigné (non obligatoire pour l’association)
- **Dernière mise à jour** : 27 octobre 2026

---

## 1. Gestion des comptes membres

| Rubrique | Contenu |
|---|---|
| Finalité | Créer et gérer les comptes, permettre la connexion à l’espace membre et l’organisation des entraînements |
| Base légale | Exécution des mesures liées à l’adhésion (art. 6.1.b) |
| Personnes concernées | Membres et personnes souhaitant rejoindre l’association |
| Données | Prénom, nom, email, mot de passe haché, rôle, date de dernière connexion |
| Destinataires | Administrateurs du site (membres du bureau), hébergeur |
| Transferts hors UE | Aucun |
| Durée de conservation | Jusqu’à la suppression du compte ; suppression automatique après 3 ans sans connexion (`app:rgpd:purge`) |
| Sécurité | HTTPS, mots de passe hachés (algorithme « auto » de Symfony), mot de passe de 12 caractères minimum (4 types), accès admin restreint, protection CSRF |

## 2. Inscriptions aux événements (présences)

| Rubrique | Contenu |
|---|---|
| Finalité | Prévoir l’organisation des entraînements et tournois, éditer la liste des participants (PDF) |
| Base légale | Exécution des mesures liées à l’adhésion (art. 6.1.b) |
| Données | Lien entre un membre et un événement, date d’inscription |
| Destinataires | Administrateurs du site |
| Durée de conservation | Suppression automatique une fois l’événement passé ; les PDF téléchargés doivent être détruits après l’événement |

## 3. Formulaire de contact

| Rubrique | Contenu |
|---|---|
| Finalité | Répondre aux demandes adressées à l’association |
| Base légale | Intérêt légitime (art. 6.1.f) |
| Personnes concernées | Toute personne qui écrit via le site |
| Données | Nom, email, message |
| Destinataires | Bureau de l’association ; Google (Gmail) pour l’acheminement des emails |
| Transferts hors UE | États-Unis (Google LLC) — décision d’adéquation UE–États-Unis du 10/07/2023 (Data Privacy Framework) |
| Durée de conservation | 1 an : suppression automatique sur le serveur (`app:rgpd:purge`) ; **supprimer aussi manuellement les emails correspondants de la boîte Gmail après 1 an** |

## 4. Photos des événements

| Rubrique | Contenu |
|---|---|
| Finalité | Conserver des souvenirs et communiquer sur les activités de l’association |
| Base légale | Consentement de la personne photographiée (art. 6.1.a), recueilli via le formulaire de droit à l’image |
| Données | Image des personnes |
| Destinataires | Administrateurs du site (photos non publiques sur le site) |
| Durée de conservation | 3 ans après l’événement (suppression automatique), ou immédiatement sur demande / retrait du consentement |
| Règle à respecter | Ne publier une photo (réseaux sociaux, site) que si toutes les personnes reconnaissables ont donné leur autorisation |

## 5. Autorisations de droit à l’image (Google Forms)

| Rubrique | Contenu |
|---|---|
| Finalité | Recueillir et prouver le consentement à l’utilisation de l’image |
| Base légale | Obligation de démontrer le consentement (art. 7) |
| Données | Identité et choix d’autorisation saisis dans le formulaire |
| Destinataires | Bureau ; Google (Forms / Drive) |
| Transferts hors UE | États-Unis (Google LLC) — Data Privacy Framework |
| Durée de conservation | Pendant la validité de l’autorisation, puis 3 ans après la dernière participation de la personne (à faire manuellement dans Google Forms) |

## 6. Sécurité et fonctionnement du site

| Rubrique | Contenu |
|---|---|
| Finalité | Maintenir la session, protéger les formulaires, assurer la sécurité |
| Base légale | Intérêt légitime (art. 6.1.f) ; obligations légales de l’hébergeur (LCEN) |
| Données | Cookie de session, adresse IP, journaux techniques |
| Durée de conservation | Cookie : durée de la session ; journaux : 1 an maximum (hébergeur) |

---

## Procédures

- **Demande d’exercice de droits** (accès, rectification, effacement…) : répondre sous 1 mois. Les membres peuvent agir seuls depuis « Mon compte ». Pour une demande par email, vérifier l’identité en cas de doute.
- **Violation de données** (piratage, fuite, perte) : notifier la CNIL sous 72 h si un risque existe pour les personnes (notifications.cnil.fr), informer les personnes si le risque est élevé, et consigner l’incident ci-dessous.

## Journal des violations de données

| Date | Nature de l’incident | Données et personnes concernées | Mesures prises | Notification CNIL |
|---|---|---|---|---|
| — | — | — | — | — |
