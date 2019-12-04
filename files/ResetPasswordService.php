<?php

namespace App\Service;


use App\Entity\User;
use App\Exception\ResetPasswordException;
use Doctrine\ORM\EntityManagerInterface;
use Swift_Mailer;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;
use Twig\Environment;

class ResetPasswordService
{

    /**
     * @var UserPasswordEncoderInterface
     */
    private $encoder;
    /**
     * @var Swift_Mailer
     */
    private $mailer;
    /**
     * @var TokenGeneratorInterface
     */
    private $tokenGenerator;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var UrlGeneratorInterface
     */
    private $router;

    /**
     * @var Environment
     */
    private $templating;

    public function __construct(
        EntityManagerInterface $entityManager,
        UserPasswordEncoderInterface $encoder,
        Swift_Mailer $mailer,
        UrlGeneratorInterface $router,
        TokenGeneratorInterface $tokenGenerator,
        Environment  $templating)
    {

        $this->encoder = $encoder;
        $this->mailer = $mailer;
        $this->tokenGenerator = $tokenGenerator;
        $this->entityManager = $entityManager;
        $this->router = $router;
        $this->templating = $templating;
    }

    /**
     * @param string $userEmail
     * @return mixed
     * @throws ResetPasswordException
     */
    public function sendResetEmail(string $userEmail)
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $userEmail, 'actif' => true]);
        if ($user === null) {
            throw ResetPasswordException::unknownUserByEmail();
        }
        $token = $this->tokenGenerator->generateToken();

        try{
            $user->setResetToken($token);
            $this->entityManager->flush();
        } catch (\Exception $e) {
            throw ResetPasswordException::couldNotSetResetToken($e);

        }

        $url = $this->router->generate('user_reset_password', array('token' => $token), UrlGeneratorInterface::ABSOLUTE_URL);

        $message = (new \Swift_Message('GDCWEB - Mot de passe oublié'))
            ->setFrom('no-reply@gdcweb.valdoise.fr')
            ->setTo($user->getEmail())
            ->setBody(
                $this->templating->render(
                // templates/emails/registration.html.twig
                    'security/emails/reset_password.html.twig',
                    ['url' => $url]
                ),
                'text/html'
            );

        return $this->mailer->send($message);
    }

    /**
     * @param $token
     * @param $email
     * @param $password
     * @return bool
     * @throws ResetPasswordException
     */
    public function resetPassword($token, $email, $password)
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email, 'actif' => true]);
        /* @var $user User */
        if ($user === null) {
            throw ResetPasswordException::unknownUserByEmail();
        } else if($user->getResetToken() !== $token ) {
            throw ResetPasswordException::unknownToken();
        }

        $user->setResetToken(null);
        $user->setPassword($this->encoder->encodePassword($user, $password));
        $this->entityManager->flush();

        return true;

    }
}