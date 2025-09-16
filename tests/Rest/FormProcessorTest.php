<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Rest\FormProcessor;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

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
        $formName = 'Test Form';
        $availableTags = ['tag1', 'tag2', 'tag3'];
        $form->setName($formName);
        $form->setAvailableTags($availableTags);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, null,
            AuthorizationService::CREATE_FORMS_ACTION, self::CURRENT_USER_IDENTIFIER);

        $form = $this->formProcessorTester->addItem($form);
        $this->assertTrue(Uuid::isValid($form->getIdentifier()));
        $this->assertEquals($formName, $form->getName());
        $this->assertEquals($availableTags, $form->getAvailableTags());

        $formPersistence = $this->getForm($form->getIdentifier());
        $this->assertEquals($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertEquals($formName, $formPersistence->getName());
        $this->assertEquals($availableTags, $formPersistence->getAvailableTags());
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
        $formName = 'Test Form';
        $availableTags = ['tag1', 'tag2', 'tag3'];
        $form = $this->addForm($formName, availableTags: $availableTags);

        $this->assertEquals(self::TEST_FORM_NAME, $this->getForm($form->getIdentifier())->getName());

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $formUpdated = $this->getForm($form->getIdentifier());

        $formName = 'Test Form Updated';
        $availableTags = ['tag4', 'tag5'];
        $formUpdated->setName($formName);
        $formUpdated->setAvailableTags($availableTags);

        $formUpdated = $this->formProcessorTester->updateItem($form->getIdentifier(), $formUpdated, $form);
        $this->assertEquals($formName, $formUpdated->getName());
        $this->assertEquals($availableTags, $formUpdated->getAvailableTags());

        $formPersistence = $this->getForm($form->getIdentifier());
        $this->assertEquals($formName, $formPersistence->getName());
        $this->assertEquals($availableTags, $formPersistence->getAvailableTags());
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
