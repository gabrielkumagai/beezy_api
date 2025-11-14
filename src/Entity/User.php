<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nome = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $telefone = null;

    #[ORM\Column(length: 100, nullable: false, unique: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $senha = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cpf = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTime $dataNascimento = null;

    #[ORM\Column(type: Types::BLOB, nullable: true)]
    private $imagem = null;

    #[ORM\OneToMany(targetEntity: PostLike::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $likedPosts;

    #[ORM\OneToMany(targetEntity: CommentLike::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $likedComments;

    #[ORM\OneToMany(targetEntity: Friendship::class, mappedBy: 'sender', orphanRemoval: true)]
    private Collection $sentFriendships;

    #[ORM\OneToMany(targetEntity: Friendship::class, mappedBy: 'receiver', orphanRemoval: true)]
    private Collection $receivedFriendships;

    #[ORM\ManyToMany(targetEntity: Chat::class, mappedBy: 'users')]
    private Collection $chats;

    // Novas propriedades para o sistema de avaliação
    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'rater', orphanRemoval: true)]
    private Collection $givenRatings;

    #[ORM\OneToMany(targetEntity: Rating::class, mappedBy: 'ratedUser', orphanRemoval: true)]
    private Collection $receivedRatings;

    // --- INÍCIO DAS NOVAS PROPRIEDADES (FOLLOW) ---
    #[ORM\OneToMany(mappedBy: 'follower', targetEntity: Follow::class, orphanRemoval: true)]
    private Collection $following; // Quem este usuário segue

    #[ORM\OneToMany(mappedBy: 'following', targetEntity: Follow::class, orphanRemoval: true)]
    private Collection $followers; // Quem segue este usuário
    // --- FIM DAS NOVAS PROPRIEDADES (FOLLOW) ---

    public function __construct()
    {
        $this->likedPosts = new ArrayCollection();
        $this->likedComments = new ArrayCollection();
        $this->sentFriendships = new ArrayCollection();
        $this->receivedFriendships = new ArrayCollection();
        $this->chats = new ArrayCollection();
        $this->givenRatings = new ArrayCollection();
        $this->receivedRatings = new ArrayCollection();

        // --- ADICIONAR AO CONSTRUTOR (FOLLOW) ---
        $this->following = new ArrayCollection();
        $this->followers = new ArrayCollection();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNome(): ?string
    {
        return $this->nome;
    }

    public function setNome(?string $nome): static
    {
        $this->nome = $nome;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getTelefone(): ?string
    {
        return $this->telefone;
    }

    public function setTelefone(?string $telefone): static
    {
        $this->telefone = $telefone;
        return $this;
    }

    public function getSenha(): ?string
    {
        return $this->senha;
    }

    public function setSenha(?string $senha): static
    {
        $this->senha = $senha;
        return $this;
    }



    public function getCpf(): ?string
    {
        return $this->cpf;
    }

    public function setCpf(?string $cpf): static
    {
        $this->cpf = $cpf;
        return $this;
    }

    public function getDataNascimento(): ?\DateTime
    {
        return $this->dataNascimento;
    }

    public function setDataNascimento(?\DateTime $dataNascimento): static
    {
        $this->dataNascimento = $dataNascimento;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->getSenha();
    }

    public function getImagem(): mixed
    {
        return $this->imagem;
    }

    public function setImagem(mixed $imagem): static
    {
        $this->imagem = $imagem;
        return $this;
    }

    /**
     * @return Collection<int, PostLike>
     */
    public function getLikedPosts(): Collection
    {
        return $this->likedPosts;
    }

    /**
     * @return Collection<int, CommentLike>
     */
    public function getLikedComments(): Collection
    {
        return $this->likedComments;
    }

    /**
     * @return Collection<int, Friendship>
     */
    public function getSentFriendships(): Collection
    {
        return $this->sentFriendships;
    }

    /**
     * @return Collection<int, Friendship>
     */
    public function getReceivedFriendships(): Collection
    {
        return $this->receivedFriendships;
    }

    /**
     * @return Collection<int, Chat>
     */
    public function getChats(): Collection
    {
        return $this->chats;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getGivenRatings(): Collection
    {
        return $this->givenRatings;
    }

    /**
     * @return Collection<int, Rating>
     */
    public function getReceivedRatings(): Collection
    {
        return $this->receivedRatings;
    }

    // --- INÍCIO DOS NOVOS MÉTODOS (FOLLOW) ---

    /**
     * @return Collection<int, Follow>
     */
    public function getFollowing(): Collection
    {
        return $this->following;
    }

    public function addFollowing(Follow $follow): static
    {
        if (!$this->following->contains($follow)) {
            $this->following->add($follow);
            $follow->setFollower($this);
        }

        return $this;
    }

    public function removeFollowing(Follow $follow): static
    {
        if ($this->following->removeElement($follow)) {
            // set the owning side to null (unless already changed)
            if ($follow->getFollower() === $this) {
                $follow->setFollower(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Follow>
     */
    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function addFollower(Follow $follow): static
    {
        if (!$this->followers->contains($follow)) {
            $this->followers->add($follow);
            $follow->setFollowing($this);
        }

        return $this;
    }

    public function removeFollower(Follow $follow): static
    {
        if ($this->followers->removeElement($follow)) {
            // set the owning side to null (unless already changed)
            if ($follow->getFollowing() === $this) {
                $follow->setFollowing(null);
            }
        }

        return $this;
    }
    // --- FIM DOS NOVOS MÉTODOS (FOLLOW) ---
}
