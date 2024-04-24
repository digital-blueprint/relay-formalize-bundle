<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Dbp\Relay\FormalizeBundle\Tests\Service\FormalizeServiceTest;
use Dbp\Relay\FormalizeBundle\TestUtils\TestEntityManager;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\Exception\MissingMappingDriverImplementation;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

abstract class RestTestCase extends TestCase
{
    protected const ADD_ACTION = 'add';
    protected const WRITE_ACTION = 'write';
    protected const READ_ACTION = 'read';
    protected const ADD_SUBMISSIONS_TO_FORM_ACTION = 'add_submissions';
    protected const WRITE_SUBMISSIONS_OF_FORM_ACTION = 'write_submissions';
    protected const READ_SUBMISSIONS_OF_FORM_ACTION = 'read_submissions';
    protected const OWN_ACTION = 'own';

    protected TestEntityManager $entityManager;
    protected AuthorizationService $authorizationService;
    protected FormalizeService $formalizeService;

    protected static function createRequestStack(): RequestStack
    {
        $request = Request::create('/formalize/forms/', 'GET');
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    protected static function getUserAttributeName(?string $formIdentifier, string $action): string
    {
        return 'DbpRelayFormalize/forms'.($formIdentifier !== null ? '/'.$formIdentifier : '').'.'.$action;
    }

    /**
     * @throws MissingMappingDriverImplementation
     * @throws Exception
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManager = TestEntityManager::create();
        $this->authorizationService = FormalizeServiceTest::createAuthorizationService();
        $this->formalizeService = FormalizeServiceTest::createFormalizeService(
            $this->entityManager->getEntityManager(), new EventDispatcher(), $this->authorizationService);
    }

    protected function addForm(string $name = 'Test Form', ?string $dataFeedSchema = null): Form
    {
        return $this->entityManager->addForm($name, $dataFeedSchema);
    }

    protected function getForm(string $identifier): ?Form
    {
        return $this->entityManager->getForm($identifier);
    }

    protected function addSubmission(?Form $form = null, string $jsonString = ''): Submission
    {
        return $this->entityManager->addSubmission($form, $jsonString);
    }

    protected function getSubmission(string $identifier): ?Submission
    {
        return $this->entityManager->getSubmission($identifier);
    }
}
