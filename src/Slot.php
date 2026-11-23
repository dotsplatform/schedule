<?php
/**
 * Description of SlotTime.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Oleksandr Polosmak <o.polosmak@dotsplatform.com>
 */

namespace Dots;

use Carbon\Carbon;
use Dots\Data\DTO;
use Dots\Exceptions\InvalidSlotTimeReceived;

class Slot extends DTO
{
    public const int STATUS_ACTIVE = 1;
    public const int STATUS_INACTIVE = 0;

    /**
     * Slots hold only HH:MM, so 23:59 is the latest end a slot can have. It means "until the end of the day"
     * ("always on" work time is 00:00-23:59, an entered 24:00 is saved as 23:59).
     */
    public const string END_OF_DAY_TIME = '23:59';

    protected string $start;
    protected string $end;
    protected int $status = self::STATUS_ACTIVE;
    protected ?int $capacity = null;

    protected function assertConstructDataIsValid(array $data): void
    {
        $startResult = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data['start'] ?? null);
        $endResult = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data['end'] ?? null);
        if (!$startResult || !$endResult) {
            throw new InvalidSlotTimeReceived('Invalid time received');
        }

        parent::assertConstructDataIsValid($data);
    }

    public function isOvernight(): bool
    {
        return $this->getEnd() < $this->getStart();
    }

    public function endsAtEndOfDay(): bool
    {
        return $this->getEnd() === self::END_OF_DAY_TIME;
    }

    public function containsTimestamp(int $timestamp, int $dayReferenceTimestamp, string $timezone): bool
    {
        $start = $this->getDayStartTimeTimestamp($dayReferenceTimestamp, $timezone);
        if ($timestamp < $start) {
            return false;
        }

        $lastSecond = $this->getDayLastSecondTimestamp($dayReferenceTimestamp, $timezone);

        return $timestamp <= $lastSecond;
    }

    public function getDayStartTimeTimestamp(int $timestamp, string $timezone): int
    {
        $day = Carbon::createFromTimestamp($timestamp, $timezone);
        return $day->startOfDay()
            ->setTimeFromTimeString($this->getStart())
            ->getTimestamp();
    }

    public function getDayEndTimeTimestamp(int $timestamp, string $timezone): int
    {
        $end = Carbon::createFromTimestamp($timestamp, $timezone)
            ->startOfDay()
            ->setTimeFromTimeString($this->getEnd());
        if ($this->isOvernight()) {
            $end->addDay();
        }

        return $end->getTimestamp();
    }

    /**
     * The moment the slot is over (exclusive bound): the configured end, or the next midnight for a slot that
     * ends at 23:59. Use it for "still before the end" checks; getDayEndTimeTimestamp() stays the configured end
     * that is shown to users and used as an order deadline.
     */
    public function getDayClosingTimestamp(int $timestamp, string $timezone): int
    {
        if ($this->endsAtEndOfDay()) {
            return $this->getNextMidnightTimestamp($timestamp, $timezone);
        }

        return $this->getDayEndTimeTimestamp($timestamp, $timezone);
    }

    /**
     * The last second that still belongs to the slot (inclusive bound). The configured end itself belongs to the
     * slot (18:00:00 fits a slot until 18:00, 18:00:30 does not); a slot that ends at 23:59 lasts until 23:59:59.
     */
    public function getDayLastSecondTimestamp(int $timestamp, string $timezone): int
    {
        if ($this->endsAtEndOfDay()) {
            return $this->getNextMidnightTimestamp($timestamp, $timezone) - 1;
        }

        return $this->getDayEndTimeTimestamp($timestamp, $timezone);
    }

    public function getStartTimestamp(int $timestamp, string $timezone): int
    {
        return Carbon::createFromTimestamp($timestamp, $timezone)
            ->setTimeFromTimeString($this->getStart())
            ->getTimestamp();
    }

    public function getEndTimestamp(int $timestamp, string $timezone): int
    {
        $end = Carbon::createFromTimestamp($timestamp, $timezone)
            ->setTimeFromTimeString($this->getEnd());
        if ($this->isOvernight()) {
            $end->addDay();
        }

        return $end->getTimestamp();
    }

    private function getNextMidnightTimestamp(int $timestamp, string $timezone): int
    {
        return Carbon::createFromTimestamp($timestamp, $timezone)
            ->startOfDay()
            ->addDay()
            ->getTimestamp();
    }

    public function getStartHours(): int
    {
        return (int)explode(':', $this->getStart())[0];
    }

    public function getStartMinutes(): int
    {
        return (int)explode(':', $this->getStart())[1];
    }

    public function getEndHours(): int
    {
        return (int)explode(':', $this->getEnd())[0];
    }

    public function getEndMinutes(): int
    {
        return (int)explode(':', $this->getEnd())[1];
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }
}
