<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\DataPointRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DataPointRepository::class)]
#[ORM\Index(columns: ['timestamp'], name: 'idx_timestamp')]
#[ORM\Index(columns: ['pid_name'], name: 'idx_pid_name')]
#[ApiResource]
class DataPoint
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'dataPoints')]
    #[ORM\JoinColumn(nullable: false)]
    private ?LogSession $logSession = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $timestamp = null;

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

    public function getLogSession(): ?LogSession
    {
        return $this->logSession;
    }

    public function setLogSession(?LogSession $logSession): static
    {
        $this->logSession = $logSession;

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
