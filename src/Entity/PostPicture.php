<?php

namespace App\Entity;

use App\Repository\PostPictureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostPictureRepository::class)]
#[ORM\Table(name: 'post_picture')]
class PostPicture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pictures')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Post $post = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $base64Data = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }

    public function getBase64Data(): ?string
    {
        return $this->base64Data;
    }

    public function setBase64Data(?string $base64Data): static
    {
        $this->base64Data = $base64Data;

        return $this;
    }
}
