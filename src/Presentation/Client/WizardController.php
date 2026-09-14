<?php

declare(strict_types=1);

namespace App\Presentation\Client;

use App\Application\Questionnaire\List\ListQuestionnaires;
use App\Application\Submission\Finalize\FinalizeSubmission;
use App\Application\Submission\Review\ReviewSubmission;
use App\Application\Submission\SaveStep\SaveStep;
use App\Application\Submission\Start\StartSubmission;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Questionnaire\QuestionVisibilityEvaluator;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\Repository\SubmissionRepositoryInterface;
use App\Domain\Submission\SubmissionCompleteness;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Infrastructure\Security\SecurityUser;
use App\Infrastructure\Security\SubmissionVoter;
use App\Presentation\Client\Form\WizardStepFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/client')]
#[IsGranted('ROLE_CLIENT')]
final class WizardController extends AbstractController
{
    public function __construct(
        private readonly ListQuestionnaires $listQuestionnaires,
        private readonly StartSubmission $startSubmission,
        private readonly SaveStep $saveStep,
        private readonly ReviewSubmission $reviewSubmission,
        private readonly FinalizeSubmission $finalizeSubmission,
        private readonly SubmissionRepositoryInterface $submissions,
        private readonly QuestionVisibilityEvaluator $visibility,
        private readonly SubmissionCompleteness $completeness,
    ) {
    }

    #[Route('', name: 'client_home', methods: ['GET'])]
    public function home(): Response
    {
        $submissionsByQuestionnaire = [];

        foreach ($this->submissions->findForUser($this->securityUser()->id()) as $submission) {
            $submissionsByQuestionnaire[$submission->questionnaire()->id()] = $submission;
        }

        return $this->render('client/home.html.twig', [
            'questionnaires' => $this->listQuestionnaires->execute(),
            'submissions' => $submissionsByQuestionnaire,
        ]);
    }

    #[Route('/questionnaires/{questionnaireId}/start', name: 'client_wizard_start', methods: ['POST'])]
    public function start(Request $request, string $questionnaireId): Response
    {
        if (!$this->isCsrfTokenValid('start-questionnaire', $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $submission = $this->startSubmission->execute($this->securityUser()->id(), $questionnaireId);
        } catch (QuestionnaireNotFound) {
            throw $this->createNotFoundException();
        } catch (InvalidSubmission $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('client_home');
        }

        $this->denyAccessUnlessGranted(SubmissionVoter::EDIT, $submission);

        return $this->redirectAfterStart($submission);
    }

    #[Route('/submissions/{id}/steps/{stepId}', name: 'client_wizard_step', methods: ['GET', 'POST'])]
    public function step(Request $request, string $id, string $stepId): Response
    {
        $submission = $this->submission($id);
        $this->denyAccessUnlessGranted(
            $request->isMethod('POST') ? SubmissionVoter::EDIT : SubmissionVoter::VIEW,
            $submission,
        );

        if ($submission->status() !== SubmissionStatus::InProgress) {
            if ($request->isMethod('POST')) {
                throw $this->createAccessDeniedException();
            }

            return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
        }

        $step = $submission->questionnaire()->findStep($stepId);

        if ($step === null) {
            throw $this->createNotFoundException();
        }

        if ($step->position() > $submission->currentStep()->position()) {
            throw $this->createAccessDeniedException();
        }

        $answersByKey = $submission->answersByQuestionKey();
        $visible = $this->visibility->visibleQuestionsOnStep(
            $step,
            $submission->questionnaire(),
            $answersByKey,
        );
        $form = $this->createForm(
            WizardStepFormType::class,
            WizardStepFormType::dataFromAnswers($visible, $answersByKey),
            ['questions' => $visible],
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submitted = $form->getData();

            try {
                $next = $this->saveStep->execute(
                    $id,
                    $stepId,
                    is_array($submitted) ? $submitted : [],
                    true,
                );
            } catch (InvalidSubmission $exception) {
                $form->addError(new FormError($exception->getMessage()));

                return $this->render('client/wizard/step.html.twig', [
                    'form' => $form,
                    'submission' => $submission,
                    'questionnaire' => $submission->questionnaire(),
                    'step' => $step,
                    'previousStepId' => $submission->questionnaire()->previousStepBefore($stepId)?->id(),
                ]);
            }

            if ($next === SaveStep::NEXT_REVIEW) {
                return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
            }

            return $this->redirectToRoute('client_wizard_step', [
                'id' => $id,
                'stepId' => $next,
            ]);
        }

        return $this->render('client/wizard/step.html.twig', [
            'form' => $form,
            'submission' => $submission,
            'questionnaire' => $submission->questionnaire(),
            'step' => $step,
            'previousStepId' => $submission->questionnaire()->previousStepBefore($stepId)?->id(),
        ]);
    }

    #[Route('/submissions/{id}/review', name: 'client_wizard_review', methods: ['GET'])]
    public function review(string $id): Response
    {
        try {
            $submission = $this->reviewSubmission->execute($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }

        $this->denyAccessUnlessGranted(SubmissionVoter::VIEW, $submission);

        if (
            $submission->status() === SubmissionStatus::InProgress
            && !$this->completeness->isComplete($submission)
        ) {
            return $this->redirectToRoute('client_wizard_step', [
                'id' => $id,
                'stepId' => $submission->currentStep()->id(),
            ]);
        }

        return $this->render('client/wizard/review.html.twig', [
            'submission' => $submission,
            'questionnaire' => $submission->questionnaire(),
            'steps' => $this->reviewSubmission->visibleAnswersByStep($submission),
            'canFinalize' => $submission->status() === SubmissionStatus::InProgress,
        ]);
    }

    #[Route('/submissions/{id}/finalize', name: 'client_wizard_finalize', methods: ['POST'])]
    public function finalize(Request $request, string $id): Response
    {
        if (!$this->isCsrfTokenValid('finalize-'.$id, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $submission = $this->submission($id);
        $this->denyAccessUnlessGranted(SubmissionVoter::EDIT, $submission);

        try {
            $this->finalizeSubmission->execute($id);
        } catch (InvalidSubmission $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
        }

        return $this->redirectToRoute('client_wizard_done', ['id' => $id]);
    }

    #[Route('/submissions/{id}/done', name: 'client_wizard_done', methods: ['GET'])]
    public function done(string $id): Response
    {
        $submission = $this->submission($id);
        $this->denyAccessUnlessGranted(SubmissionVoter::VIEW, $submission);

        if ($submission->status() === SubmissionStatus::InProgress) {
            return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
        }

        return $this->render('client/wizard/done.html.twig', [
            'submission' => $submission,
            'questionnaire' => $submission->questionnaire(),
        ]);
    }

    private function redirectAfterStart(QuestionnaireSubmission $submission): Response
    {
        if ($submission->status() !== SubmissionStatus::InProgress) {
            return $this->redirectToRoute('client_wizard_review', ['id' => $submission->id()]);
        }

        return $this->redirectToRoute('client_wizard_step', [
            'id' => $submission->id(),
            'stepId' => $submission->currentStep()->id(),
        ]);
    }

    private function submission(string $id): QuestionnaireSubmission
    {
        try {
            return $this->submissions->get($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }
    }

    private function securityUser(): SecurityUser
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
