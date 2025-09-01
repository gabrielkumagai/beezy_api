<?php
namespace App\Dto;

class UserDto
{
    public ?string $nome = null;
    public ?string $telefone = null;
    public ?string $email = null;
    public ?string $senha = null;
    public ?string $cpf = null;
    public ?\DateTime $dataNascimento = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();

        $dto->nome = !empty($data['nome']) ? $data['nome'] : null;
        $dto->telefone = !empty($data['telefone']) ? $data['telefone'] : null;
        $dto->email = !empty($data['email']) ? $data['email'] : null;
        $dto->senha = !empty($data['senha']) ? $data['senha'] : null;
        $dto->cpf = !empty($data['cpf']) ? $data['cpf'] : null;
        $dto->dataNascimento = !empty($data['dataNascimento'])
            ? new \DateTime($data['dataNascimento'])
            : null;

        return $dto;
    }
}
