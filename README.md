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
php -d upload_max_filesize=30M -d post_max_size=128M -d max_file_uploads=100 -S 127.0.0.1:8000 -t public public/router.php
```

Ouvrir ensuite <http://127.0.0.1:8000>.

Le fichier `public/router.php` est important : il permet de servir correctement les fichiers CSS, JavaScript et images tout en transmettant les autres requêtes à Symfony.

Les options `-d` sont nécessaires pour l'import des photos : le serveur intégré de PHP ignore `public/.user.ini`, et la limite par défaut (2 Mo par fichier) refuserait la plupart des photos de téléphone.

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

Le formulaire de contact est disponible sur l'accueil et la boutique. Il envoie les messages à `velociraptouch@gmail.com`. Pour un utilisateur connecté, le nom, le prénom et l'email sont préremplis ; son adresse est utilisée comme expéditeur et comme `Reply-To`. Avec Gmail SMTP, Google peut toutefois refuser ou réécrire une adresse `From` différente du compte SMTP authentifié.

### Créer un mot de passe d'application Gmail

Le mot de passe d'application est différent du mot de passe habituel du compte Gmail. Il autorise uniquement une application comme Symfony à utiliser SMTP.

1. se connecter au compte `velociraptouch@gmail.com` ;
2. ouvrir <https://myaccount.google.com/security> ;
3. activer la validation en deux étapes ;
4. rechercher **Mots de passe des applications** ;
5. créer une application nommée `Velociraptouch` ;
6. copier le code de 16 caractères fourni par Google ;
7. supprimer les espaces du code.

### Renseigner `.env.local`

Créer ou modifier `/home/gabriel/Bureau/Velociraptouch/.env.local` :

```dotenv
MAILER_DSN="smtp://velociraptouch@gmail.com:REMPLACEZ_PAR_LE_MOT_DE_PASSE_APPLICATION@smtp.gmail.com:587?encryption=tls&auth_mode=login"
CONTACT_RECIPIENT="velociraptouch@gmail.com"
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
php -d upload_max_filesize=30M -d post_max_size=128M -d max_file_uploads=100 -S 127.0.0.1:8000 -t public public/router.php
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

## 12. Mettre le site en ligne

Le projet est prêt pour un hébergement PHP classique (Apache, PHP 8.3 ou plus, MySQL 8) : `public/.htaccess` envoie les requêtes vers Symfony et force le HTTPS, et `public/.user.ini` relève les limites d'upload pour les photos. L'exemple ci-dessous suit un hébergeur mutualisé avec cPanel et accès SSH (o2switch, Infomaniak, PlanetHoster…), le cas le plus courant.

Ne pas utiliser le serveur PHP intégré (`php -S`) en production.

### 12.1. Préparer l'hébergement (une seule fois)

Dans le panneau de l'hébergeur :

1. **Nom de domaine** : rattacher le domaine (ex. `velociraptouch.fr`) à l'hébergement.
2. **Version de PHP** : choisir PHP 8.3 ou plus (« Sélecteur de version PHP »), avec les extensions `pdo_mysql`, `intl`, `zip`, `gd`, `mbstring`, `fileinfo`, `iconv`, `ctype`.
3. **Base MySQL** : créer une base et un utilisateur, lui donner tous les droits sur la base. Noter le nom de la base, l'utilisateur, le mot de passe et l'hôte (souvent `localhost`).
4. **Certificat HTTPS** : activer le certificat gratuit Let's Encrypt (« SSL/TLS Status » ou « AutoSSL »).
5. **Accès SSH** : l'activer (sur o2switch, autoriser son adresse IP dans « Autorisation SSH »).

### 12.2. Envoyer le code

En SSH sur l'hébergement, récupérer le dépôt GitHub dans un dossier hors de `public_html` :

```bash
cd ~
git clone https://github.com/gabrielscomagebelinpro-netizen/velociraptouch.git velociraptouch
cd velociraptouch
```

Si le dépôt est privé, utiliser un « Personal access token » GitHub comme mot de passe, ou une clé de déploiement SSH.

Puis, dans cPanel (« Domaines »), faire pointer la **racine du document** du domaine vers `velociraptouch/public`. Seul ce dossier doit être accessible depuis le web : le code, `.env.local` et les photos (`var/uploads`) restent ainsi hors d'atteinte.

### 12.3. Configurer `.env.local` sur le serveur

Créer le fichier `~/velociraptouch/.env.local` (avec l'éditeur de fichiers de cPanel ou `nano .env.local`) :

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET=une-valeur-aleatoire-de-32-caracteres-ou-plus
DATABASE_URL="mysql://UTILISATEUR:MOT_DE_PASSE@localhost:3306/NOM_DE_LA_BASE?serverVersion=8.0&charset=utf8mb4"
MAILER_DSN="smtp://velociraptouch@gmail.com:MOT_DE_PASSE_APPLICATION@smtp.gmail.com:587?encryption=tls&auth_mode=login"
CONTACT_RECIPIENT=velociraptouch@gmail.com
TRUSTED_PROXIES=127.0.0.1,REMOTE_ADDR
DEFAULT_URI=https://velociraptouch.fr
SHOP_ENABLED=0
```

- `APP_SECRET` : générer une valeur avec `php -r "echo bin2hex(random_bytes(16));"`.
- `serverVersion` : mettre la version MySQL indiquée par l'hébergeur (ex. `8.0` ou `10.11.2-MariaDB` pour MariaDB).
- `TRUSTED_PROXIES` : indispensable si l'hébergeur place un proxy HTTPS devant le site ; sans lui, la connexion et les formulaires échouent avec « Invalid CSRF token ».
- `APP_ENV=prod` est obligatoire : en `dev`, les erreurs détaillées et la barre de debug exposeraient les mots de passe.

### 12.4. Installer et initialiser

```bash
cd ~/velociraptouch
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
```

Si `composer` n'est pas disponible, l'installer dans le projet : `curl -sS https://getcomposer.org/installer | php`, puis utiliser `php composer.phar` à la place de `composer`.

Créer le premier administrateur : s'inscrire sur `https://votre-domaine/inscription` (le prénom et le nom servent à se connecter), puis passer ce compte administrateur :

```bash
php bin/console app:user:promote-admin email-du-compte@exemple.fr
```

### 12.5. Tâche planifiée

Dans cPanel (« Tâches Cron »), ajouter une tâche quotidienne (ex. tous les jours à 3 h) :

```bash
cd ~/velociraptouch && php bin/console app:attendances:cleanup >/dev/null 2>&1
```

### 12.6. Mettre à jour le site plus tard

Après un `git push` depuis le poste de développement :

```bash
cd ~/velociraptouch
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console cache:clear
```

### 12.7. Sauvegardes

À sauvegarder régulièrement (la plupart des hébergeurs le font aussi automatiquement) :

- la base MySQL ;
- le dossier `var/uploads/events` : il contient les photos originales des événements, qui ne sont pas dans Git ;
- le fichier `.env.local`.

### 12.8. Vérifications après la mise en ligne

- `https://votre-domaine/` s'affiche, et `http://` redirige vers `https://` ;
- la connexion admin fonctionne ;
- l'import d'une photo de téléphone (plusieurs Mo) fonctionne, ainsi que son téléchargement ;
- une adresse inexistante affiche une page d'erreur sobre, sans détails techniques ;
- un message envoyé depuis le formulaire de contact arrive sur `velociraptouch@gmail.com`.
