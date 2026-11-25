<?php
/**
 * Description of SlotTest.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Bohdan Mamontov <bohdan.mamontov@dotsplatform.com>
 */

namespace Tests;

use Carbon\Carbon;
use Dots\Slot;
use Tests\Generators\SlotGenerator;

class SlotTest extends TestCase
{
    private const string TIMEZONE = 'Europe/Kyiv';

    public function testGetOrderDeadlineTimestampExpectsNullWithoutDeadline(): void
    {
        $slot = SlotGenerator::generate(['start' => '09:00', 'end' => '13:00']);

        $this->assertNull($slot->getOrderDeadlineTimestamp($this->timestamp('2026-12-04 09:00'), self::TIMEZONE));
    }

    public function testGetOrderDeadlineTimestampExpectsPreviousDayTime(): void
    {
        $slot = $this->generateMorningSlot('16:00', 1);

        $orderDeadlineTimestamp = $slot->getOrderDeadlineTimestamp($this->timestamp('2026-12-04 09:00'), self::TIMEZONE);

        $this->assertSame($this->timestamp('2026-12-03 16:00'), $orderDeadlineTimestamp);
    }

    public function testGetOrderDeadlineTimestampExpectsCalendarDaysAcrossClosedDay(): void
    {
        $thursdayMorningSlotStart = $this->timestamp('2026-12-03 09:00');

        $oneDayBeforeSlot = $this->generateMorningSlot('16:00', 1);
        $twoDaysBeforeSlot = $this->generateMorningSlot('16:00', 2);

        $this->assertSame(
            $this->timestamp('2026-12-02 16:00'),
            $oneDayBeforeSlot->getOrderDeadlineTimestamp($thursdayMorningSlotStart, self::TIMEZONE),
        );
        $this->assertSame(
            $this->timestamp('2026-12-01 16:00'),
            $twoDaysBeforeSlot->getOrderDeadlineTimestamp($thursdayMorningSlotStart, self::TIMEZONE),
        );
    }

    public function testGetOrderDeadlineTimestampExpectsSameDayWhenZeroDaysBefore(): void
    {
        $slot = $this->generateMorningSlot('07:00', 0);

        $orderDeadlineTimestamp = $slot->getOrderDeadlineTimestamp($this->timestamp('2026-12-04 09:00'), self::TIMEZONE);

        $this->assertSame($this->timestamp('2026-12-04 07:00'), $orderDeadlineTimestamp);
    }

    public function testGetOrderDeadlineTimestampExpectsSameDayWhenDaysBeforeIsEmpty(): void
    {
        $slot = $this->generateMorningSlot('07:00', null);

        $orderDeadlineTimestamp = $slot->getOrderDeadlineTimestamp($this->timestamp('2026-12-04 09:00'), self::TIMEZONE);

        $this->assertSame($this->timestamp('2026-12-04 07:00'), $orderDeadlineTimestamp);
    }

    public function testGetOrderDeadlineTimestampExpectsLocalTimeAcrossDstChange(): void
    {
        $slot = $this->generateMorningSlot('16:00', 1);

        $orderDeadlineTimestamp = $slot->getOrderDeadlineTimestamp($this->timestamp('2026-03-30 09:00'), self::TIMEZONE);

        $this->assertSame($this->timestamp('2026-03-29 16:00'), $orderDeadlineTimestamp);
    }

    public function testGetOrderDeadlineTimestampExpectsSlotStartDateForOvernightSlot(): void
    {
        $slot = SlotGenerator::generate([
            'start' => '22:00',
            'end' => '02:00',
            'orderDeadline' => '16:00',
            'orderDeadlineDaysBefore' => 0,
        ]);

        $orderDeadlineTimestamp = $slot->getOrderDeadlineTimestamp($this->timestamp('2026-12-04 22:00'), self::TIMEZONE);

        $this->assertSame($this->timestamp('2026-12-04 16:00'), $orderDeadlineTimestamp);
    }

    public function testIsOrderDeadlinePassedExpectsFalseOneSecondBefore(): void
    {
        $slot = $this->generateMorningSlot('16:00', 2);

        $isOrderDeadlinePassed = $slot->isOrderDeadlinePassed(
            $this->timestamp('2026-12-03 09:00'),
            $this->timestamp('2026-12-01 15:59:59'),
            self::TIMEZONE,
        );

        $this->assertFalse($isOrderDeadlinePassed);
    }

    public function testIsOrderDeadlinePassedExpectsTrueAtDeadline(): void
    {
        $slot = $this->generateMorningSlot('16:00', 2);

        $isOrderDeadlinePassed = $slot->isOrderDeadlinePassed(
            $this->timestamp('2026-12-03 09:00'),
            $this->timestamp('2026-12-01 16:00'),
            self::TIMEZONE,
        );

        $this->assertTrue($isOrderDeadlinePassed);
    }

    public function testIsOrderDeadlinePassedExpectsFalseWithoutDeadline(): void
    {
        $slot = SlotGenerator::generate(['start' => '09:00', 'end' => '13:00']);

        $isOrderDeadlinePassed = $slot->isOrderDeadlinePassed(
            $this->timestamp('2026-12-03 09:00'),
            $this->timestamp('2026-12-03 08:59'),
            self::TIMEZONE,
        );

        $this->assertFalse($isOrderDeadlinePassed);
    }

    public function testHasOrderDeadlineExpectsFalseForMalformedTime(): void
    {
        $slot = $this->generateMorningSlot('25:00', 1);

        $this->assertFalse($slot->hasOrderDeadline());
        $this->assertNull($slot->getOrderDeadlineTimestamp($this->timestamp('2026-12-04 09:00'), self::TIMEZONE));
    }

    public function testToArrayExpectsOrderDeadlineKeys(): void
    {
        $slot = $this->generateMorningSlot('16:00', 1);

        $slotData = $slot->toArray();

        $this->assertSame('16:00', $slotData['orderDeadline']);
        $this->assertSame(1, $slotData['orderDeadlineDaysBefore']);
    }

    public function testFromArrayExpectsEmptyOrderDeadlineForOldData(): void
    {
        $slot = Slot::fromArray(['start' => '09:00', 'end' => '13:00', 'status' => 1, 'capacity' => null]);

        $this->assertNull($slot->getOrderDeadline());
        $this->assertNull($slot->getOrderDeadlineDaysBefore());
        $this->assertFalse($slot->hasOrderDeadline());
    }

    private function generateMorningSlot(string $orderDeadline, ?int $orderDeadlineDaysBefore): Slot
    {
        return SlotGenerator::generate([
            'start' => '09:00',
            'end' => '13:00',
            'orderDeadline' => $orderDeadline,
            'orderDeadlineDaysBefore' => $orderDeadlineDaysBefore,
        ]);
    }

    private function timestamp(string $localDateTime): int
    {
        return Carbon::parse($localDateTime, self::TIMEZONE)->getTimestamp();
    }
}
