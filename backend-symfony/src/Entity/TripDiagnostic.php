<?php

namespace App\Entity;

use App\Repository\TripDiagnosticRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TripDiagnosticRepository::class)]
#[ORM\Table(name: 'trip_diagnostic')]
class TripDiagnostic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Trip::class, inversedBy: 'diagnostics')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Trip $trip = null;

    /**
     * Diagnostic category: engine, hybrid, catalyst, o2_sensors, fuel_system, etc.
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $category;

    /**
     * Diagnostic result: ok, warning, critical
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $status;

    /**
     * Health score for this category (0-100)
     */
    #[ORM\Column(type: Types::INTEGER)]
    private int $score = 0;

    /**
     * Confidence level: low, medium, high
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $confidence = 'medium';

    /**
     * Diagnostic details and measurements
     * Format: {
     *   "catalyst_efficiency": 85.5,
     *   "o2_amont_variation": 0.025,
     *   "o2_aval_stability": 0.08,
     *   "measurements": {...}
     * }
     */
    #[ORM\Column(type: Types::JSON)]
    private array $details = [];

    /**
     * Human-readable messages
     */
    #[ORM\Column(type: Types::JSON)]
    private array $messages = [];

    /**
     * Recommended actions
     */
    #[ORM\Column(type: Types::JSON)]
    private array $recommendations = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // ===== Getters & Setters =====

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTrip(): ?Trip
    {
        return $this->trip;
    }

    public function setTrip(?Trip $trip): self
    {
        $this->trip = $trip;
        return $this;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getScore(): int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getConfidence(): string
    {
        return $this->confidence;
    }

    public function setConfidence(string $confidence): self
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function getDetails(): array
    {
        return $this->details;
    }

    public function setDetails(array $details): self
    {
        $this->details = $details;
        return $this;
    }

    public function getMessages(): array
    {
        return $this->messages;
    }

    public function setMessages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    public function getRecommendations(): array
    {
        return $this->recommendations;
    }

    public function setRecommendations(array $recommendations): self
    {
        $this->recommendations = $recommendations;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
