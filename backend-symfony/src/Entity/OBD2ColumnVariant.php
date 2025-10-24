<?php

namespace App\Entity;

use App\Repository\OBD2ColumnVariantRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Représente une variante de nom pour une colonne OBD2
 * (ex: "Engine RPM(rpm)", "RPM", "Engine Speed(rpm)" -> toutes mappées vers "engine_rpm")
 */
#[ORM\Entity(repositoryClass: OBD2ColumnVariantRepository::class)]
#[ORM\Table(name: 'obd2_column_variant')]
#[ORM\Index(columns: ['variant_name'], name: 'idx_variant_name')]
class OBD2ColumnVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Nom de la variante tel qu'il apparaît dans le CSV
     */
    #[ORM\Column(length: 255)]
    private ?string $variantName = null;

    /**
     * Priorité (0 = meilleure source, plus le chiffre est élevé, moins prioritaire)
     */
    #[ORM\Column]
    private ?int $priority = 0;

    /**
     * Colonne OBD2 associée
     */
    #[ORM\ManyToOne(targetEntity: OBD2Column::class, inversedBy: 'variants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?OBD2Column $column = null;

    /**
     * Source du mapping (torque_pro, elm327, obdlink, custom)
     */
    #[ORM\Column(length: 50)]
    private ?string $source = 'torque_pro';

    /**
     * Actif ou non
     */
    #[ORM\Column]
    private ?bool $active = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getVariantName(): ?string
    {
        return $this->variantName;
    }

    public function setVariantName(string $variantName): static
    {
        $this->variantName = $variantName;
        return $this;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): static
    {
        $this->priority = $priority;
        return $this;
    }

    public function getColumn(): ?OBD2Column
    {
        return $this->column;
    }

    public function setColumn(?OBD2Column $column): static
    {
        $this->column = $column;
        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;
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
}
