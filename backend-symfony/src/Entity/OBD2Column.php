<?php

namespace App\Entity;

use App\Repository\OBD2ColumnRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente une colonne OBD2 normalisée (nom standard en BDD)
 */
#[ORM\Entity(repositoryClass: OBD2ColumnRepository::class)]
#[ORM\Table(name: 'obd2_column')]
class OBD2Column
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom normalisé de la colonne (ex: engine_rpm, coolant_temp)
     */
    #[ORM\Column(length: 100, unique: true)]
    private ?string $name = null;

    /**
     * Description de la donnée
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    /**
     * Catégorie (temporel, gps, lambda, temperature, engine, etc.)
     */
    #[ORM\Column(length: 50)]
    private ?string $category = null;

    /**
     * Unité de mesure (°C, rpm, km/h, V, %, etc.)
     */
    #[ORM\Column(length: 20, nullable: true)]
    private ?string $unit = null;

    /**
     * Type de données (float, int, string, datetime)
     */
    #[ORM\Column(length: 20)]
    private ?string $dataType = null;

    /**
     * Valeur minimale attendue (pour validation)
     */
    #[ORM\Column(nullable: true)]
    private ?float $minValue = null;

    /**
     * Valeur maximale attendue (pour validation)
     */
    #[ORM\Column(nullable: true)]
    private ?float $maxValue = null;

    /**
     * Valeurs d'erreur connues (JSON array)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorValues = null;

    /**
     * Critères de validation spécifiques (JSON)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $validationCriteria = null;

    /**
     * Actif ou non
     */
    #[ORM\Column]
    private ?bool $active = true;

    /**
     * Variantes de noms pour cette colonne
     */
    #[ORM\OneToMany(targetEntity: OBD2ColumnVariant::class, mappedBy: 'column', cascade: ['persist', 'remove'])]
    private Collection $variants;

    public function __construct()
    {
        $this->variants = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
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

    public function getDataType(): ?string
    {
        return $this->dataType;
    }

    public function setDataType(string $dataType): static
    {
        $this->dataType = $dataType;
        return $this;
    }

    public function getMinValue(): ?float
    {
        return $this->minValue;
    }

    public function setMinValue(?float $minValue): static
    {
        $this->minValue = $minValue;
        return $this;
    }

    public function getMaxValue(): ?float
    {
        return $this->maxValue;
    }

    public function setMaxValue(?float $maxValue): static
    {
        $this->maxValue = $maxValue;
        return $this;
    }

    public function getErrorValues(): ?array
    {
        return $this->errorValues;
    }

    public function setErrorValues(?array $errorValues): static
    {
        $this->errorValues = $errorValues;
        return $this;
    }

    public function getValidationCriteria(): ?array
    {
        return $this->validationCriteria;
    }

    public function setValidationCriteria(?array $validationCriteria): static
    {
        $this->validationCriteria = $validationCriteria;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    /**
     * @return Collection<int, OBD2ColumnVariant>
     */
    public function getVariants(): Collection
    {
        return $this->variants;
    }

    public function addVariant(OBD2ColumnVariant $variant): static
    {
        if (!$this->variants->contains($variant)) {
            $this->variants->add($variant);
            $variant->setColumn($this);
        }

        return $this;
    }

    public function removeVariant(OBD2ColumnVariant $variant): static
    {
        if ($this->variants->removeElement($variant)) {
            if ($variant->getColumn() === $this) {
                $variant->setColumn(null);
            }
        }

        return $this;
    }
}
