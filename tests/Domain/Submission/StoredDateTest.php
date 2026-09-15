<?php

declare(strict_types=1);

namespace App\Tests\Domain\Submission;

use App\Domain\Submission\ValueObject\StoredDate;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class StoredDateTest extends TestCase
{
    public function testItParsesAStorageDateAndFormatsMonthDayYear(): void
    {
        $date = StoredDate::tryFrom('1995-07-12');

        self::assertNotNull($date);
        self::assertSame('1995-07-12', $date->toStorage());
        self::assertSame('07/12/1995', $date->formatMonthDayYear());
        self::assertSame('1995-07-12', $date->toDateTime()->format('Y-m-d'));
    }

    public function testItRejectsInvalidAndNonCanonicalValues(): void
    {
        self::assertNull(StoredDate::tryFrom(''));
        self::assertNull(StoredDate::tryFrom('07/12/1995'));
        self::assertNull(StoredDate::tryFrom('1995-13-01'));
        self::assertNull(StoredDate::tryFrom('1995-02-29'));
    }

    public function testOverlayFormatsStoredDatesAndLeavesInvalidRaw(): void
    {
        self::assertSame('07/12/1995', StoredDate::overlay('1995-07-12'));
        self::assertSame('12/07/1995', StoredDate::overlay('12/07/1995'));
        self::assertSame('not-a-date', StoredDate::overlay('not-a-date'));
    }

    public function testItStoresADateTimeAsTheCanonicalDate(): void
    {
        $date = StoredDate::fromDateTime(new DateTimeImmutable('1995-07-12T15:30:00+00:00'));

        self::assertSame('1995-07-12', $date->toStorage());
        self::assertSame('07/12/1995', $date->formatMonthDayYear());
    }
}
