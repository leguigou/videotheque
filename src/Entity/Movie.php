<?php
/**
 * @category  Videotheque
 * @author    Guillaume Deloffre <guillaume.deloffre@gmail.com>
 * @date      2024
 */

namespace App\Entity;

use App\Repository\MovieRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MovieRepository::class)]
class Movie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column]
    private ?int $duration = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $poster = null;

    #[ORM\ManyToMany(targetEntity: "App\Entity\People", inversedBy: "movies")]
    #[ORM\JoinTable(
        name: "movie_has_people",
        joinColumns: [new ORM\JoinColumn(name: "movie_id", referencedColumnName: "id")],
        inverseJoinColumns: [
            new ORM\JoinColumn(name: "people_id", referencedColumnName: "id")
        ]
    )]
    private $people;

    #[ORM\ManyToMany(targetEntity: "App\Entity\Type", inversedBy: "movies")]
    #[ORM\JoinTable(
        name: "movie_has_type",
        joinColumns: [new ORM\JoinColumn(name: "movie_id", referencedColumnName: "id")],
        inverseJoinColumns: [new ORM\JoinColumn(name: "type_id", referencedColumnName: "id")]
    )]
    private $type;

    public function __construct()
    {
        $this->people = new ArrayCollection();
        $this->type = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): static
    {
        $this->duration = $duration;

        return $this;
    }

    public function getPoster(): ?string
    {
        return $this->poster;
    }

    public function setPoster(string $poster): static
    {
        $this->poster = $poster;

        return $this;
    }

    public function getPeople(): Collection
    {
        return $this->people;
    }

    public function getType(): Collection
    {
        return $this->type;
    }
}
