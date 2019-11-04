# Creating new Symfony Application

Build the Symfony App Generator image:

    docker build -t symfgen .

`cd` into the directory that will host the application directory

Init the project based on symfony skeleton launching:

    docker run --rm -it -v "$REPLACE_PATH_OF_THE_DIRECTORY":/app --workdir /app symfgen composer create-project symfony/website-skeleton $REPLACE_PROJECT_NAME --ignore-platform-reqs

## Setting up docker container

Copy the folder docker to the project repository and follow the README!
Important to change are the container Name, the container port and the Image Name if you are going to modify something inside the dockerfile(otherwise set it as generic: cdvo_iso_prod).

## Setting up server

The container take care of the apache server hosting the application, one step remains though: copy the file : `files/.htaccess` into the public folder.

Now it's time to setup the local .env file:

cp .env .env.local

And setup database connection, smtp and others there.

## Setting up Twig Global Variables

Head to config/packages/twig.yaml and add the following lines:

```
    globals:
        app_details:
            code: '%app_name%'
            version: '%app_version%'
```

Remains to configure the value of these parameters in the `config/services.yaml` file adding at the beginning:

```
parameters:
    app_name: _Name of the app_
    app_version: _Version of the app_
```

## Setting up Encore

Start by installing the bundle with Symfony flex:

    composer require symfony/webpack-encore-bundle

This will create the assets folder where we'll put our css and Js code and a webpack.config.js file at the root directory ( [as per the documentation](https://symfony.com/doc/current/frontend/encore/installation.html)).

After the setOutputPath instruction, add the following one:

```
.copyFiles({
    from: './assets/images',
    to: 'images/[path][name].[ext]',
})
```

and make sure the enableSassLoader instruction is uncommented.

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
