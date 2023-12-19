<?php
/**
 * Description of SlotGenerator.php
 * @copyright Copyright (c) DOTSPLATFORM, LLC
 * @author    Mamontov Bogdan <bohdan.mamontov@dotsplatform.com>
 */

namespace Tests\Generators;

use Carbon\Carbon;
use Dots\Slot;

class SlotGenerator
{
    public const BASE_SLOT_START = '10:00';
    public const BASE_SLOT_END = '12:00';

    public static function generateEntities(int $count, Carbon $startTime, int $minutesInterval): array
    {
        $slots = [];
        for ($i = 0; $i < $count; $i++) {
            $slots[] = self::generate([
                'start' => $startTime->format('H:i'),
                'end' => (clone $startTime)->addMinutes($minutesInterval)->format('H:i'),
            ])->toArray();

            $startTime = $startTime->addMinutes($minutesInterval);
        }
        return $slots;
    }

    public static function generate(array $data = []): Slot
    {
        return Slot::fromArray(array_merge([
            'start' => self::BASE_SLOT_START,
            'end' => self::BASE_SLOT_END,
        ], $data));
    }
}
