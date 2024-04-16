<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProcessorTester;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Rest\FormProcessor;
use Symfony\Component\HttpFoundation\Response;

class FormProcessorTest extends RestTest
{
    private DataProcessorTester $formProcessorTester;

    protected function setUp(): void
    {
        parent::setUp();

        $formProcessor = new FormProcessor($this->formalizeService, $this->authorizationService);
        DataProcessorTester::setUp($formProcessor);

        $this->formProcessorTester = new DataProcessorTester($formProcessor, Form::class, ['FormalizeForm:input']);
    }

    public function testAddForm()
    {
        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName(null, self::ADD_ACTION) => true,
            ]);

        $form = new Form();
        $form->setName('Test Form');

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
        $form = $this->addForm('Test Form');

        $this->assertEquals('Test Form', $this->getForm($form->getIdentifier())->getName());

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_ACTION) => true,
            ]);

        try {
            $this->formProcessorTester->removeItem($form->getIdentifier(), $form);
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
