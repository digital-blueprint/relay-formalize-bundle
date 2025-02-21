<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Command;

use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Common\DemoForm;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DemoFormCommand extends Command
{
    public function __construct(
        private readonly FormalizeService $formalizeService,
        private readonly AuthorizationService $authorizationService)
    {
        parent::__construct();
    }

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('dbp:relay:formalize:demo-form');
        $this->setAliases(['dbp:relay-formalize:demo-form']);
        $this
            ->setDescription('Demo Form command')
            ->addArgument('action', InputArgument::REQUIRED, 'action: create-permissions');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $FORM_RESOURCE_CLASS = AuthorizationService::FORM_RESOURCE_CLASS;
        $CREATE_SUBMISSIONS_FORM_ACTION = AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION;
        $READ_FORM_ACTION = AuthorizationService::READ_FORM_ACTION;
        var_dump($FORM_RESOURCE_CLASS, $CREATE_SUBMISSIONS_FORM_ACTION, $READ_FORM_ACTION);
        // $demoFormRegistrationsAuthorizationResourceIdentifier = self::createBinaryUuidForInsertStatement();
        $groupIdentifier = 'everybody';

        $form = $this->formalizeService->getForm(DemoForm::FORM_IDENTIFIER);
        var_dump($form);

        $action = $input->getArgument('action');

        switch ($action) {
            case 'create-permissions':
                $output->writeln('Creating demo form permissions...');
                // TODO: Check if permissions already exist
                $this->authorizationService->registerForm($form);
                break;
            default:
                $output->writeln('Action not found!');

                return 1;
        }

        return 0;
    }
}
