<?php

declare(strict_types=1);

namespace App\Presentation\Client;

use App\Application\Submission\ClientHome\ListClientHome;
use App\Application\Submission\Finalize\FinalizeSubmission;
use App\Application\Submission\Get\GetSubmission;
use App\Application\Submission\GetWizardStep\GetWizardStep;
use App\Application\Submission\Review\ReviewSubmission;
use App\Application\Submission\SaveStep\SaveStep;
use App\Application\Submission\Start\StartSubmission;
use App\Domain\Questionnaire\Exception\QuestionnaireNotFound;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Domain\Submission\ValueObject\SubmissionStatus;
use App\Infrastructure\Security\SecurityUser;
use App\Infrastructure\Security\SubmissionVoter;
use App\Presentation\Client\Form\WizardStepFormType;
use App\Presentation\Http\RendersPdfWaitingPage;
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
    use RendersPdfWaitingPage;

    public function __construct(
        private readonly ListClientHome $listClientHome,
        private readonly StartSubmission $startSubmission,
        private readonly GetWizardStep $getWizardStep,
        private readonly SaveStep $saveStep,
        private readonly ReviewSubmission $reviewSubmission,
        private readonly FinalizeSubmission $finalizeSubmission,
        private readonly GetSubmission $getSubmission,
    ) {
    }

    #[Route('', name: 'client_home', methods: ['GET'])]
    public function home(): Response
    {
        $home = $this->listClientHome->execute($this->securityUser()->id());

        return $this->renderPdfWaiting('client/home.html.twig', [
            'questionnaires' => $home->questionnaires,
            'submissions' => $home->submissionsByQuestionnaireId,
        ], $home->hasAwaitingPdf());
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
        $this->authorizedSubmission(
            $id,
            $request->isMethod('POST') ? SubmissionVoter::EDIT : SubmissionVoter::VIEW,
        );

        try {
            $view = $this->getWizardStep->execute($id, $stepId);
        } catch (InvalidSubmission $exception) {
            if ($exception->deniesAccess()) {
                throw $this->createAccessDeniedException();
            }

            throw $this->createNotFoundException();
        }

        if (!$view->openForEditing) {
            if ($request->isMethod('POST')) {
                throw $this->createAccessDeniedException();
            }

            return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
        }

        $step = $view->editableStep();

        $form = $this->createForm(
            WizardStepFormType::class,
            WizardStepFormType::dataFromAnswers($view->visibleQuestions, $view->submission->answersByQuestionKey()),
            ['questions' => $view->visibleQuestions],
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
                if ($exception->deniesAccess()) {
                    throw $this->createAccessDeniedException();
                }

                $form->addError(new FormError($exception->getMessage()));

                return $this->render('client/wizard/step.html.twig', [
                    'form' => $form,
                    'submission' => $view->submission,
                    'questionnaire' => $view->submission->questionnaire(),
                    'step' => $step,
                    'previousStepId' => $view->previousStepId,
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
            'submission' => $view->submission,
            'questionnaire' => $view->submission->questionnaire(),
            'step' => $step,
            'previousStepId' => $view->previousStepId,
        ]);
    }

    #[Route('/submissions/{id}/review', name: 'client_wizard_review', methods: ['GET'])]
    public function review(string $id): Response
    {
        $this->authorizedSubmission($id, SubmissionVoter::VIEW);

        try {
            $review = $this->reviewSubmission->execute($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }

        if ($review->resumeStepId !== null) {
            return $this->redirectToRoute('client_wizard_step', [
                'id' => $id,
                'stepId' => $review->resumeStepId,
            ]);
        }

        return $this->renderPdfWaiting('client/wizard/review.html.twig', [
            'submission' => $review->submission,
            'questionnaire' => $review->submission->questionnaire(),
            'steps' => $review->steps,
            'canFinalize' => $review->canFinalize,
        ], $review->submission->status()->isAwaitingPdf());
    }

    #[Route('/submissions/{id}/finalize', name: 'client_wizard_finalize', methods: ['POST'])]
    public function finalize(Request $request, string $id): Response
    {
        $this->authorizedSubmission($id, SubmissionVoter::EDIT);

        if (!$this->isCsrfTokenValid('finalize-'.$id, $request->request->getString('_csrf_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $actor = $this->securityUser();

        try {
            $this->finalizeSubmission->execute($id, $actor->id(), $actor->isAdmin());
        } catch (InvalidSubmission $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
        }

        return $this->redirectToRoute('client_wizard_done', ['id' => $id]);
    }

    #[Route('/submissions/{id}/done', name: 'client_wizard_done', methods: ['GET'])]
    public function done(string $id): Response
    {
        $submission = $this->authorizedSubmission($id, SubmissionVoter::VIEW);

        if ($submission->status() === SubmissionStatus::InProgress) {
            return $this->redirectToRoute('client_wizard_review', ['id' => $id]);
        }

        return $this->renderPdfWaiting('client/wizard/done.html.twig', [
            'submission' => $submission,
            'questionnaire' => $submission->questionnaire(),
        ], $submission->status()->isAwaitingPdf());
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

    private function authorizedSubmission(string $id, string $attribute): QuestionnaireSubmission
    {
        try {
            $submission = $this->getSubmission->execute($id);
        } catch (SubmissionNotFound) {
            throw $this->createNotFoundException();
        }

        if (!$this->isGranted($attribute, $submission)) {
            throw $this->createNotFoundException();
        }

        return $submission;
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
