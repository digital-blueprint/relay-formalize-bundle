<?php

declare(strict_types=1);

namespace Dbp\Relay\FormalizeBundle\Tests\Rest;

use Dbp\Relay\AuthorizationBundle\API\ResourceActionGrantService;
use Dbp\Relay\CoreBundle\Exception\ApiError;
use Dbp\Relay\CoreBundle\TestUtils\DataProviderTester;
use Dbp\Relay\FormalizeBundle\Authorization\AuthorizationService;
use Dbp\Relay\FormalizeBundle\Entity\Form;
use Dbp\Relay\FormalizeBundle\Entity\Submission;
use Dbp\Relay\FormalizeBundle\Rest\FormProvider;
use Dbp\Relay\FormalizeBundle\Service\FormalizeService;
use Symfony\Component\HttpFoundation\Response;

class FormProviderTest extends RestTestCase
{
    private DataProviderTester $formProviderTester;

    protected function setUp(): void
    {
        parent::setUp();

        $formProvider = new FormProvider($this->formalizeService, $this->authorizationService, self::createRequestStack());
        $this->formProviderTester = DataProviderTester::create($formProvider, Form::class, ['FormalizeForm:output']);
    }

    public function testGetFormItemWithReadPermission()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertEquals($form->getIdentifier(), $this->formProviderTester->getItem($form->getIdentifier())->getIdentifier());
    }

    public function testGetFormItemWithManagePermission()
    {
        $form = $this->addForm();

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $formPersistence = $this->formProviderTester->getItem($form->getIdentifier());
        assert($formPersistence instanceof Form);

        $this->assertEquals($form->getIdentifier(), $formPersistence->getIdentifier());
        $this->assertEquals($form->getName(), $formPersistence->getName());
        $this->assertEquals([ResourceActionGrantService::MANAGE_ACTION], $formPersistence->getGrantedActions());
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

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        try {
            $this->formProviderTester->getItem($form->getIdentifier());
            $this->fail('exception was not thrown as expected');
        } catch (ApiError $apiError) {
            $this->assertEquals(Response::HTTP_FORBIDDEN, $apiError->getStatusCode());
        }
    }

    public function testGetFormCollection()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();
        $form3 = $this->addForm();
        $form4 = $this->addForm();
        $form5 = $this->addForm();

        // current has read/manage grants for forms 1, 3, and 4
        $authorizationResource = $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier());
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form4->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form5->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(3, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form1) {
            return $form->getIdentifier() === $form1->getIdentifier()
                && count($form->getGrantedActions()) === 2
                && in_array(AuthorizationService::READ_FORM_ACTION, $form->getGrantedActions(), true)
                && in_array(AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, $form->getGrantedActions(), true);
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier()
                && $form->getGrantedActions() === [AuthorizationService::READ_FORM_ACTION];
        }));
    }

    public function testGetFormCollectionWithReadAllSubmissionsAllowedOnlyFilter(): void
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();
        $form3 = $this->addForm();
        $form4 = $this->addForm();
        $form5 = $this->addForm();
        $form6 = $this->addForm();
        $form7 = $this->addForm();

        // read form submissions missing
        $authorizationResource = $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier());
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        // manage form -> ok
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        // read form and read form submissions -> ok
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form4->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form5->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        // read form only
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form6->getIdentifier(),
            AuthorizationService::READ_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form7->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $forms = $this->formProviderTester->getCollection([FormalizeService::WHERE_READ_FORM_SUBMISSIONS_GRANTED_FILTER => true]);
        $this->assertCount(3, $forms);

        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier()
                && $this->isPermutationOf(
                    [AuthorizationService::READ_FORM_ACTION, AuthorizationService::READ_SUBMISSIONS_FORM_ACTION],
                    $form->getGrantedActions());
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form7) {
            return $form->getIdentifier() === $form7->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));

        // test pagination:
        $formPage1 = $this->formProviderTester->getPage(1, 2,
            [FormalizeService::WHERE_READ_FORM_SUBMISSIONS_GRANTED_FILTER => true]);
        $this->assertCount(2, $formPage1);
        $formPage2 = $this->formProviderTester->getPage(2, 2,
            [FormalizeService::WHERE_READ_FORM_SUBMISSIONS_GRANTED_FILTER => true]);
        $this->assertCount(1, $formPage2);
        $forms = array_merge($formPage1, $formPage2);
        $this->assertCount(3, $forms);

        // manage form -> ok
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form7->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);

        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier()
                && $this->isPermutationOf(
                    [AuthorizationService::READ_FORM_ACTION, AuthorizationService::READ_SUBMISSIONS_FORM_ACTION],
                    $form->getGrantedActions());
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form7) {
            return $form->getIdentifier() === $form7->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
    }

    public function testGetFormCollectionWithWithSubmissionsOnlyFilter(): void
    {
        $USER_1 = 'user_1';
        $USER_2 = 'user_2';
        $USER_3 = 'user_3';
        $USER_4 = 'user_4';
        $SOMEONE_ELSE = 'someone_else';

        $form1 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: []
        );
        $form2 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]
        );
        $form3 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: []
        );
        $form4 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );
        $form5 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: []
        );
        $form6 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::DELETE_SUBMISSION_ACTION]
        );
        $form7 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: []
        );
        $form8 = $this->addForm(
            grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]
        );

        // 'noise' forms:
        $form9 = $this->addForm(
            grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION]
        );
        $form10 = $this->addForm(grantBasedSubmissionAuthorization: true,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );
        $form11 = $this->addForm(grantBasedSubmissionAuthorization: false,
            allowedSubmissionStates: Submission::SUBMISSION_STATE_DRAFT | Submission::SUBMISSION_STATE_SUBMITTED,
            actionsAllowedWhenSubmitted: [AuthorizationService::READ_SUBMISSION_ACTION, AuthorizationService::UPDATE_SUBMISSION_ACTION]
        );

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form3->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form4->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form5->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form6->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form7->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form8->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');

        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form9->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form10->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, dynamicGroupIdentifier: 'everybody');
        // NOTE: no read form permission for users 1, 2, 3 and 4
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form11->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, userIdentifier: $SOMEONE_ELSE);

        $this->addSubmission($form1, creatorId: $USER_1);

        $this->addSubmission($form2, creatorId: $USER_2);
        $this->addSubmission($form2, creatorId: $USER_3);

        $this->addSubmission($form3, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $USER_4);
        $this->addSubmission($form3, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $USER_1);

        $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $USER_3);
        $this->addSubmission($form4, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $USER_4);

        // grant based submission authorization forms:
        $submission5_1 = $this->addSubmission($form5, creatorId: $USER_2);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission5_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_2);

        $submission6_1 = $this->addSubmission($form6, $USER_1);
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission6_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_1);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, $USER_4);

        $submission7_1 = $this->addSubmission($form7, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $USER_2);
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission7_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_2);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::DELETE_SUBMISSION_ACTION, $USER_1);
        $submission7_2 = $this->addSubmission($form7, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $USER_1);
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission7_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_1);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, $USER_2);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, $USER_4);

        $submission8_1 = $this->addSubmission($form8, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $USER_4);
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission8_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_4);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            AuthorizationService::READ_SUBMISSION_ACTION, $USER_1);
        $submission8_2 = $this->addSubmission($form8, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $USER_3);
        $rag = $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission8_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_3);
        $this->authorizationTestEntityManager->addResourceActionGrant($rag->getAuthorizationResource(),
            ResourceActionGrantService::MANAGE_ACTION, $USER_2);

        $this->addSubmission($form9, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $SOMEONE_ELSE);
        $this->addSubmission($form9, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $SOMEONE_ELSE);

        $submission10_1 = $this->addSubmission($form10, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $SOMEONE_ELSE);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission10_1->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $SOMEONE_ELSE);
        $submission10_2 = $this->addSubmission($form10, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $SOMEONE_ELSE);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::SUBMISSION_RESOURCE_CLASS, $submission10_2->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, $SOMEONE_ELSE);

        // NOTE: users don't have read form permissions for form 11:
        $this->addSubmission($form11, submissionState: Submission::SUBMISSION_STATE_DRAFT, creatorId: $USER_1);
        $this->addSubmission($form11, submissionState: Submission::SUBMISSION_STATE_SUBMITTED, creatorId: $USER_2);

        $this->login($USER_1);
        $forms = $this->formProviderTester->getCollection([FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true]);
        $this->assertCount(2, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form6) {
            return $form->getIdentifier() === $form6->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form8) {
            return $form->getIdentifier() === $form8->getIdentifier();
        }));

        $this->login($USER_2);
        $forms = $this->formProviderTester->getCollection([FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true]);
        $this->assertCount(3, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form2) {
            return $form->getIdentifier() === $form2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form7) {
            return $form->getIdentifier() === $form7->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form8) {
            return $form->getIdentifier() === $form8->getIdentifier();
        }));

        $this->login($USER_3);
        $forms = $this->formProviderTester->getCollection([FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true]);
        $this->assertCount(3, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form2) {
            return $form->getIdentifier() === $form2->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form8) {
            return $form->getIdentifier() === $form8->getIdentifier();
        }));

        $this->login($USER_4);
        $forms = $this->formProviderTester->getCollection([FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true]);
        $this->assertCount(4, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form6) {
            return $form->getIdentifier() === $form6->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form8) {
            return $form->getIdentifier() === $form8->getIdentifier();
        }));

        // test pagination:
        $formPage1 = $this->formProviderTester->getPage(1, 2, [
            FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true,
        ]);
        $this->assertCount(2, $formPage1);
        $formPage2 = $this->formProviderTester->getPage(2, 2, [
            FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true,
        ]);
        $this->assertCount(2, $formPage2);
        $formPage3 = $this->formProviderTester->getPage(3, 2, [
            FormalizeService::WHERE_CONTAINS_SUBMISSIONS_MAY_READ_FILTER => true,
        ]);
        $this->assertCount(0, $formPage3);

        $forms = array_merge($formPage1, $formPage2);
        $this->assertCount(4, $forms);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form6) {
            return $form->getIdentifier() === $form6->getIdentifier();
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form8) {
            return $form->getIdentifier() === $form8->getIdentifier();
        }));
    }

    public function testGetFormCollectionPagination()
    {
        $form1 = $this->addForm();
        $form2 = $this->addForm();
        $form3 = $this->addForm();
        $form4 = $this->addForm();
        $form5 = $this->addForm();

        // current has read/manage grants for forms 1, 3, and 4
        $authorizationResource = $this->authorizationTestEntityManager->addAuthorizationResource(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier());
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addResourceActionGrant($authorizationResource,
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form1->getIdentifier(),
            AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form2->getIdentifier(),
            AuthorizationService::UPDATE_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form3->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form4->getIdentifier(),
            AuthorizationService::READ_FORM_ACTION, self::CURRENT_USER_IDENTIFIER);
        $this->authorizationTestEntityManager->addAuthorizationResourceAndActionGrant(
            AuthorizationService::FORM_RESOURCE_CLASS, $form5->getIdentifier(),
            ResourceActionGrantService::MANAGE_ACTION, self::ANOTHER_USER_IDENTIFIER);

        $formPage1 = $this->formProviderTester->getPage(1, 2);
        $this->assertCount(2, $formPage1);
        $formPage2 = $this->formProviderTester->getPage(2, 2);
        $this->assertCount(1, $formPage2);

        $forms = array_merge($formPage1, $formPage2);
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form1) {
            return $form->getIdentifier() === $form1->getIdentifier()
                && count($form->getGrantedActions()) === 2
                && in_array(AuthorizationService::READ_FORM_ACTION, $form->getGrantedActions(), true)
                && in_array(AuthorizationService::CREATE_SUBMISSIONS_FORM_ACTION, $form->getGrantedActions(), true);
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form3) {
            return $form->getIdentifier() === $form3->getIdentifier()
                && $form->getGrantedActions() === [ResourceActionGrantService::MANAGE_ACTION];
        }));
        $this->assertCount(1, $this->selectWhere($forms, function ($form) use ($form4) {
            return $form->getIdentifier() === $form4->getIdentifier()
                && $form->getGrantedActions() === [AuthorizationService::READ_FORM_ACTION];
        }));
    }

    public function testGetFormCollectionEmpty()
    {
        $this->addForm();
        $this->addForm();

        $forms = $this->formProviderTester->getCollection();
        $this->assertCount(0, $forms);
    }
}
