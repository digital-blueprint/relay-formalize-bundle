<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Service;

use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FormalizeServiceTest extends WebTestCase
{
    private $api;

    protected function setUp(): void
    {
        $config = ORMSetup::createAnnotationMetadataConfiguration([__DIR__.'/../../src/Entity'], true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(CASE_LOWER, true));
        $em = EntityManager::create(
            [
                'driver' => 'pdo_sqlite',
                'memory' => true,
            ], $config
        );
        $em->getConnection()->executeQuery('CREATE TABLE formalize_submissions (identifier VARCHAR(50) NOT NULL, data_feed_element TEXT NOT NULL, date_created DATETIME NOT NULL, form VARCHAR(255) NOT NULL, PRIMARY KEY(identifier))');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->api = new FormalizeService($em, $eventDispatcher);
    }

    public function testCreateSubmission()
    {
        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm('form');
        $this->api->createSubmission($sub);

        $this->assertCount(1, $this->api->getSubmissions());
        $this->assertSame('form', $this->api->getSubmissions()[0]->getForm());
    }

    public function testGetSubmission()
    {
        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm('form');
        $this->api->createSubmission($sub);

        $id = $this->api->getSubmissions()[0]->getIdentifier();
        $sub = $this->api->getSubmissionByIdentifier($id);
        $this->assertSame($sub->getIdentifier(), $id);
    }

    public function testCreateSubmissionWrongKeys()
    {
        $sub = new Submission();
        $sub->setDataFeedElement('{"foo": "bar"}');
        $sub->setForm('form');
        $this->api->createSubmission($sub);

        $sub = new Submission();
        $sub->setDataFeedElement('{"quux": "bar"}');
        $sub->setForm('form');
        $this->expectExceptionMessageMatches('/doesn\'t match with the pevious submissions/');
        $this->api->createSubmission($sub);
    }
}
