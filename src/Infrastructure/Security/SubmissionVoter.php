<?php

declare(strict_types=1);

namespace App\Infrastructure\Security;

use App\Domain\Submission\Entity\QuestionnaireSubmission;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, QuestionnaireSubmission>
 */
final class SubmissionVoter extends Voter
{
    public const VIEW = 'submission_view';
    public const EDIT = 'submission_edit';
    public const DOWNLOAD = 'submission_download';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $subject instanceof QuestionnaireSubmission
            && in_array($attribute, [self::VIEW, self::EDIT, self::DOWNLOAD], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof SecurityUser) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (!$user->isClient()) {
            return false;
        }

        return $subject->user()->id() === $user->id();
    }
}
