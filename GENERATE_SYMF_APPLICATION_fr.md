# Pour créer une nouvelle application Symfony

Générer l'image génératrice:

    docker build -t symfgen .

`cd` dans le répertoire parent qui va héberger l'application

Initialisation du projet en se basant sur le squelette symfony:

    docker run --rm -it -v "$PATH A REMPLACER PAR LE PATH COURANT":/app --workdir /app symfgen composer create-project symfony/website-skeleton $NOM_DU_PROKET_A_REMPLACER --ignore-platform-reqs

## Mise en place du containeur docker

Copier le dossier [docker](./docker) dans le dossier du projet et suivre le README!
LEs champs importants à modifier son le nom du containeur, son port ainsi que le nom de l'image dans le cas où des modifications sont faites au Dockerfile (sinon on peut le laisser au générique: cdvo_iso_prod et réutiliser la même image d'un projet à l'autre).

## Mise en place du serveur Web sur le containeur

Le containeur gère tout seul apache qui sert l'application, il reste à mettre un fichier .htaccess comme celui en [exemple](files/.htaccess) dans le dossier public de l'application.

Il reste ensuite à mettre en place le fichier .env en se basant sur le fichier .env générer lors de la création du projet selon le squelette.

    cp .env .env.local

Attention à bien modifier et ne mettre des `secret` uniquement dans le fichier .env.local qui n'est pas commité. Le fichiers .env contient lui les informations non sensibles et qui sont amenées à être commitée.

Mettre en place les liens avec la base de donnée, le SMTP dans le fichier .env.local pour le développement et sur le serveur de production dans le fichier .env.prod.local.

Par défaut, Symfony vient loadé en dernier lieu le fichier .env.{Environnement courant}.local.

## Mise en place des variables Twig

Se diriger vers le fichier config/packages/twig.yaml et ajouter les lignes suivantes:

```
    globals:
        app_details:
            code: '%app_name%'
            version: '%app_version%'
```

Il reste donc à configurer les valeurs de ces paramètres dans le fichier `config/services.yaml` file adding at the beginning:

```
parameters:
    app_name: _Name of the app_
    app_version: _Version of the app_
```

## Mise en place de Encore

Ce module doit être installé en utilisant Symfony Flex grâce à la ligne de commande suivante:

    composer require symfony/webpack-encore-bundle

Cela va créer le dossier assets où se trouveront le code Css et JS. Un fichier webpack.config.js est également créer à la racine du projet ( [se réferer à la documentation](https://symfony.com/doc/current/frontend/encore/installation.html)).

Afin de copier les images/logos du dossier assets vers le dossier public et pouvoir ainsi les utiliser dans le templates, ajouter les lignes suivantes

```
.copyFiles({
    from: './assets/images',
    to: 'images/[path][name].[ext]',
})
```

et s'assurer que l'instruction `enableSassLoader` n'est pas commentée.

Copier la feuille de style de base [exemple](./files/assets/css/app.scss) dans le dossier `{ProjectRootDir}/assets/css`, et changer l'instruction dans le fichier `{ProjectRootDir}/assets/js/app.js` pour loader le fichier app.scss en lieu et place de app.css.

Copier les images de bases (logo, favicon) depuis [./files/assets/images](./files/assets/images) vers `{ProjectRootDir}/assets/images`.

Copier également le [dossier des fonts](./files/assets/fonts) vers `{ProjectRootDir}/assets/fonts`.

Pour s'intégrer avec le reste du parc, les librairies js suivantes peuvent aider:

    npm install --save @fortawesome/fontawesome-free jquery popper.js bootstrap sass-loader@^7.0.1 node-sass

Ne reste plus qu'à lancer `npm install` pour télécharger toutes les dépendences et `npm run dev/prod` pour compiler notre scss, js and vuejs etc dans le dossier public.

## Configure the User Provider

Create your user class as needed and register the user provider adding to the provider section in `config/packages/security.yaml`:

```
app_user_provider:
    entity:
        class: App\Entity\User
        property: username
```

## Authentification login/pwd

Se diriger vers `config/packages/security.yaml` et ajouter au firewall main:

```
    anonymous: ~
    guard:
        authenticators:
            - App\Security\LoginFormAuthenticator
    logout:
        path: app_logout
```
Il reste à créer l'authentificator mentionné dans le dossier src/Security/ en se basant sur [l'exemple](./files/LoginFormAuthenticator.php).

Ajuster la section `access_control` dans le fichier de sécurité:

    access_control:
        - { path: ^/login, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: /mot-de-passe-oublie, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/reinitialiser-mot-de-passe/, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }

## Authentification déléguée au SSO

Se diriger vers `config/packages/security.yaml` et ajouter au firewall main:

```
    anonymous: ~
    guard:
        authenticators:
            - App\Security\SSOAuthenticator
    logout:
        path: app_logout
```

Reste à créer l'Authenticator mentionné dans src/Security/ en se basant sur [l'exemple](./files/SSOAuthenticator.php).

Ajuster la section `access_control` dans le fichier de sécurité:

    access_control:
        - { path: ^/acces-non-autorise, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }

## Usurpation des utilisateurs

Pour aider la phase de test ainsi que le débug en offrant un 'Support Technique', il peut être interessant de mettre en place la fonctionalité d'impersonation pour naviguer sur l'application en tant qu'un utilisateur spécifique avec ses roles/permissions.

Pour mettre ca en place, il suffit de suivre la doc [ici](https://symfony.com/doc/current/security/impersonating_user.html):

- ajouter au firewall main:

        switch_user: { role: CAN_SWITCH_USER }

* Implementer un Voter en se basant sur [cet exemple](./files/SwitchToUserVoter.php)

* S'assurer que le UserRepository implémente UserLoaderInterface si la propriété Username n'est pas persisté en DB (si c'est un champ en lecture composé du Nom et Prénom par exemple). La propriété `key` doit Être supprimé de la definition du UserProvider dans le fichier security.yaml pour faire comprendre a symfony d'utiliser le UserRepository comme QueryLoader.

        public function loadUserByUsername($username)
        {
            return $this->createQueryBuilder('u')
                ->where('CONCAT(u.firstname, \' \', u.lastname) = :username')
                ->setParameter('username', $username)
                ->getQuery()
                ->getOneOrNullResult();
        }

## Implémentation de Test jouant avec la base de Donnée ?

### hautelook/AliceBundle

Installer la librairie:

    composer require --dev hautelook/alice-bundle

Créer les fixtures dans le dossier du même nom `fixtures`.

Les tests utilisant les fixtures doivent utiliser le trait `Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait` pour faire charger les éléments en base avant de lancer les tests.

### Test du JS ?

La librairie symfony/Panther permet les tests e2e (end to end), elle s'installe avec la commande suivante:

    composer require --dev symfony/panther

Suivre ensuite les instructions de la [documentation](https://packagist.org/packages/symfony/panther)

### Besoin de formatter des models avant de les renvoyer au front par ex ?

Fratal est une librairie proposant d'implémenter une couche de présentation et de transformation des données complexes (nos entités).
LA documentation se trouve [ic](https://fractal.thephpleague.com/). Mais dans les grandes lignes :

    composer require league/fractal
