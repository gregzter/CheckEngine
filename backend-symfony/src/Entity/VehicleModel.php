<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\VehicleModelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VehicleModelRepository::class)]
#[ApiResource]
class VehicleModel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $manufacturer = null;

    #[ORM\Column(length: 100)]
    private ?string $model = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $yearStart = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $yearEnd = null;

    #[ORM\Column(length: 50)]
    private ?string $generation = null;

    #[ORM\Column(length: 20)]
    private ?string $engineCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 1)]
    private ?string $displacement = null;

    #[ORM\Column(length: 50)]
    private ?string $fuelType = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private ?int $horsePower = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: true)]
    private ?int $electricMotorPower = null;

    #[ORM\Column]
    private ?bool $isHybrid = false;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $supportedPids = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $specifications = null;

    /**
     * @var Collection<int, Vehicle>
     */
    #[ORM\OneToMany(targetEntity: Vehicle::class, mappedBy: 'model')]
    private Collection $vehicles;

    public function __construct()
    {
        $this->vehicles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getManufacturer(): ?string
    {
        return $this->manufacturer;
    }

    public function setManufacturer(string $manufacturer): static
    {
        $this->manufacturer = $manufacturer;

        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;

        return $this;
    }

    public function getYearStart(): ?int
    {
        return $this->yearStart;
    }

    public function setYearStart(int $yearStart): static
    {
        $this->yearStart = $yearStart;

        return $this;
    }

    public function getYearEnd(): ?int
    {
        return $this->yearEnd;
    }

    public function setYearEnd(?int $yearEnd): static
    {
        $this->yearEnd = $yearEnd;

        return $this;
    }

    public function getGeneration(): ?string
    {
        return $this->generation;
    }

    public function setGeneration(string $generation): static
    {
        $this->generation = $generation;

        return $this;
    }

    public function getEngineCode(): ?string
    {
        return $this->engineCode;
    }

    public function setEngineCode(string $engineCode): static
    {
        $this->engineCode = $engineCode;

        return $this;
    }

    public function getDisplacement(): ?string
    {
        return $this->displacement;
    }

    public function setDisplacement(string $displacement): static
    {
        $this->displacement = $displacement;

        return $this;
    }

    public function getFuelType(): ?string
    {
        return $this->fuelType;
    }

    public function setFuelType(string $fuelType): static
    {
        $this->fuelType = $fuelType;

        return $this;
    }

    public function getHorsePower(): ?int
    {
        return $this->horsePower;
    }

    public function setHorsePower(int $horsePower): static
    {
        $this->horsePower = $horsePower;

        return $this;
    }

    public function getElectricMotorPower(): ?int
    {
        return $this->electricMotorPower;
    }

    public function setElectricMotorPower(?int $electricMotorPower): static
    {
        $this->electricMotorPower = $electricMotorPower;

        return $this;
    }

    public function isHybrid(): ?bool
    {
        return $this->isHybrid;
    }

    public function setHybrid(bool $isHybrid): static
    {
        $this->isHybrid = $isHybrid;

        return $this;
    }

    public function getSupportedPids(): ?array
    {
        return $this->supportedPids;
    }

    public function setSupportedPids(?array $supportedPids): static
    {
        $this->supportedPids = $supportedPids;

        return $this;
    }

    public function getSpecifications(): ?array
    {
        return $this->specifications;
    }

    public function setSpecifications(?array $specifications): static
    {
        $this->specifications = $specifications;

        return $this;
    }

    /**
     * @return Collection<int, Vehicle>
     */
    public function getVehicles(): Collection
    {
        return $this->vehicles;
    }

    public function addVehicle(Vehicle $vehicle): static
    {
        if (!$this->vehicles->contains($vehicle)) {
            $this->vehicles->add($vehicle);
            $vehicle->setModel($this);
        }

        return $this;
    }

    public function removeVehicle(Vehicle $vehicle): static
    {
        if ($this->vehicles->removeElement($vehicle)) {
            if ($vehicle->getModel() === $this) {
                $vehicle->setModel(null);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return sprintf('%s %s (%d-%s)', 
            $this->manufacturer, 
            $this->model, 
            $this->yearStart,
            $this->yearEnd ? $this->yearEnd : 'present'
        );
    }
}
