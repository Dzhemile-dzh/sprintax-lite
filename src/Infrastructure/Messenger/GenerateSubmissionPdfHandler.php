<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Pdf\GenerateSubmissionPdf;
use App\Domain\Calculation\Exception\UnsupportedFormType;
use App\Domain\Pdf\Exception\PdfGenerationFailed;
use App\Domain\Submission\Exception\InvalidSubmission;
use App\Domain\Submission\Exception\SubmissionNotFound;
use App\Infrastructure\Messenger\Message\GenerateSubmissionPdfMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class GenerateSubmissionPdfHandler
{
    public function __construct(
        private readonly GenerateSubmissionPdf $generateSubmissionPdf,
    ) {
    }

    public function __invoke(GenerateSubmissionPdfMessage $message): void
    {
        try {
            $this->generateSubmissionPdf->execute($message->submissionId);
        } catch (SubmissionNotFound $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } catch (InvalidSubmission $exception) {
            if ($exception->isRetryable()) {
                throw $exception;
            }

            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        } catch (PdfGenerationFailed $exception) {
            if (!$exception->isRetryable()) {
                throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
            }

            throw $exception;
        } catch (UnsupportedFormType $exception) {
            throw new UnrecoverableMessageHandlingException($exception->getMessage(), 0, $exception);
        }
    }
}
