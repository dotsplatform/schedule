<?php
/**
 * Description of SlotEndOfDayTest.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Bogdan Mamontov <bohdan.mamontov@dotsplatform.com>
 */

namespace Tests;

use Carbon\Carbon;
use Dots\Day;
use Dots\Slot;
use Dots\WorkTimeSchedule;
use Tests\Generators\WorkTimeGenerator;

/**
 * A slot can only hold HH:MM, so a slot ending at 23:59 means "until the end of the day" and covers the whole
 * last minute (23:59:00-23:59:59). Any other end keeps its exact boundary: 18:00:00 is inside a slot until 18:00,
 * 18:00:30 is not.
 */
class SlotEndOfDayTest extends TestCase
{
    private const string TIMEZONE = 'Europe/Kyiv';

    public function testIsWorkingAtTimeExpectsYesAtTheStartOfTheLastMinute(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);

        $this->assertTrue($schedule->isWorkingAtTime($this->dayTimestamp('23:59:00')));
    }

    public function testIsWorkingAtTimeExpectsYesInTheMiddleOfTheLastMinute(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);

        $this->assertTrue($schedule->isWorkingAtTime($this->dayTimestamp('23:59:30')));
    }

    public function testIsWorkingAtTimeExpectsYesAtTheLastSecondOfTheDay(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);

        $this->assertTrue($schedule->isWorkingAtTime($this->dayTimestamp('23:59:59')));
    }

    public function testIsWorkingAtTimeExpectsYesAtTheLastSecondForEveningSlotAfterBreak(): void
    {
        $schedule = $this->generateScheduleForOneDay([
            ['start' => '10:00', 'end' => '14:00'],
            ['start' => '18:00', 'end' => '23:59'],
        ]);

        $this->assertTrue($schedule->isWorkingAtTime($this->dayTimestamp('23:59:59')));
        $this->assertFalse($schedule->isWorkingAtTime($this->dayTimestamp('14:00:30')));
    }

    public function testIsWorkingAtTimeExpectsYesAtMidnightWhenTheNextDayIsAlsoAlwaysOn(): void
    {
        $schedule = $this->generateAlwaysOnSchedule();
        $nextMidnight = $this->dayDate()->addDay()->getTimestamp();

        $this->assertTrue($schedule->isWorkingAtTime($nextMidnight));
    }

    public function testIsWorkingAtTimeExpectsNoAtMidnightWhenTheNextDayIsOff(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);
        $nextMidnight = $this->dayDate()->addDay()->getTimestamp();

        $this->assertFalse($schedule->isWorkingAtTime($nextMidnight));
    }

    public function testIsWorkingAtTimeExpectsNoAfterMidnightWhenTheNextDayIsOff(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);
        $afterMidnight = $this->dayDate()->addDay()->setTimeFromTimeString('00:00:30')->getTimestamp();

        $this->assertFalse($schedule->isWorkingAtTime($afterMidnight));
    }

    public function testIsWorkingAtTimeExpectsYesAtTheExactEndOfARegularSlot(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '08:00', 'end' => '18:00']]);

        $this->assertTrue($schedule->isWorkingAtTime($this->dayTimestamp('18:00:00')));
    }

    public function testIsWorkingAtTimeExpectsNoWithinTheEndMinuteOfARegularSlot(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '08:00', 'end' => '18:00']]);

        $this->assertFalse($schedule->isWorkingAtTime($this->dayTimestamp('18:00:30')));
    }

    public function testIsWorkingAtTimeExpectsNoWithinTheEndMinuteOfASlotEndingAt2358(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '08:00', 'end' => '23:58']]);

        $this->assertFalse($schedule->isWorkingAtTime($this->dayTimestamp('23:58:30')));
    }

    public function testIsWorkingAtTimeExpectsYesAtTheLastSecondOfADaylightSavingDay(): void
    {
        // Europe/Kyiv switches to summer time on 2023-03-26.
        $daylightSavingDay = Carbon::createFromFormat('Y-m-d H:i:s', '2023-03-26 00:00:00', self::TIMEZONE);
        $schedule = $this->generateScheduleForDay($daylightSavingDay, [['start' => '00:00', 'end' => '23:59']]);
        $lastSecond = (clone $daylightSavingDay)->setTimeFromTimeString('23:59:59')->getTimestamp();

        $this->assertTrue($schedule->isWorkingAtTime($lastSecond));
    }

    public function testIsWorkingDayExpectsYesWithinTheLastMinute(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);

        $this->assertTrue($schedule->isWorkingDay($this->dayTimestamp('23:59:30')));
    }

    public function testIsWorkingDayExpectsNoAtTheExactEndOfARegularSlot(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '08:00', 'end' => '18:00']]);

        $this->assertFalse($schedule->isWorkingDay($this->dayTimestamp('18:00:00')));
    }

    public function testFindSlotByStartTimestampExpectsSlotWithinTheLastMinute(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);

        $slot = $schedule->findSlotByStartTimestamp($this->dayTimestamp('23:59:59'), self::TIMEZONE);

        $this->assertNotNull($slot);
        $this->assertEquals('23:59', $slot->getEnd());
    }

    public function testFindSlotByEndTimestampExpectsSlotWithinTheLastMinute(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);

        $slot = $schedule->findSlotByEndTimestamp($this->dayTimestamp('23:59:59'), self::TIMEZONE);

        $this->assertNotNull($slot);
        $this->assertEquals('23:59', $slot->getEnd());
    }

    public function testFindSlotByEndTimestampExpectsNullWithinTheEndMinuteOfARegularSlot(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '08:00', 'end' => '18:00']]);

        $this->assertNull($schedule->findSlotByEndTimestamp($this->dayTimestamp('18:00:30'), self::TIMEZONE));
    }

    public function testGetDayEndTimeTimestampExpectsConfiguredEndIsKept(): void
    {
        $slot = Slot::fromArray(['start' => '00:00', 'end' => '23:59']);

        $this->assertEquals(
            $this->dayTimestamp('23:59:00'),
            $slot->getDayEndTimeTimestamp($this->dayTimestamp('12:00:00'), self::TIMEZONE),
        );
    }

    public function testGetDaySlotsTimestampsExpectsConfiguredEndIsKept(): void
    {
        $schedule = $this->generateScheduleForOneDay([['start' => '00:00', 'end' => '23:59']]);
        $day = $schedule->getDays()->findDayForTime($this->dayDate());

        $this->assertNotNull($day);
        $times = $day->getSlots()->getDaySlotsTimestamps($this->dayDate());
        $this->assertEquals($this->dayTimestamp('23:59:00'), $times[0]['end']);
    }

    public function testGetDayLastSecondTimestampExpectsEndOfDayForSlotEndingAt2359(): void
    {
        $slot = Slot::fromArray(['start' => '00:00', 'end' => '23:59']);

        $this->assertTrue($slot->endsAtEndOfDay());
        $this->assertEquals(
            $this->dayTimestamp('23:59:59'),
            $slot->getDayLastSecondTimestamp($this->dayTimestamp('12:00:00'), self::TIMEZONE),
        );
        $this->assertEquals(
            $this->dayDate()->addDay()->getTimestamp(),
            $slot->getDayClosingTimestamp($this->dayTimestamp('12:00:00'), self::TIMEZONE),
        );
    }

    public function testGetDayLastSecondTimestampExpectsConfiguredEndForRegularSlot(): void
    {
        $slot = Slot::fromArray(['start' => '08:00', 'end' => '18:00']);

        $this->assertFalse($slot->endsAtEndOfDay());
        $this->assertEquals(
            $this->dayTimestamp('18:00:00'),
            $slot->getDayLastSecondTimestamp($this->dayTimestamp('12:00:00'), self::TIMEZONE),
        );
        $this->assertEquals(
            $this->dayTimestamp('18:00:00'),
            $slot->getDayClosingTimestamp($this->dayTimestamp('12:00:00'), self::TIMEZONE),
        );
    }

    private function generateAlwaysOnSchedule(): WorkTimeSchedule
    {
        $days = [];
        for ($dayId = 0; $dayId < 7; $dayId++) {
            $days[$dayId] = [
                'id' => $dayId,
                'status' => Day::STATUS_ACTIVE,
                'slots' => [
                    ['start' => '00:00', 'end' => '23:59'],
                ],
            ];
        }

        return WorkTimeGenerator::getInactiveWorkTime($days);
    }

    private function generateScheduleForOneDay(array $slots): WorkTimeSchedule
    {
        return $this->generateScheduleForDay($this->dayDate(), $slots);
    }

    private function generateScheduleForDay(Carbon $day, array $slots): WorkTimeSchedule
    {
        $dayId = $day->dayOfWeekIso - 1;

        return WorkTimeGenerator::getInactiveWorkTime([
            $dayId => [
                'id' => $dayId,
                'status' => Day::STATUS_ACTIVE,
                'slots' => $slots,
            ],
        ]);
    }

    private function dayTimestamp(string $time): int
    {
        return $this->dayDate()->setTimeFromTimeString($time)->getTimestamp();
    }

    private function dayDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m-d H:i:s', '2023-07-13 00:00:00', self::TIMEZONE);
    }
}
