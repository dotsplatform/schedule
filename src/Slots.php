<?php
/**
 * Description of SlotTimes.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Oleksandr Polosmak <o.polosmak@dotsplatform.com>
 */

namespace Dots;

use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * @extends Collection<int, Slot>
 * @method Slot|null first(callable $callback = null, $default = null)
 * @method Slot|null last(callable $callback = null, $default = null)
 * @method Slot[] all()
 */
class Slots extends Collection
{
    public static function fromArray(array $data): static
    {
        $slots = array_map(
            fn (array $item) => Slot::fromArray($item),
            $data,
        );
        usort($slots, function (
            Slot $slot1,
            Slot $slot2,
        ) {
            return ($slot1->getStart() > $slot2->getStart()) ? 1 : -1;
        });

        return new static($slots);
    }

    public function findNearestSlot(int $timestamp, string $timezone): ?Slot
    {
        return $this->getNearestSlots($timestamp, $timezone)->first();
    }

    public function containsTimestamp(int $timestamp, int $dayReferenceTimestamp, string $timezone): bool
    {
        foreach ($this->all() as $slot) {
            if ($slot->containsTimestamp($timestamp, $dayReferenceTimestamp, $timezone)) {
                return true;
            }
        }

        return false;
    }

    public function getNearestSlots(int $timestamp, string $timezone): static
    {
        return $this->filter(
            fn (Slot $slot) => $slot->getDayStartTimeTimestamp($timestamp, $timezone) > $timestamp,
        );
    }

    public function getDaySlotsTimestamps(Carbon $day): array
    {
        $slotsTimestamps = $this->map(
            fn (Slot $slot) => [
                'start' => (clone $day)->setTimeFromTimeString($slot->getStart())->getTimestamp(),
                'end' => $this->resolveSlotEndTimestamp($day, $slot),
            ],
        )->toArray();

        return array_values($slotsTimestamps);
    }

    public function findSlotByEndTimestamp(int $timestamp, string $timezone): ?Slot
    {
        return $this->first(
            fn (Slot $slot) => $slot->getDayStartTimeTimestamp($timestamp, $timezone) < $timestamp
                && $slot->getDayEndTimeTimestamp($timestamp, $timezone) >= $timestamp,
        );
    }

    public function findSlotByStartTimestamp(int $timestamp, string $timezone): ?Slot
    {
        return $this->first(
            fn (Slot $slot) => $slot->getDayStartTimeTimestamp($timestamp, $timezone) <= $timestamp
                && $slot->getDayEndTimeTimestamp($timestamp, $timezone) > $timestamp,
        );
    }

    private function resolveSlotEndTimestamp(Carbon $day, Slot $slot): int
    {
        $end = (clone $day)->setTimeFromTimeString($slot->getEnd());
        if ($slot->isOvernight()) {
            $end->addDay();
        }

        return $end->getTimestamp();
    }
}
