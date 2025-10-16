<?php

namespace App\Entity;

use App\Repository\RatingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ORM\Table(name: 'rating')]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'givenRatings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $rater = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'receivedRatings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $ratedUser = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $score = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRater(): ?User
    {
        return $this->rater;
    }

    public function setRater(?User $rater): self
    {
        $this->rater = $rater;
        return $this;
    }

    public function getRatedUser(): ?User
    {
        return $this->ratedUser;
    }

    public function setRatedUser(?User $ratedUser): self
    {
        $this->ratedUser = $ratedUser;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
