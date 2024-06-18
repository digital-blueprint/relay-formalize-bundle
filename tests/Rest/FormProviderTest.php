<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Rest\FormProvider;
use Symfony\Component\HttpFoundation\Response;

class FormProviderTest extends RestTestCase
{
    private DataProviderTester $formProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $formProvider = new FormProvider($this->formalizeService, $this->authorizationService, self::createRequestStack());
        $this->formProviderTester = DataProviderTester::create($formProvider, Form::class, ['FormalizeForm:output']);
    }

    public function testGetFormItemWithReadPermission()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($form->getIdentifier(), $this->formProviderTester->getItem($form->getIdentifier())->getIdentifier());
    }

    public function testGetFormItemWithManagePermission()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $formPersistence = $this->formProviderTester->getItem($form->getIdentifier());
        assert($formPersistence instanceof Form);

        $this->assertEquals($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertEquals($form->getName(), $formPersistence->getName());
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $formPersistence->getGrantedActions());
    }

    public function testGetFormItemWithoutPermission()
    {
        $form = $this->addForm();

        try {
            $this->formProviderTester->getItem($form->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetFormItemWithWrongPermission()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->formProviderTester->getItem($form->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetFormCollection()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();
        $form3 = $this->addForm();
        $form4 = $this->addForm();
        $form5 = $this->addForm();

        // current has read/manage grants for forms 1, 3, and 4
        $authorizationResource = $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier());
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form4->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form5->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(3, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form1) {
            return $form->getIdentifier() === $form1->getIdentifier()
                && count($form->getGrantedActions()) === 2
                && in_array(AuthorizationService::READ_FORM_ACTION, $form->getGrantedActions(), true)
                && in_array(AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, $form->getGrantedActions(), true);
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier()
                && $form->getGrantedActions() === [AuthorizationService::READ_FORM_ACTION];
        }));
    }

    public function testGetFormCollectionPagination()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();
        $form3 = $this->addForm();
        $form4 = $this->addForm();
        $form5 = $this->addForm();

        // current has read/manage grants for forms 1, 3, and 4
        $authorizationResource = $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier());
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form4->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form5->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $formPage1 = $this->formProviderTester->getCollection([
            'page' => 1,
            'perPage' => 2,
        ]);
        $this->assertCount(2, $formPage1);
        $formPage2 = $this->formProviderTester->getCollection([
            'page' => 2,
            'perPage' => 2,
        ]);
        $this->assertCount(1, $formPage2);

        $forms = array_merge($formPage1, $formPage2);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form1) {
            return $form->getIdentifier() === $form1->getIdentifier()
                && count($form->getGrantedActions()) === 2
                && in_array(AuthorizationService::READ_FORM_ACTION, $form->getGrantedActions(), true)
                && in_array(AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, $form->getGrantedActions(), true);
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier()
                && $form->getGrantedActions() === [AuthorizationService::READ_FORM_ACTION];
        }));
    }

    public function testGetFormCollectionEmpty()
    {
        $this->addForm();
        $this->addForm();

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(0, $forms);
    }
}
