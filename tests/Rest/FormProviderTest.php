<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\CoreBundle\TestUtils\TestAuthorizationService;
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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::READ_ACTION) => true,
            ]);

        $this->assertEquals($form->getIdentifier(), $this->formProviderTester->getItem($form->getIdentifier())->getIdentifier());
    }

    public function testGetFormItemWithOwnPermission()
    {
        $form = $this->addForm();

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::OWN_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form->getIdentifier(), self::WRITE_ACTION) => true,
            ]);

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

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form1->getIdentifier(), self::OWN_ACTION) => true,
                self::getUserAttributeName($form2->getIdentifier(), self::READ_ACTION) => true,
            ]);

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(2, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
        $this->assertEquals($form2->getIdentifier(), $forms[1]->getIdentifier());
    }

    public function testGetFormCollectionPartial()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();

        TestAuthorizationService::setUp($this->authorizationService,
            TestAuthorizationService::TEST_USER_IDENTIFIER, [
                self::getUserAttributeName($form1->getIdentifier(), self::READ_ACTION) => true,
                self::getUserAttributeName($form2->getIdentifier(), self::WRITE_ACTION) => true,
            ]);

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
