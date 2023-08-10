<?php
/**
 * Description of SlotDay.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Oleksandr Polosmak <o.polosmak@dotsplatform.com>
 */

namespace Dots;

use Carbon\Carbon;
use Dots\Data\DTO;

class Day extends DTO
{
    public const STATUS_ACTIVE = 1;
    public const STATUS_INACTIVE = 0;

    protected int $id;
    protected int $status;
    protected Slots $slots;

    public static function fromArray(array $data): static
    {
        $data['slots'] = Slots::fromArray($data['slots'] ?? []);
        return parent::fromArray($data);
    }

    public function getNearestSlots(Carbon $dayDate, int $timestamp, string $timezone): Slots
    {
        if ($this->isSameDay($dayDate, $timestamp, $timezone)) {
            return $this->getSlots()->getNearestSlots($timestamp, $timezone);
        }

        return $this->getSlots();
    }

    public function isSameDay(Carbon $dayDate, int $timestamp, string $timezone): bool
    {
        $time = Carbon::createFromTimestamp($timestamp, $timezone);
        if (!$dayDate->isSameDay($time)) {
            return false;
        }
        return $time->dayOfWeekIso - 1 === $this->getId();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSlots(): Slots
    {
        return $this->slots;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->getStatus() === self::STATUS_ACTIVE;
    }
}
