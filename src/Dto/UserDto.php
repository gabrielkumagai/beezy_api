<?php
namespace App\Dto;

class UserDto
{
public string $nome;
public string $telefone;
public string $senha;
public string $rg;
public \DateTime $dataNascimento;

public static function fromArray(array $data): self
{
$dto = new self();
$dto->nome = $data['nome'] ?? '';
$dto->telefone = $data['telefone'] ?? '';
$dto->senha = $data['senha'] ?? '';
$dto->rg = $data['rg'] ?? '';
$dto->dataNascimento = new \DateTime($data['dataNascimento'] ?? 'now');

return $dto;
}
}
