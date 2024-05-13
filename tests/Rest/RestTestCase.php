<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Tests\AbstractTestCase;
use Dbp\Relay\FormalizeBundle\TestUtils\TestEntityManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class RestTestCase extends AbstractTestCase
{
    protected const TEST_FORM_NAME = TestEntityManager::DEFAULT_FORM_NAME;

    protected TestEntityManager $testEntityManager;
    protected AuthorizationService $authorizationService;
    protected FormalizeService $formalizeService;

    protected static function createRequestStack(string $uri = '/formalize/forms/', string $method = 'GET'): RequestStack
    {
        $request = Request::create($uri, $method);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    protected static function getUserAttributeName(?string $formIdentifier, string $action): string
    {
        return 'DbpRelayFormalize/forms'.($formIdentifier !== null ? '/'.$formIdentifier : '').'.'.$action;
    }

    protected function addForm(string $name = self::TEST_FORM_NAME, ?string $dataFeedSchema = null): Form
    {
        return $this->testEntityManager->addForm($name, $dataFeedSchema);
    }

    protected function getForm(string $identifier): ?Form
    {
        return $this->testEntityManager->getForm($identifier);
    }

    protected function addSubmission(?Form $form = null, string $jsonString = ''): Submission
    {
        return $this->testEntityManager->addSubmission($form, $jsonString);
    }

    protected function getSubmission(string $identifier): ?Submission
    {
        return $this->testEntityManager->getSubmission($identifier);
    }
}
