<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;
use Doctrine\ORM\Exception\NotSupported;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\HttpFoundation\Response;

class FormalizeServiceTest extends AbstractTestCase
{
    public function testAddForm()
    {
        $testName = 'Test Name';

        $form = new Form();
        $form->setName($testName);
        $form = $this->formalizeService->addForm($form);

        $this->assertSame($testName, $form->getName());
        $this->assertNotEmpty($form->getIdentifier());
        $this->assertNotEmpty($form->getDateCreated());

        $formPersistence = $this->testEntityManager->getForm($form->getIdentifier());
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
    }

    /**
     * @throws \JsonException
     */
    public function testAddFormNameMissingError()
    {
        try {
            $form = new Form();
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'name\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
        }
    }

    public function testAddFormWithDataFeedSchema()
    {
        $testDataFeedSchema = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["givenName", "familyName"]
        }';

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        $form = $this->formalizeService->addForm($form);

        $this->assertSame($testDataFeedSchema, $form->getDataFeedSchema());

        $formPersistence = $this->testEntityManager->getForm($form->getIdentifier());
        $this->assertSame($form->getDataFeedSchema(), $formPersistence->getDataFeedSchema());
    }

    /**
     * @throws \JsonException
     */
    public function testAddFormDataFeedErrorInvalidJson()
    {
        // invalid JSON
        $testDataFeedSchema = '{
            "type" = "object"
        }';

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        try {
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('\'dataFeedSchema\' is not a valid JSON schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-invalid-data-feed-schema', $apiError->getErrorId());
        }
    }

    /**
     * @throws \JsonException
     */
    public function testAddFormDataFeedErrorInvalidJsonSchema()
    {
        // invalid JSON schema
        $testDataFeedSchema = '{
            "type": "foo"
        }';

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        try {
            $this->formalizeService->addForm($form);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('\'dataFeedSchema\' is not a valid JSON schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-invalid-data-feed-schema', $apiError->getErrorId());
        }
    }

    public function testUpdateForm()
    {
        $form = $this->testEntityManager->addForm('Test Name');
        $formId = $form->getIdentifier();
        $dataCreated = $form->getDateCreated();

        $form->setName('Updated Name');
        $this->formalizeService->updateForm($form);

        $formPersistence = $this->testEntityManager->getForm($form->getIdentifier());
        $this->assertSame($formId, $formPersistence->getIdentifier());
        $this->assertSame('Updated Name', $formPersistence->getName());
        $this->assertSame($dataCreated, $formPersistence->getDateCreated());
    }

    public function testGetForm()
    {
        $form = $this->testEntityManager->addForm('Test Name');
        $this->assertSame('Test Name', $form->getName());

        $formPersistence = $this->formalizeService->getForm($form->getIdentifier());
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
    }

    public function testGetFormNotFoundError()
    {
        try {
            $this->formalizeService->getForm('notFound');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('Form could not be found', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
            $this->assertEquals('formalize:form-with-id-not-found', $apiError->getErrorId());
        }
    }

    public function testGetForms()
    {
        $form1 = $this->testEntityManager->addForm();
        $form2 = $this->testEntityManager->addForm();

        $forms = $this->formalizeService->getForms(1, 3);
        $this->assertCount(2, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
        $this->assertEquals($form2->getIdentifier(), $forms[1]->getIdentifier());

        $forms = $this->formalizeService->getForms(1, 1);
        $this->assertCount(1, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());

        $forms = $this->formalizeService->getForms(2, 1);
        $this->assertCount(1, $forms);
        $this->assertEquals($form2->getIdentifier(), $forms[0]->getIdentifier());

        $forms = $this->formalizeService->getForms(2, 2);
        $this->assertCount(0, $forms);
    }

    /**
     * @throws \JsonException
     */
    public function testRemoveForm()
    {
        $form = $this->testEntityManager->addForm();
        $submission = $this->testEntityManager->addSubmission($form);

        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier()));
        $this->assertNotNull($this->testEntityManager->getSubmission($submission->getIdentifier()));

        $this->formalizeService->removeForm($form);

        // form and submission must be removed
        $this->assertNull($this->testEntityManager->getForm($form->getIdentifier()));
        $this->assertNull($this->testEntityManager->getSubmission($submission->getIdentifier()));
    }

    public function testAddSubmission()
    {
        $form = $this->testEntityManager->addForm();

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar"}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());

        $formPersistence = $submissionPersistence->getForm();
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionFormMissingError()
    {
        try {
            $submission = new Submission();
            $submission->setDataFeedElement('{}');
            $this->formalizeService->addSubmission($submission);
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'form\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
            $this->assertEquals('form', $apiError->getErrorDetails()[0]);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws NotSupported
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionDataFeedElementMissingError()
    {
        try {
            $submission = new Submission();
            $submission->setForm($this->testEntityManager->addForm());
            $this->formalizeService->addSubmission($submission);
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('field \'dataFeedElement\' is required', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:required-field-missing', $apiError->getErrorId());
            $this->assertEquals('dataFeedElement', $apiError->getErrorDetails()[0]);
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws NotSupported
     */
    public function testAddSubmissionToFormWithDataFeedSchema()
    {
        $testDataFeedSchema = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["givenName", "familyName"]
        }';

        $form = $this->testEntityManager->addForm('people', $testDataFeedSchema);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());

        $formPersistence = $submissionPersistence->getForm();
        $this->assertSame($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertSame($form->getName(), $formPersistence->getName());
        $this->assertSame($form->getDateCreated(), $formPersistence->getDateCreated());
    }

    /**
     * @throws \Exception
     */
    public function testAddSubmissionFormAvailable()
    {
        $testName = 'Test Name';

        $form = new Form();
        $form->setName($testName);
        $utcTimezone = new \DateTimeZone('UTC');
        $start = (new \DateTime('now', $utcTimezone))->sub(\DateInterval::createFromDateString('1 minutes'));
        $end = (new \DateTime('now', $utcTimezone))->add(\DateInterval::createFromDateString('1 minutes'));
        $form->setAvailabilityStarts($start);
        $form->setAvailabilityEnds($end);
        $this->formalizeService->addForm($form);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        $submission = $this->formalizeService->addSubmission($submission);

        $submissionPersistence = $this->testEntityManager->getSubmission($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
    }

    /**
     * @throws \Exception
     */
    public function testAddSubmissionFormCurrentlyNotAvailable()
    {
        // test availability has already ended
        $form = new Form();
        $form->setName('Test Name');
        $utcTimezone = new \DateTimeZone('UTC');

        // test availability has ended
        $start = (new \DateTime('now', $utcTimezone))->sub(\DateInterval::createFromDateString('2 minutes'));
        $end = (new \DateTime('now', $utcTimezone))->sub(\DateInterval::createFromDateString('1 minutes'));
        $form->setAvailabilityStarts($start);
        $form->setAvailabilityEnds($end);
        $this->formalizeService->addForm($form);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-form-currently-not-available', $apiError->getErrorId());
        }

        // test availability has not yet started
        $start = (new \DateTime('now', $utcTimezone))->add(\DateInterval::createFromDateString('1 minutes'));
        $end = (new \DateTime('now', $utcTimezone))->add(\DateInterval::createFromDateString('2 minutes'));
        $form->setAvailabilityStarts($start);
        $form->setAvailabilityEnds($end);
        $this->formalizeService->updateForm($form);

        $submission = new Submission();
        $submission->setDataFeedElement('{"givenName": "John", "familyName": "Doe"}');
        $submission->setForm($form);

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-form-currently-not-available', $apiError->getErrorId());
        }
    }

    public function testGetSubmission()
    {
        $submission = $this->testEntityManager->addSubmission(null, '{"foo": "bar"}');

        $submissionPersistence = $this->formalizeService->getSubmissionByIdentifier($submission->getIdentifier());
        $this->assertSame($submission->getIdentifier(), $submissionPersistence->getIdentifier());
        $this->assertSame($submission->getDataFeedElement(), $submissionPersistence->getDataFeedElement());
        $this->assertSame($submission->getDateCreated(), $submissionPersistence->getDateCreated());
    }

    /**
     * @throws \JsonException
     */
    public function testGetSubmissionsByFormId()
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');
        $form2 = $this->testEntityManager->addForm();
        $submission2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $submissions = $this->formalizeService->getSubmissionsByForm($form1->getIdentifier(), 1, 3);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissionsByForm($form2->getIdentifier(), 1, 3);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission2->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissionsByForm('foo', 1, 3);
        $this->assertCount(0, $submissions);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveSubmission()
    {
        $form = $this->testEntityManager->addForm();
        $submission = $this->testEntityManager->addSubmission($form);

        $this->assertNotNull($this->testEntityManager->getSubmission($submission->getIdentifier()));

        $this->formalizeService->removeSubmission($submission);

        $this->assertNull($this->testEntityManager->getSubmission($submission->getIdentifier()));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testRemoveFormSubmissions()
    {
        $form = $this->testEntityManager->addForm();
        $this->testEntityManager->addSubmission($form);
        $this->testEntityManager->addSubmission($form);

        $this->assertCount(2, $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));

        $this->formalizeService->removeAllFormSubmissions($form->getIdentifier());

        $this->assertCount(0, $this->testEntityManager->getEntityManager()->getRepository(Submission::class)
            ->findBy(['form' => $form->getIdentifier()]));
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionInvalidJsonError()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('foo');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionInvalidDataFeedElementKeysError()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm($form);
        $this->formalizeService->addSubmission($sub);

        $sub = new Submission();
        $sub->setDataFeedElement('{"quux": "bar"}');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('doesn\'t match with the pevious submissions', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json-keys', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('doesn\'t match with the pevious submissions', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json-keys', $apiError->getErrorId());
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws NotSupported
     * @throws \JsonException
     */
    public function testAddSubmissionToFormWithDataFeedSchemaSchemaViolationError()
    {
        $testDataFeedSchema = '{
            "type": "object",
            "properties": {
                "givenName": {
                  "type": "string"
                },
                "familyName": {
                  "type": "string"
                }
            },
            "required": ["familyName"],
            "additionalProperties": false
        }';

        $form = $this->testEntityManager->addForm('people', $testDataFeedSchema);

        $submission = new Submission();
        $submission->setForm($form);
        $submission->setDataFeedElement('{"givenName": "John"}'); // required property 'familyName' missing

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the JSON schema defined in the form!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        $submission->setDataFeedElement('{"familyName": "Doe", "email": "john@doe.com"}'); // undefined property 'email'

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t comply with the JSON schema defined in the form!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_UNPROCESSABLE_ENTITY, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }
    }

    public function testFormAuthorization()
    {
        $form = new Form();
        $form->setName('Test Form');
        $form = $this->formalizeService->addForm($form);

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form));

        $this->formalizeService->removeForm($form);

        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form));
    }
}
