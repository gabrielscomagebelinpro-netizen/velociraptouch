# Les Vélociraptouch

Application Symfony du site de l'association : accueil, boutique, comptes utilisateurs, agenda, présences persistantes, administration et export PDF.

## 1. Pré-requis

Installer les outils suivants :

- PHP 8.3 ou supérieur ;
- Composer ;
- Docker et Docker Compose ;
- un navigateur web.

Vérifier l'installation :

```bash
php -v
composer --version
docker --version
docker compose version
```

Toutes les commandes ci-dessous doivent être exécutées depuis la racine du projet :

```bash
cd /home/gabriel/Bureau/Velociraptouch
```

## 2. Installer le projet

Installer les dépendances PHP :

```bash
composer install
```

Créer le fichier local de configuration uniquement s'il n'existe pas déjà :

```bash
cp .env .env.local
```

Le fichier `.env.local` est ignoré par Git. Il peut contenir les mots de passe locaux et les identifiants SMTP.

## 3. Démarrer MySQL avec Docker

Lancer le conteneur MySQL :

```bash
docker compose up -d database
```

La configuration locale est :

| Paramètre | Valeur |
|---|---|
| Hôte | `127.0.0.1` |
| Port | `3310` |
| Base | `velociraptouch` |
| Utilisateur | `velociraptouch` |
| Mot de passe | `velociraptouch` |

Attendre que MySQL soit prêt, puis appliquer les migrations :

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

Vérifier l'état du conteneur :

```bash
docker compose ps
```

Arrêter uniquement la base :

```bash
docker compose stop database
```

Supprimer le conteneur sans supprimer les données :

```bash
docker compose down
```

Le volume Docker `database_data` conserve les données. Ne pas utiliser `docker compose down -v` sauf si la base doit être entièrement réinitialisée.

## 4. Lancer le site

Lancer le serveur PHP intégré avec le routeur Symfony :

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

Ouvrir ensuite <http://127.0.0.1:8000>.

Le fichier `public/router.php` est important : il permet de servir correctement les fichiers CSS, JavaScript et images tout en transmettant les autres requêtes à Symfony.

Pour arrêter le serveur, revenir dans son terminal et appuyer sur `Ctrl+C`.

Commandes utiles avant un lancement :

```bash
php bin/console cache:clear
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
```

## 5. Créer un compte et un administrateur

Créer un compte utilisateur depuis le site :

1. ouvrir <http://127.0.0.1:8000/inscription> ;
2. renseigner le prénom, le nom, l'adresse email et un mot de passe d'au moins 8 caractères ;
3. ouvrir ensuite <http://127.0.0.1:8000/connexion>.

Créer ou promouvoir un administrateur en ligne de commande :

```bash
php bin/console app:user:promote-admin admin@example.com --password='UnMotDePasseSolide2026!'
```

La commande crée le compte s'il n'existe pas et lui attribue `ROLE_ADMIN`. Si le compte existe déjà, elle lui ajoute le rôle administrateur et peut mettre à jour son mot de passe avec `--password`.

Le panneau d'administration est disponible sur :

<http://127.0.0.1:8000/admin>

## 6. Gérer les événements et les présences

Depuis le panneau d'administration, un administrateur peut :

- créer un événement ;
- modifier un événement ;
- supprimer un événement ;
- télécharger la liste des présences au format PDF.

Un utilisateur connecté peut cliquer sur **Je serai là** pour enregistrer sa présence, puis sur **Annuler ma présence** pour la retirer. Chaque couple utilisateur/événement est unique afin d'éviter les doublons.

Les présences sont stockées dans la table `event_attendances`. Les événements passés sont nettoyés automatiquement lors de l'accès au site ou à l'administration. Pour effectuer ce nettoyage avec une tâche planifiée :

```bash
php bin/console app:attendances:cleanup
```

Exemple de cron quotidien à 3 h :

```cron
0 3 * * * cd /home/gabriel/Bureau/Velociraptouch && php bin/console app:attendances:cleanup --env=prod
```

## 7. Télécharger les présences en PDF

Dans le panneau d'administration, cliquer sur **PDF** à côté d'un événement.

Le fichier est téléchargé avec le format :

```text
presence-AAAA-MM-JJ-nom-de-levenement.pdf
```

Le document contient le titre, la date, le nombre de participants, le prénom et le nom, l'adresse email et la date d'inscription de chaque participant.

La route correspondante est protégée par `ROLE_ADMIN` :

```text
GET /admin/events/{id}/attendance.pdf
```

## 8. Configurer l'envoi des messages de contact

Le formulaire de contact est disponible sur l'accueil et la boutique. Il envoie les messages à `gabsg66@gmail.com`. Pour un utilisateur connecté, le nom, le prénom et l'email sont préremplis ; son adresse est utilisée comme expéditeur et comme `Reply-To`. Avec Gmail SMTP, Google peut toutefois refuser ou réécrire une adresse `From` différente du compte SMTP authentifié.

