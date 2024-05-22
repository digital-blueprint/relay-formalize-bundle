<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Rest\FormProcessor;
use Symfony\Component\HttpFoundation\Response;

class FormProcessorTest extends RestTestCase
{
    private DataProcessorTester $formProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $formProcessor = new FormProcessor($this->formalizeService, $this->authorizationService);
        $this->formProcessorTester = DataProcessorTester::create($formProcessor, Form::class, ['FormalizeForm:input']);
    }

    public function testAddForm()
    {
        $form = new Form();
        $form->setName('Test Form');

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, null,
            AuthorizationService::CREATE_FORMS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->formProcessorTester->addItem($form);

        $formPersistence = $this->getForm($form->getIdentifier());
        $this->assertEquals($form->getIdentifier(), $formPersistence->getIdentifier());
    }

    public function testAddFormWithoutPermission()
    {
        $form = new Form();
        $form->setName('Test Form');

        try {
            $this->formProcessorTester->addItem($form);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateForm()
    {
        $form = $this->addForm(self::TEST_FORM_NAME);

        $this->assertEquals(self::TEST_FORM_NAME, $this->getForm($form->getIdentifier())->getName());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $formUpdated = $this->getForm($form->getIdentifier());
        $formUpdated->setName('Test Form Updated');

        $newForm = $this->formProcessorTester->updateItem($form->getIdentifier(), $formUpdated, $form);

        $this->assertEquals('Test Form Updated', $this->getForm($newForm->getIdentifier())->getName());
    }

    public function testUpdateFormWithoutPermissions()
    {
        $form = $this->addForm();
        $formUpdated = $this->getForm($form->getIdentifier());
        $formUpdated->setName('Test Form Updated');

        try {
            $this->formProcessorTester->updateItem($form->getIdentifier(), $formUpdated, $form);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testUpdateFormWithWrongPermissions()
    {
        $form = $this->addForm();
        $formUpdated = $this->getForm($form->getIdentifier());
        $formUpdated->setName('Test Form Updated');

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->formProcessorTester->updateItem($form->getIdentifier(), $formUpdated, $form);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveForm()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::DELETE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->formProcessorTester->removeItem($form->getIdentifier(), $form);

        $this->assertNull($this->getForm($form->getIdentifier()));
    }

    public function testRemoveFormWithoutPermissions()
    {
        $form = $this->addForm();

        try {
            $this->formProcessorTester->removeItem($form->getIdentifier(), $form);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testRemoveFormWithWrongPermissions()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->formProcessorTester->removeItem($form->getIdentifier(), $form);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
