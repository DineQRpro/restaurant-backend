<?php

namespace App\Services\Table;

use App\Enums\TableOperationalStatus;

readonly class TableOccupancy
{
    public function __construct(
        public int $capacity,
        public int $occupiedSeats,
        public int $cleaningSeats,
        public int $availableSeats,
        public TableOperationalStatus $status,
    ) {}

    /**
     * @return array{occupied_seats: int, cleaning_seats: int, available_seats: int}
     */
    public function toArray(): array
    {
        return [
            'occupied_seats' => $this->occupiedSeats,
            'cleaning_seats' => $this->cleaningSeats,
            'available_seats' => $this->availableSeats,
        ];
    }

    public function allowsNewSeating(): bool
    {
        if ($this->occupiedSeats === 0 && $this->cleaningSeats > 0) {
            return false;
        }

        return $this->availableSeats > 0;
    }

    public function isFullyFree(): bool
    {
        return $this->occupiedSeats === 0 && $this->cleaningSeats === 0;
    }
}
