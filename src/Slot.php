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
    protected string $start;
    protected string $end;

    protected function assertConstructDataIsValid(array $data): void
    {
        $startResult = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data['start'] ?? null);
        $endResult = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $data['end'] ?? null);
        if (!$startResult || !$endResult) {
            throw new InvalidSlotTimeReceived('Invalid time received');
        }

        parent::assertConstructDataIsValid($data);
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
        $day = Carbon::createFromTimestamp($timestamp, $timezone);
        return $day->startOfDay()
            ->setTimeFromTimeString($this->getEnd())
            ->getTimestamp();
    }

    public function getStart(): string
    {
        return $this->start;
    }

    public function getEnd(): string
    {
        return $this->end;
    }

    public function getStartTimestamp(int $timestamp, string $timezone): int
    {
        return Carbon::createFromTimestamp($timestamp, $timezone)
            ->setTimeFromTimeString($this->getStart())
            ->getTimestamp();
    }

    public function getEndTimestamp(int $timestamp, string $timezone): int
    {
        return Carbon::createFromTimestamp($timestamp, $timezone)
            ->setTimeFromTimeString($this->getEnd())
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
}
