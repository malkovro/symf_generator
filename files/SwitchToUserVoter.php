<?php

namespace App\Security\Voter;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

class SwitchToUserVoter extends Voter
{
    private $security;

    public function __construct(Security $security)
    {
        $this->security = $security;
    }

    protected function supports($attribute, $subject)
    {
        return in_array($attribute, ['CAN_SWITCH_USER'])
            && $subject instanceof UserInterface;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token)
    {
        $user = $token->getUser();
        // if the user is anonymous or if the subject is not a user, do not grant access
        if (!$user instanceof UserInterface || !$subject instanceof UserInterface) {
            return false;
        }

        // you can still check for ROLE_ALLOWED_TO_SWITCH
        if ($this->security->isGranted('ROLE_ALLOWED_TO_SWITCH')) {
            return true;
        }

        // check for any roles you want
        if ($this->security->isGranted('ROLE_TECH_SUPPORT')) {
            return true;
        }

        /*
         * or use some custom data from your User object
        if ($user->isAllowedToSwitch()) {
            return true;
        }
        */

        return false;
    }
}