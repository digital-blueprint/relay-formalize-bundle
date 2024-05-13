<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Rest\RemoveAllFormSubmissionsController;
use Symfony\Component\HttpFoundation\Response;

class RemoveAllFormSubmissionsControllerTest extends RestTestCase
{
    private RemoveAllFormSubmissionsController $removeAllFormSubmissionsController;

    protected function setUp(): void
    {
        parent::setUp();

        $this->removeAllFormSubmissionsController = new RemoveAllFormSubmissionsController(
            $this->formalizeService, $this->authorizationService);
    }

    public function testRemoveAllFormSubmissions(): void
    {
        $form = $this->addForm();
        $submission = $this->addSubmission($form);
        $submission2 = $this->addSubmission($form);

        $this->assertNotNull($this->getSubmission($submission->getIdentifier()));
        $this->assertNotNull($this->getSubmission($submission2->getIdentifier()));

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_NAMESPACE, $form->getIdentifier(),
            AuthorizationService::DELETE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->removeAllFormSubmissionsController->__invoke(self::createRequestStack(
            '/formalize/submissions?formIdentifier='.$form->getIdentifier(), 'DELETE')->getCurrentRequest());

        $this->assertNull($this->getSubmission($submission->getIdentifier()));
        $this->assertNull($this->getSubmission($submission2->getIdentifier()));
    }

    public function testRemoveAllFormSubmissionsForbidden(): void
    {
        $form = $this->addForm();
        $this->addSubmission($form);

        try {
            $this->removeAllFormSubmissionsController->__invoke(self::createRequestStack(
                '/formalize/submissions?formIdentifier='.$form->getIdentifier(), 'DELETE')->getCurrentRequest());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }
}