### Créer un mot de passe d'application Gmail

Le mot de passe d'application est différent du mot de passe habituel du compte Gmail. Il autorise uniquement une application comme Symfony à utiliser SMTP.

1. se connecter au compte `gabsg66@gmail.com` ;
2. ouvrir <https://myaccount.google.com/security> ;
3. activer la validation en deux étapes ;
4. rechercher **Mots de passe des applications** ;
5. créer une application nommée `Velociraptouch` ;
6. copier le code de 16 caractères fourni par Google ;
7. supprimer les espaces du code.

### Renseigner `.env.local`

Créer ou modifier `/home/gabriel/Bureau/Velociraptouch/.env.local` :

```dotenv
MAILER_DSN="smtp://gabsg66@gmail.com:MOT_DE_PASSE_APPLICATION@smtp.gmail.com:587?encryption=tls&auth_mode=login"
CONTACT_RECIPIENT="gabsg66@gmail.com"
```

Remplacer `MOT_DE_PASSE_APPLICATION` par le code Google. Ne jamais utiliser le mot de passe normal de Gmail, ne jamais le mettre dans `composer.json`, le code PHP ou Git, et ne jamais le publier dans une conversation.

Si le mot de passe contient un caractère spécial réservé dans une URL, l'encoder avant de l'insérer dans `MAILER_DSN`.

Après toute modification de `.env.local` :

```bash
php bin/console cache:clear
```

Redémarrer le serveur PHP, puis envoyer un message test depuis le formulaire. En cas d'échec, vérifier le message affiché et les logs :

```bash
tail -f var/log/dev.log
```

Le serveur SMTP Gmail doit être accessible sur `smtp.gmail.com:587`. Le compte Google doit autoriser la validation en deux étapes et le mot de passe d'application doit être correct.

## 9. Utiliser la base dans PhpStorm

Dans PhpStorm :

1. ouvrir **View > Tool Windows > Database** ;
2. cliquer sur **+ > Data Source > MySQL** ;
3. saisir `127.0.0.1` comme hôte ;
4. saisir `3310` comme port ;
5. saisir `velociraptouch` comme base ;
6. saisir `velociraptouch` comme utilisateur ;
7. saisir `velociraptouch` comme mot de passe ;
8. cliquer sur **Test Connection**, puis **Apply**.

Tables principales :

- `users` : comptes, prénoms, noms, emails, rôles et mots de passe hashés ;
- `events` : événements de l'agenda ;
- `event_attendances` : présences des utilisateurs ;
- `doctrine_migration_versions` : migrations appliquées.

## 10. Données de démonstration

Pour créer les comptes et présences de démonstration sur le prochain événement :

```bash
php bin/console app:demo:seed-attendances
```

Pour cibler un événement précis :

```bash
php bin/console app:demo:seed-attendances --event-id=1
```

Le mot de passe commun des comptes de démonstration est affiché par la commande. Ces comptes sont réservés au développement local et ne doivent pas être conservés en production.

## 11. Dépannage courant

### Le site affiche une erreur de connexion MySQL

Vérifier que le conteneur fonctionne :

```bash
docker compose ps
docker compose logs database
```

Vérifier que `DATABASE_URL` utilise bien le port `3310`, puis relancer :

```bash
php bin/console cache:clear
```

### Le CSS ou les images ne s'affichent pas

Utiliser le routeur intégré, et non `public/index.php` directement :

```bash
php -S 127.0.0.1:8000 -t public public/router.php
```

### Le formulaire de contact n'envoie pas de mail

Vérifier `MAILER_DSN` dans `.env.local`, le mot de passe d'application Google, la validation en deux étapes et les logs Symfony :

```bash
tail -f var/log/dev.log
```

### Une migration n'est pas appliquée

```bash
php bin/console doctrine:migrations:status
php bin/console doctrine:migrations:migrate --no-interaction
```

### Réinitialiser complètement la base locale

Cette opération supprime les données de développement :

```bash
docker compose down -v
docker compose up -d database
php bin/console doctrine:migrations:migrate --no-interaction
```

## 12. Préparer la production

Ne pas utiliser le serveur PHP intégré en production. Définir au minimum dans `.env.local` ou dans le gestionnaire de secrets :

```dotenv
APP_ENV=prod
APP_SECRET=une-valeur-aleatoire-longue
DATABASE_URL=...
MAILER_DSN=...
CONTACT_RECIPIENT=gabsg66@gmail.com
```

Installer les dépendances et vider le cache :

```bash
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --env=prod --no-interaction
php bin/console cache:clear --env=prod
```

Configurer ensuite un serveur web (Apache ou Nginx) avec `public/` comme document root, HTTPS, des sauvegardes de la base et une tâche cron pour le nettoyage des présences.
