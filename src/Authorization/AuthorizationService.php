<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Authorization;

use Dbp\Relay\CoreBundle\Authorization\AbstractAuthorizationService;
use Dbp\Relay\CoreBundle\User\UserAttributeException;
use Dbp\Relay\FormalizeBundle\Entity\Form;

class AuthorizationService extends AbstractAuthorizationService
{
    private const NAMESPACE_NAME = 'DbpRelayFormalize';

    private const FORM_COLLECTION_RESOURCE = 'forms';

    private const ADD_ACTION = 'add';
    private const WRITE_ACTION = 'write';
    private const READ_ACTION = 'read';
    private const ADD_SUBMISSIONS_TO_FORM_ACTION = 'add_submissions';
    private const WRITE_SUBMISSIONS_OF_FORM_ACTION = 'write_submissions';
    private const READ_SUBMISSIONS_OF_FORM_ACTION = 'read_submissions';
    private const OWN_ACTION = 'own';

    public function canCurrentUserAddForms(): bool
    {
        return $this->isUserAttributeTruthy(self::toRequiredUserAttributeName(
            null, self::ADD_ACTION));
    }

    public function canCurrentUserWriteForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorized($form, self::WRITE_ACTION);
    }

    public function canCurrentUserReadForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorized($form, self::READ_ACTION);
    }

    public function canCurrentUserAddSubmissionsToForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorized($form, self::ADD_SUBMISSIONS_TO_FORM_ACTION);
    }

    public function canCurrentUserWriteSubmissionsOfForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorized($form, self::WRITE_SUBMISSIONS_OF_FORM_ACTION);
    }

    public function canCurrentUserReadSubmissionsOfForm(Form $form): bool
    {
        return $this->isCurrentUserAuthorized($form, self::READ_SUBMISSIONS_OF_FORM_ACTION);
    }

    /**
     * @param Form[] $allForms the list of all forms
     *
     * @return Form[] the list of forms the current user may read
     */
    public function getFormsUserCanRead(array $allForms): array
    {
        // Stretch goal: Extend the current user attribute API to get user attributes for a user using filters
        // (here something like: 'get all attributes of current user where userAttributeName ENDSWITH self::READ_FORM_ACTION'
        $formsCanRead = [];
        foreach ($allForms as $form) {
            if ($this->isCurrentUserAuthorized($form, self::READ_ACTION)) {
                $formsCanRead[] = $form;
            }
        }

        return $formsCanRead;
    }

    private function isUserAttributeTruthy(string $userAttributeName): bool
    {
        return (bool) $this->getUserAttributeValue($userAttributeName);
    }

    /**
     * @return mixed|null
     */
    private function getUserAttributeValue(string $userAttributeName)
    {
        try {
            return $this->getUserAttribute($userAttributeName);
        } catch (UserAttributeException $exception) {
            if ($exception->getCode() === UserAttributeException::USER_ATTRIBUTE_UNDEFINED) {
                return false; // not found means user attribute is not truthy
            } else {
                throw new \RuntimeException($exception->getMessage());
            }
        }
    }

    /**
     * @param string|null $formIdentifier the form identifier or null for the form collection resource
     *
     * @return string the name of user attribute that must be truthy for the current user for them to be authorized to
     *                perform the given action on the given resource
     */
    private static function toRequiredUserAttributeName(?string $formIdentifier, string $action): string
    {
        return self::toResourceName($formIdentifier).'.'.$action;
    }

    /**
     * @param string|null $formIdentifier the form identifier or null for the form collection resource
     */
    private static function toResourceName(?string $formIdentifier): string
    {
        return self::NAMESPACE_NAME.'/'.self::FORM_COLLECTION_RESOURCE.($formIdentifier !== null ? '/'.$formIdentifier : '');
    }

    private function isCurrentUserAuthorized(Form $form, string $action): bool
    {
        switch ($action) {
            case self::READ_ACTION:
                $usersOption = $form->getReadForm();
                break;
            case self::WRITE_ACTION:
                $usersOption = $form->getWriteForm();
                break;
            case self::READ_SUBMISSIONS_OF_FORM_ACTION:
                $usersOption = $form->getReadSubmissions();
                break;
            case self::WRITE_SUBMISSIONS_OF_FORM_ACTION:
                $usersOption = $form->getWriteSubmissions();
                break;
            case self::ADD_SUBMISSIONS_TO_FORM_ACTION:
                $usersOption = $form->getAddSubmissions();
                break;
            default:
                throw new \UnexpectedValueException('unexpected form action: '.$action);
        }

        switch ($usersOption) {
            case Form::USERS_OPTION_NONE:
                return false;
            case Form::USERS_OPTION_AUTHENTICATED:
                return $this->isAuthenticated();
            case Form::USERS_OPTION_AUTHORIZED:
                return $this->isUserAttributeTruthy(
                    self::toRequiredUserAttributeName($form->getIdentifier(), self::OWN_ACTION))
                    || $this->isUserAttributeTruthy(
                        self::toRequiredUserAttributeName($form->getIdentifier(), $action));
            case Form::DEPRECATED_USERS_OPTIONS_CLIENT_SCOPE:
                return
                    ($action === self::READ_SUBMISSIONS_OF_FORM_ACTION && (
                        $this->getUserAttributeValue('SCOPE_FORMALIZE')
                        || $this->getUserAttributeValue('ROLE_FORMALIZE_TEST_USER'))
                    )
                    || ($action === self::ADD_SUBMISSIONS_TO_FORM_ACTION
                        && $this->getUserAttributeValue('SCOPE_FORMALIZE_POST'));
            default:
                throw new \UnexpectedValueException('unexpected users option: '.$usersOption);
        }
    }
}
