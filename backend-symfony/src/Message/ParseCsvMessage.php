<?php

namespace App\Message;

/**
 * Message for async CSV parsing
 */
class ParseCsvMessage
{
    public function __construct(
        private readonly string $filename,
        private readonly int $userId,
        private readonly ?int $vehicleId = null
    ) {}

    public function getFilename(): string
    {
        return $this->filename;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getVehicleId(): ?int
    {
        return $this->vehicleId;
    }
}
