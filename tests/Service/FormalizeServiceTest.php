<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
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
        $testDataFeedSchema = '{"type":"object","properties":{"givenName":{"type":"string"},"familyName":{"type":"string"}},"required":["givenName","familyName"]}';

        $form = new Form();
        $form->setName('Test Name');
        $form->setDataFeedSchema($testDataFeedSchema);

        $form = $this->formalizeService->addForm($form);

        $this->assertEquals($testDataFeedSchema, $form->getDataFeedSchema());

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
            $this->assertStringContainsString('\'dataFeedSchema\' is not valid JSON', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
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
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
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
        $form3 = $this->testEntityManager->addForm();

        $forms = $this->formalizeService->getForms(0, 3);
        $this->assertCount(3, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
        $this->assertEquals($form2->getIdentifier(), $forms[1]->getIdentifier());
        $this->assertEquals($form3->getIdentifier(), $forms[2]->getIdentifier());

        $forms = $this->formalizeService->getForms(0, 2);
        $this->assertCount(2, $forms);
        $this->assertEquals($form1->getIdentifier(), $forms[0]->getIdentifier());
        $this->assertEquals($form2->getIdentifier(), $forms[1]->getIdentifier());

        $forms = $this->formalizeService->getForms(2, 2);
        $this->assertCount(1, $forms);
        $this->assertEquals($form3->getIdentifier(), $forms[0]->getIdentifier());

        $forms = $this->formalizeService->getForms(3, 2);
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

    public function testGetSubmissionNotFound()
    {
        try {
            $this->formalizeService->getSubmissionByIdentifier('404');
            $this->fail('exception not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_NOT_FOUND, $apiError->getStatusCode());
        }
    }

    public function testGetSubmissionsByFormId()
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm();
        $submission2_1 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_3 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $form3 = $this->testEntityManager->addForm();

        $submissions = $this->formalizeService->getSubmissionsByForm($form1->getIdentifier());
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissionsByForm($form2->getIdentifier());
        $this->assertCount(3, $submissions);
        $this->assertEquals($submission2_1->getIdentifier(), $submissions[0]->getIdentifier());
        $this->assertEquals($submission2_2->getIdentifier(), $submissions[1]->getIdentifier());
        $this->assertEquals($submission2_3->getIdentifier(), $submissions[2]->getIdentifier());

        // test pagination:
        $submissionPage1 = $this->formalizeService->getSubmissionsByForm($form2->getIdentifier(), [], 0, 2);
        $this->assertCount(2, $submissionPage1);

        $submissionPage2 = $this->formalizeService->getSubmissionsByForm($form2->getIdentifier(), [], 2, 2);
        $this->assertCount(1, $submissionPage2);

        $submissionPage3 = $this->formalizeService->getSubmissionsByForm($form2->getIdentifier(), [], 4, 2);
        $this->assertCount(0, $submissionPage3);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertResourcesAreAPermutationOf($submissions, [$submission2_1, $submission2_2, $submission2_3]);

        $submissions = $this->formalizeService->getSubmissionsByForm($form3->getIdentifier());
        $this->assertCount(0, $submissions);

        $submissions = $this->formalizeService->getSubmissionsByForm('foo');
        $this->assertCount(0, $submissions);
    }

    //    /**
    //     * Test commented because sqlite in its current version doesn't support the JSON_KEYS function.
    //     */
    //    public function testGetSubmissionsByFormIdWithOutputValidationKeys()
    //    {
    //        $form = $this->testEntityManager->addForm();
    //
    //        $submission0 = new Submission();
    //        $submission0->setDataFeedElement('{"foo": "bar"}');
    //        $submission0->setForm($form);
    //        $this->formalizeService->addSubmission($submission0);
    //
    //        $this->testEntityManager->addSubmission($form, '{"bar": "baz"}');
    //        $submission2 = $this->testEntityManager->addSubmission($form, '{"foo": "baz"}');
    //        $this->testEntityManager->addSubmission($form, '{"foo": "baz", "bar": 2}');
    //
    //        $submissions = $this->formalizeService->getSubmissionsByForm($form->getIdentifier());
    //        $this->assertCount(4, $submissions);
    //
    //        $submissions = $this->formalizeService->getSubmissionsByForm($form->getIdentifier(),
    //            [FormalizeService::OUTPUT_VALIDATION_FILTER => FormalizeService::OUTPUT_VALIDATION_KEYS]);
    //        $this->assertCount(2, $submissions);
    //        $this->assertResourcesAreAPermutationOf($submissions, [$submission0, $submission2]);
    //    }

    public function testGetSubmissionsByForms(): void
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm();
        $submission2_1 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_3 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $form3 = $this->testEntityManager->addForm();
        $submission3 = $this->testEntityManager->addSubmission($form3, '{"foo": "bar"}');

        $submissions = $this->formalizeService->getSubmissionsByForms([$form1->getIdentifier()], [], 0, 5);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $submissions = $this->formalizeService->getSubmissionsByForms([$form2->getIdentifier(), $form3->getIdentifier()],
            [], 0, 5);
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_3) {
            return $submission->getIdentifier() === $submission2_3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3) {
            return $submission->getIdentifier() === $submission3->getIdentifier();
        }));

        $submissions = $this->formalizeService->getSubmissionsByForms([], [], 0, 5);
        $this->assertCount(0, $submissions);

        // pagination:
        $submissions = $this->formalizeService->getSubmissionsByForms(
            [$form2->getIdentifier(), $form3->getIdentifier(), $form1->getIdentifier()], [], 0, 5);
        $this->assertCount(5, $submissions);

        $submissionPage1 = $this->formalizeService->getSubmissionsByForms(
            [$form2->getIdentifier(), $form3->getIdentifier(), $form1->getIdentifier()], [], 0, 3);
        $this->assertCount(3, $submissionPage1);
        $submissionPage2 = $this->formalizeService->getSubmissionsByForms(
            [$form2->getIdentifier(), $form3->getIdentifier(), $form1->getIdentifier()], [], 3, 3);
        $this->assertCount(2, $submissionPage2);
        $submissionPage3 = $this->formalizeService->getSubmissionsByForms(
            [$form2->getIdentifier(), $form3->getIdentifier(), $form1->getIdentifier()], [], 6, 3);
        $this->assertCount(0, $submissionPage3);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_3) {
            return $submission->getIdentifier() === $submission2_3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission3) {
            return $submission->getIdentifier() === $submission3->getIdentifier();
        }));
    }

    public function testGetCountSubmissionsByForms(): void
    {
        $form1 = $this->testEntityManager->addForm();
        $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm();
        $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $form3 = $this->testEntityManager->addForm();
        $this->testEntityManager->addSubmission($form3, '{"foo": "bar"}');

        $this->assertEquals(0, $this->formalizeService->getCountSubmissionsByForms([]));
        $this->assertEquals(1, $this->formalizeService->getCountSubmissionsByForms([$form1->getIdentifier()]));
        $this->assertEquals(4, $this->formalizeService->getCountSubmissionsByForms(
            [$form2->getIdentifier(), $form3->getIdentifier()]));
        $this->assertEquals(5, $this->formalizeService->getCountSubmissionsByForms(
            [$form2->getIdentifier(), $form3->getIdentifier(), $form1->getIdentifier()]));
        $this->assertEquals(0, $this->formalizeService->getCountSubmissionsByForms([]));
    }

    public function testGetSubmissionsByIdentifiers()
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm();
        $submission2_1 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_3 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $form1SubmissionIdentifiers = [$submission1->getIdentifier()];
        $submissions = $this->formalizeService->getSubmissionsByIdentifiers($form1SubmissionIdentifiers);
        $this->assertCount(1, $submissions);
        $this->assertEquals($submission1->getIdentifier(), $submissions[0]->getIdentifier());

        $form2SubmissionIdentifiers = [$submission2_1->getIdentifier(), $submission2_2->getIdentifier(), $submission2_3->getIdentifier()];

        $submissions = $this->formalizeService->getSubmissionsByIdentifiers($form2SubmissionIdentifiers);
        $this->assertCount(3, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_3) {
            return $submission->getIdentifier() === $submission2_3->getIdentifier();
        }));

        $allSubmissionIdentifiers = array_merge($form1SubmissionIdentifiers, $form2SubmissionIdentifiers);

        $submissions = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers);
        $this->assertCount(4, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_3) {
            return $submission->getIdentifier() === $submission2_3->getIdentifier();
        }));

        // test pagination
        $submissionPage1 = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            null, [], 0, 2);
        $this->assertCount(2, $submissionPage1);

        $submissionPage2 = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            null, [], 2, 2);
        $this->assertCount(2, $submissionPage2);

        $submissionsPage3 = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            null, [], 4, 2);
        $this->assertCount(0, $submissionsPage3);

        $this->assertEmpty(array_uintersect($submissionPage1, $submissionPage2, function ($sub1, $sub2) {
            return strcmp($sub1->getIdentifier(), $sub2->getIdentifier());
        }));

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_1) {
            return $submission->getIdentifier() === $submission2_1->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_2) {
            return $submission->getIdentifier() === $submission2_2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission2_3) {
            return $submission->getIdentifier() === $submission2_3->getIdentifier();
        }));

        $submissions = $this->formalizeService->getSubmissionsByIdentifiers([]);
        $this->assertCount(0, $submissions);
    }

    public function testGetSubmissionsByIdentifiersWhereFormIdentifierNotIn()
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm();
        $submission2_1 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_3 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $allSubmissionIdentifiers = [
            $submission1->getIdentifier(), $submission2_1->getIdentifier(),
            $submission2_2->getIdentifier(), $submission2_3->getIdentifier()];

        $submissions = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            [$form2->getIdentifier()]);
        $this->assertCount(1, $submissions);
        $this->assertCount(1, $this->selectWhere($submissions, function ($submission) use ($submission1) {
            return $submission->getIdentifier() === $submission1->getIdentifier();
        }));

        $submissions = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            [$form1->getIdentifier(), $form2->getIdentifier()]);
        $this->assertCount(0, $submissions);

        // test pagination:
        $submissionPage1 = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            [$form1->getIdentifier()], [], 0, 2);
        $this->assertCount(2, $submissionPage1);
        $submissionPage2 = $this->formalizeService->getSubmissionsByIdentifiers($allSubmissionIdentifiers,
            [$form1->getIdentifier()], [], 2, 2);
        $this->assertCount(1, $submissionPage2);

        $submissions = array_merge($submissionPage1, $submissionPage2);
        $this->assertTrue($this->containsResource($submissions, $submission2_1));
        $this->assertTrue($this->containsResource($submissions, $submission2_2));
        $this->assertTrue($this->containsResource($submissions, $submission2_3));
    }

    public function testGetSubmissionIdentifiersByFormId()
    {
        $form1 = $this->testEntityManager->addForm();
        $submission1 = $this->testEntityManager->addSubmission($form1, '{"foo": "bar"}');

        $form2 = $this->testEntityManager->addForm();
        $submission2_1 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_2 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');
        $submission2_3 = $this->testEntityManager->addSubmission($form2, '{"foo": "baz"}');

        $form3 = $this->testEntityManager->addForm();

        $submissionIdentifiers = $this->formalizeService->getSubmissionIdentifiersByForm($form1->getIdentifier());
        $this->assertCount(1, $submissionIdentifiers);
        $this->assertEquals($submission1->getIdentifier(), $submissionIdentifiers[0]);

        $submissionIdentifiers = $this->formalizeService->getSubmissionIdentifiersByForm($form2->getIdentifier());
        $this->assertCount(3, $submissionIdentifiers);
        $this->assertEquals($submission2_1->getIdentifier(), $submissionIdentifiers[0]);
        $this->assertEquals($submission2_2->getIdentifier(), $submissionIdentifiers[1]);
        $this->assertEquals($submission2_3->getIdentifier(), $submissionIdentifiers[2]);

        $submissionIdentifiers = $this->formalizeService->getSubmissionIdentifiersByForm($form3->getIdentifier());
        $this->assertCount(0, $submissionIdentifiers);

        $submissionIdentifiers = $this->formalizeService->getSubmissionIdentifiersByForm('foo');
        $this->assertCount(0, $submissionIdentifiers);
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
    public function testRemoveAllFormSubmissions()
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
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The dataFeedElement doesn\'t contain valid json!', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-invalid-json', $apiError->getErrorId());
        }
    }

    public function testAddSubmissionFormSchemaGeneration(): void
    {
        $form = $this->testEntityManager->addForm();

        $this->assertNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "bar", "fooNum": 1, "fooFloat": 4.2, "fooBool": false, "fooNull": null}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": "baz", "fooNum": 2, "fooFloat": 0.0, "fooBool": true, "fooNull": null}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws \JsonException
     */
    public function testAddSubmissionFormSchemaGenerationWithArrayProperty()
    {
        $form = $this->testEntityManager->addForm();

        // for empty arrays, the items type is guessed to be string
        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": [], "bar": [1, 2]}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
        $this->assertNotNull($this->testEntityManager->getForm($form->getIdentifier())->getDataFeedSchema());

        $submission = new Submission();
        $submission->setDataFeedElement('{"foo": ["baz"], "bar": [3]}');
        $submission->setForm($form);
        $submission = $this->formalizeService->addSubmission($submission);

        $this->assertEquals($submission->getIdentifier(),
            $this->testEntityManager->getSubmission($submission->getIdentifier())->getIdentifier());
    }

    public function testAddSubmissionFormSchemaGenerationSchemaViolationWrongKey()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm($form);
        $this->formalizeService->addSubmission($sub);

        $sub = new Submission();
        $sub->setDataFeedElement('{"fooz": "bar"}');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The data doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The data doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }
    }

    public function testAddSubmissionFormSchemaGenerationSchemaViolationWrongType()
    {
        $form = $this->testEntityManager->addForm();

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm($form);
        $this->formalizeService->addSubmission($sub);

        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": 41}');
        $sub->setForm($form);

        try {
            $this->formalizeService->addSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The data doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        try {
            $this->formalizeService->updateSubmission($sub);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The data doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
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
            $this->assertStringContainsString('The data doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }

        $submission->setDataFeedElement('{"familyName": "Doe", "email": "john@doe.com"}'); // undefined property 'email'

        try {
            $this->formalizeService->addSubmission($submission);
            $this->fail('expected exception not thrown');
        } catch (ApiError $apiError) {
            $this->assertStringContainsString('The data doesn\'t comply with the form\'s data schema', $apiError->getMessage());
            $this->assertEquals(Response::HTTP_BAD_REQUEST, $apiError->getStatusCode());
            $this->assertEquals('formalize:submission-data-feed-invalid-schema', $apiError->getErrorId());
        }
    }

    public function testFormAuthorization()
    {
        $form = new Form();
        $form->setName('Test Form');
        $form = $this->formalizeService->addForm($form);

        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $this->authorizationService->getGrantedFormItemActions($form));

        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadForm($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($form));
        $this->assertTrue($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form));

        $this->formalizeService->removeForm($form);

        $this->assertEquals([], $this->authorizationService->getGrantedFormItemActions($form));

        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToDeleteForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadForm($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToCreateFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToDeleteFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToUpdateFormSubmissions($form));
        $this->assertFalse($this->authorizationService->isCurrentUserAuthorizedToReadFormSubmissions($form));
    }
}
