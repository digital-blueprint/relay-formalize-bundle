<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Command;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Common\DemoForm;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DemoFormCommand extends Command
{
    private const FORM_MANAGER_ARGUMENT = 'formManager';

    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly ResourceActionGrantService $resourceActionGrantService)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setName('dbp:relay:formalize:demo-form')
            ->setAliases(['dbp:relay-formalize:demo-form'])
            ->setDescription('Creates the demo form and access grants')
            ->addArgument(self::FORM_MANAGER_ARGUMENT, InputArgument::OPTIONAL, 'The user identifier of the form manager. Default: "dummy"', 'dummy');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $form = $this->formalizeService->tryGetForm(DemoForm::FORM_IDENTIFIER);
        if ($form === null) {
            $output->writeln('Demo Form not found. Creating it...');

            $form = new Form();
            $form->setName('Demo Form');
            $form->setIdentifier(DemoForm::FORM_IDENTIFIER);
            $this->formalizeService->addForm($form,
                formManagerUserIdentifier: $input->getArgument(self::FORM_MANAGER_ARGUMENT),
                setIdentifier: false);
        } else {
            $output->writeln('Demo Form already exists.');
        }

        $grantedDemoFormActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            AuthorizationService::FORM_RESOURCE_CLASS, DemoForm::FORM_IDENTIFIER);
        if (false === in_array(AuthorizationService::READ_FORM_ACTION, $grantedDemoFormActions, true)) {
            $output->writeln('Granting read form permission to everybody...');
            $this->resourceActionGrantService->addResourceActionGrant(
                AuthorizationService::FORM_RESOURCE_CLASS, DemoForm::FORM_IDENTIFIER,
                AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        } else {
            $output->writeln('Read form permission already granted.');
        }

        $grantedDemoFormSubmissionActions = $this->resourceActionGrantService->getGrantedItemActionsForCurrentUser(
            AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, DemoForm::FORM_IDENTIFIER
        );
        if (false === in_array(AuthorizationService::CREATE_SUBMISSIONS_ACTION, $grantedDemoFormSubmissionActions, true)) {
            $output->writeln('Granting create submissions permission to everybody...');
            $this->resourceActionGrantService->addResourceActionGrant(
                AuthorizationService::SUBMISSION_COLLECTION_RESOURCE_CLASS, DemoForm::FORM_IDENTIFIER,
                AuthorizationService::CREATE_SUBMISSIONS_ACTION, dynamicGroupIdentifier: 'everybody');
        } else {
            $output->writeln('Create submissions permission already granted.');
        }

        return 0;
    }
}
