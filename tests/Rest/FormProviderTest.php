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
        DataProviderTester::setUp($formProvider);

        $this->formProviderTester = new DataProviderTester($formProvider, Form::class, ['FormalizeForm:output']);
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

        $this->assertEquals($form->getIdentifier(), $this->formProviderTester->getItem($form->getIdentifier())->getIdentifier());
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

    public function testGetFormCollectionFull()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(2, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
        $this->assertEquals($form2->getIdentifier(), $forms[1]->getIdentifier());
    }

    public function testGetFormCollectionPartial()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(1, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
    }

    public function testGetFormCollectionEmpty()
    {
        $this->addForm();
        $this->addForm();

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(0, $forms);
    }
}
