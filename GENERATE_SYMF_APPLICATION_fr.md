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

Copy the base stylesheet from ./files/assets/css/app.scss to `{ProjectRootDir}/assets/css`, and Change the require in the app.js to load app.scss instead of default app.css.

Copy the base image assets from the ./files/assets/images to `{ProjectRootDir}/assets/images`.

Copy the font folder into assets folder as well.

We'll need then a few js librairies to make life easier for us:

    npm install --save @fortawesome/fontawesome-free jquery popper.js bootstrap sass-loader@^7.0.1 node-sass

Now we can run `npm install` to download all the dependencies and `npm run dev/prod` to compile our scss, js and vuejs etc into the public folder.

## Configure the User Provider

Create your user class as needed and register the user provider adding to the provider section in `config/packages/security.yaml`:

```
app_user_provider:
    entity:
        class: App\Entity\User
        property: username
```

## Authentication login/pwd

Head to `config/packages/security.yaml` and add to the main firewall:

```
    anonymous: ~
    guard:
        authenticators:
            - App\Security\LoginFormAuthenticator
    logout:
        path: app_logout
```

You then need to add the said Authenticator in src/Security/ taking file ./files/LoginFormAuthenticator.php as example.

In the access_control section, adjust something like:

    access_control:
        - { path: ^/login, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: /mot-de-passe-oublie, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/reinitialiser-mot-de-passe/, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }

## Authentication delegated to the SSO

Head to `config/packages/security.yaml` and add to the main firewall:

```
    anonymous: ~
    guard:
        authenticators:
            - App\Security\SSOAuthenticator
    logout:
        path: app_logout
```

You then need to add the said Authenticator in src/Security/ taking file ./files/SSOAuthenticator.php as example.

In the access_control section, adjust something like:

    access_control:
        - { path: ^/acces-non-autorise, roles: IS_AUTHENTICATED_ANONYMOUSLY }
        - { path: ^/, roles: IS_AUTHENTICATED_FULLY }

## Impersonating users

For testing purposes and to offer 'Tech Support', it might be interesting to setup the impersonating feature offered by Symfony to browse the platform as a specific given user with his/her given roles and permissions.
To set this up, follow the documentation available [here](https://symfony.com/doc/current/security/impersonating_user.html):

- add the following statemenent to the main firewall:

        switch_user: { role: CAN_SWITCH_USER }

* Implement Voter taking [this one](./files/SwitchToUserVoter.php) as example

* Make sure the UserRepository implements UserLoaderInterface and implements loadUserByUsername if the property Username used to display the name of the user is not persisted in DB (it could be for instance a combination of firstname and lastname). The property key then needs to be removed from the User provider in the security.yaml to enforce the UserRepository to be used as query loader.

        public function loadUserByUsername($username)
        {
            return$this->createQueryBuilder('u')
                ->where('CONCAT(u.firstname, \' \', u.lastname) = :username')
                ->setParameter('username', $username)
                ->getQuery()
                ->getOneOrNullResult();
        }

## Need some Tests requiring some interaction with the DB

### hautelook/AliceBundle

Install the package launching:

    composer require --dev hautelook/alice-bundle

Create your fixture files in the fixtures directory.

Make your test use the trait `Hautelook\AliceBundle\PhpUnit\RefreshDatabaseTrait` to make them load your fixture in DB before actually launching the test.

###Test the JS ?

Use symfony/Panther to do e2e testing, first install it with (require the unzip command):

    composer req --dev symfony/panther

Follow then all instructions in the [documentation](https://packagist.org/packages/symfony/panther)

### Need to format model for presentation purpose ?

Fratal is a library providing presentation and transformation layer of complex data (our entities).
Documentation can be found [here](https://fractal.thephpleague.com/). But in the main lines, to install :

    composer require league/fractal
