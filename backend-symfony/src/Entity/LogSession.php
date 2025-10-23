<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\LogSessionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LogSessionRepository::class)]
#[ApiResource]
class LogSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'logSessions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Vehicle $vehicle = null;

    #[ORM\Column(length: 255)]
    private ?string $filename = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filePath = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $sessionDate = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $duration = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: true)]
    private ?string $distance = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $dataPointsCount = null;

    #[ORM\Column(length: 20)]
    private ?string $status = 'pending';

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $analysisResults = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $catalystEfficiency = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $avgFuelTrimST = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $avgFuelTrimLT = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $uploadedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $analyzedAt = null;

    /**
     * @var Collection<int, DataPoint>
     */
    #[ORM\OneToMany(targetEntity: DataPoint::class, mappedBy: 'logSession', orphanRemoval: true)]
    private Collection $dataPoints;

    public function __construct()
    {
        $this->dataPoints = new ArrayCollection();
        $this->uploadedAt = new \DateTimeImmutable();
        $this->status = 'pending';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVehicle(): ?Vehicle
    {
        return $this->vehicle;
    }

    public function setVehicle(?Vehicle $vehicle): static
    {
        $this->vehicle = $vehicle;

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(string $filename): static
    {
        $this->filename = $filename;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getSessionDate(): ?\DateTimeImmutable
    {
        return $this->sessionDate;
    }

    public function setSessionDate(\DateTimeImmutable $sessionDate): static
    {
        $this->sessionDate = $sessionDate;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getDistance(): ?string
    {
        return $this->distance;
    }

    public function setDistance(?string $distance): static
    {
        $this->distance = $distance;

        return $this;
    }

    public function getDataPointsCount(): ?int
    {
        return $this->dataPointsCount;
    }

    public function setDataPointsCount(?int $dataPointsCount): static
    {
        $this->dataPointsCount = $dataPointsCount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getAnalysisResults(): ?array
    {
        return $this->analysisResults;
    }

    public function setAnalysisResults(?array $analysisResults): static
    {
        $this->analysisResults = $analysisResults;

        return $this;
    }

    public function getCatalystEfficiency(): ?string
    {
        return $this->catalystEfficiency;
    }

    public function setCatalystEfficiency(?string $catalystEfficiency): static
    {
        $this->catalystEfficiency = $catalystEfficiency;

        return $this;
    }

    public function getAvgFuelTrimST(): ?string
    {
        return $this->avgFuelTrimST;
    }

    public function setAvgFuelTrimST(?string $avgFuelTrimST): static
    {
        $this->avgFuelTrimST = $avgFuelTrimST;

        return $this;
    }

    public function getAvgFuelTrimLT(): ?string
    {
        return $this->avgFuelTrimLT;
    }

    public function setAvgFuelTrimLT(?string $avgFuelTrimLT): static
    {
        $this->avgFuelTrimLT = $avgFuelTrimLT;

        return $this;
    }

    public function getUploadedAt(): ?\DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): static
    {
        $this->uploadedAt = $uploadedAt;

        return $this;
    }

    public function getAnalyzedAt(): ?\DateTimeImmutable
    {
        return $this->analyzedAt;
    }

    public function setAnalyzedAt(?\DateTimeImmutable $analyzedAt): static
    {
        $this->analyzedAt = $analyzedAt;

        return $this;
    }

    /**
     * @return Collection<int, DataPoint>
     */
    public function getDataPoints(): Collection
    {
        return $this->dataPoints;
    }

    public function addDataPoint(DataPoint $dataPoint): static
    {
        if (!$this->dataPoints->contains($dataPoint)) {
            $this->dataPoints->add($dataPoint);
            $dataPoint->setLogSession($this);
        }

        return $this;
    }

    public function removeDataPoint(DataPoint $dataPoint): static
    {
        if ($this->dataPoints->removeElement($dataPoint)) {
            if ($dataPoint->getLogSession() === $this) {
                $dataPoint->setLogSession(null);
            }
        }

        return $this;
    }
}
