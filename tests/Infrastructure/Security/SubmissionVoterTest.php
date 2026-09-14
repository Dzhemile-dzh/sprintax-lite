<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Security;

use App\Domain\Questionnaire\Entity\Questionnaire;
use App\Domain\Questionnaire\ValueObject\FormType;
use App\Domain\Questionnaire\ValueObject\QuestionType;
use App\Domain\Submission\Entity\QuestionnaireSubmission;
use App\Domain\User\Entity\User;
use App\Domain\User\ValueObject\Email;
use App\Infrastructure\Security\SecurityUser;
use App\Infrastructure\Security\SubmissionVoter;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

final class SubmissionVoterTest extends TestCase
{
    public function testAClientCanAccessOnlyTheirOwnSubmission(): void
    {
        $owner = User::registerClient('u-1', new Email('owner@example.test'), 'hash');
        $other = User::registerClient('u-2', new Email('other@example.test'), 'hash');
        $own = $this->submission('sub-1', $owner);
        $theirs = $this->submission('sub-2', $other);
        $voter = new SubmissionVoter();

        $ownerToken = $this->token($owner);
        $otherToken = $this->token($other);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($ownerToken, $own, [SubmissionVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($ownerToken, $own, [SubmissionVoter::EDIT]));
        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($ownerToken, $own, [SubmissionVoter::DOWNLOAD]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($ownerToken, $theirs, [SubmissionVoter::VIEW]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote($otherToken, $own, [SubmissionVoter::DOWNLOAD]));
    }

    public function testAnAdminCanAccessAnySubmission(): void
    {
        $admin = User::provisionAdmin('admin-1', new Email('admin@example.test'), 'hash');
        $client = User::registerClient('u-1', new Email('client@example.test'), 'hash');
        $submission = $this->submission('sub-1', $client);
        $voter = new SubmissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($admin), $submission, [SubmissionVoter::VIEW]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($admin), $submission, [SubmissionVoter::EDIT]),
        );
        self::assertSame(
            VoterInterface::ACCESS_GRANTED,
            $voter->vote($this->token($admin), $submission, [SubmissionVoter::DOWNLOAD]),
        );
    }

    public function testAnUnauthenticatedUserCannotAccessASubmission(): void
    {
        $client = User::registerClient('u-1', new Email('client@example.test'), 'hash');
        $submission = $this->submission('sub-1', $client);
        $voter = new SubmissionVoter();

        self::assertSame(
            VoterInterface::ACCESS_DENIED,
            $voter->vote(new NullToken(), $submission, [SubmissionVoter::VIEW]),
        );
    }

    private function token(User $user): UsernamePasswordToken
    {
        $securityUser = SecurityUser::fromUser($user);

        return new UsernamePasswordToken($securityUser, 'main', $securityUser->getRoles());
    }

    private function submission(string $id, User $user): QuestionnaireSubmission
    {
        $questionnaire = Questionnaire::create('q-1', '1040-NR', FormType::Form1040Nr);
        $questionnaire->addStep('step-1', 'Personal');
        $questionnaire->addQuestion('step-1', 'q-name', 'first_name', 'First name', QuestionType::ShortText);

        return QuestionnaireSubmission::start(
            $id,
            $questionnaire,
            $user,
            new DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );
    }
}
