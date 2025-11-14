<?php

namespace App\Entity;

use App\Repository\FollowRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FollowRepository::class)]
#[ORM\Table(name: '`follow`')]
#[ORM\UniqueConstraint(name: 'follow_unique', columns: ['follower_id', 'following_id'])]
class Follow
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // O usuário que está a seguir
    #[ORM\ManyToOne(inversedBy: 'following')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $follower = null;

    // O usuário que está a ser seguido
    #[ORM\ManyToOne(inversedBy: 'followers')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $following = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFollower(): ?User
    {
        return $this->follower;
    }

    public function setFollower(?User $follower): static
    {
        $this->follower = $follower;

        return $this;
    }

    public function getFollowing(): ?User
    {
        return $this->following;
    }

    public function setFollowing(?User $following): static
    {
        $this->following = $following;

        return $this;
    }
}
