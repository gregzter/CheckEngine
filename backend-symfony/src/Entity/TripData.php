<?php

namespace App\Entity;

use App\Repository\TripDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * TimescaleDB hypertable for OBD2 time-series data
 * Composite primary key: (id, timestamp) required for partitioning
 */
#[ORM\Entity(repositoryClass: TripDataRepository::class)]
#[ORM\Index(columns: ['trip_id', 'timestamp'], name: 'idx_trip_timestamp')]
#[ORM\Index(columns: ['pid_name', 'timestamp'], name: 'idx_pid_timestamp')]
class TripData
{
    #[ORM\Id]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Id]
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $timestamp = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $trip_id = null;

    #[ORM\Column(length: 50)]
    private ?string $pidName = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 4, nullable: true)]
    private ?string $value = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTripId(): ?int
    {
        return $this->trip_id;
    }

    public function setTripId(int $trip_id): static
    {
        $this->trip_id = $trip_id;
        return $this;
    }

    public function getTimestamp(): ?\DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function setTimestamp(\DateTimeImmutable $timestamp): static
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    public function getPidName(): ?string
    {
        return $this->pidName;
    }

    public function setPidName(string $pidName): static
    {
        $this->pidName = $pidName;

        return $this;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(?string $value): static
    {
        $this->value = $value;

        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): static
    {
        $this->unit = $unit;

        return $this;
    }
}
