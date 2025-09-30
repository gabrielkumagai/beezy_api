<?php

namespace App\Controller;

use App\Entity\User;
use App\Dto\UserDto;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class ApiUserController extends AbstractController
{
    #[Route('/api/user/create', name: 'api_user_create', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $em, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'JSON inválido: ' . json_last_error_msg()], 400);
        }

        $userDto = new UserDto();
        $userDto->nome = $data['nome'] ?? null;
        $userDto->telefone = $data['telefone'] ?? null;
        $userDto->email = $data['email'] ?? null;
        $userDto->senha = $data['senha'] ?? null;
        $userDto->cpf = $data['cpf'] ?? null;
        $userDto->dataNascimento = isset($data['dataNascimento']) ? new \DateTimeImmutable($data['dataNascimento']) : null;
        $userDto->imagemBase64 = $data['imagem'] ?? null;

        if (!$userDto->email || !$userDto->senha) {
            return new JsonResponse(['error' => 'E-mail e senha são obrigatórios.'], 400);
        }

        if ($userRepository->findOneBy(['email' => $userDto->email])) {
            return new JsonResponse(['error' => 'E-mail já cadastrado.'], 409);
        }

        $user = new User();
        $user->setNome($userDto->nome);
        $user->setTelefone($userDto->telefone);
        $user->setEmail($userDto->email);
        $user->setSenha($passwordHasher->hashPassword($user, $userDto->senha));
        $user->setCpf($userDto->cpf);
        $user->setDataNascimento($userDto->dataNascimento);

        if ($userDto->imagemBase64) {
            $user->setImagem(base64_decode($userDto->imagemBase64));
        }

        // A entidade User já inicializa o timestamp no construtor
        // $user->setTimestamp(new DateTimeImmutable());

        try {
            $em->persist($user);
            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['error' => 'Já existe um usuário com este e-mail.'], 409);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Ocorreu um erro inesperado: ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'message' => 'Usuário criado com sucesso',
            'id' => $user->getId()
        ], 201);
    }

    #[Route('/api/user/get/{id}', name: 'api_user_get', methods: ['GET'])]
    public function findUser(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        return new JsonResponse($this->formatUserData($user));
    }

    #[Route('/api/user/update/{id}', name: 'api_user_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request, EntityManagerInterface $em, UserRepository $userRepository, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'JSON inválido: ' . json_last_error_msg()], 400);
        }

        try {
            if (isset($data['nome'])) {
                $user->setNome($data['nome']);
            }
            if (isset($data['telefone'])) {
                $user->setTelefone($data['telefone']);
            }
            if (isset($data['email'])) {
                $user->setEmail($data['email']);
            }
            if (isset($data['senha'])) {
                $user->setSenha($passwordHasher->hashPassword($user, $data['senha']));
            }
            if (isset($data['cpf'])) {
                $user->setCpf($data['cpf']);
            }
            if (isset($data['dataNascimento'])) {
                $user->setDataNascimento($data['dataNascimento'] ? new \DateTimeImmutable($data['dataNascimento']) : null);
            }
            if (isset($data['imagem'])) {
                if ($data['imagem'] === null) {
                    $user->setImagem(null);
                } else {
                    $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $data['imagem']);
                    $imageData = base64_decode($base64Data);
                    if ($imageData !== false) {
                        $user->setImagem($imageData);
                    }
                }
            }

            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
            return new JsonResponse(['error' => 'Já existe um usuário com este e-mail.'], 409);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Ocorreu um erro inesperado: ' . $e->getMessage()], 500);
        }

        return new JsonResponse([
            'message' => 'Usuário atualizado com sucesso'
        ]);
    }

    #[Route('/api/user/delete/{id}', name: 'api_user_delete', methods: ['DELETE'])]
    public function deleteUser(int $id, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['message' => 'Usuário deletado com sucesso']);
    }

    #[Route('/api/user/all', name: 'api_user_all', methods: ['GET'])]
    public function getAllUsers(UserRepository $userRepository): JsonResponse
    {
        $users = $userRepository->findAll();
        $data = array_map(fn(User $user) => $this->formatUserData($user), $users);

        return new JsonResponse($data);
    }

    private function formatUserData(User $user): array
    {
        $imageData = null;
        $image = $user->getImagem();
        if ($image !== null) {
            if (is_resource($image)) {
                $image = stream_get_contents($image);
            }
            $imageData = base64_encode($image);
        }

        return [
            'id' => $user->getId(),
            'nome' => $user->getNome(),
            'telefone' => $user->getTelefone(),
            'email' => $user->getEmail(),
            'cpf' => $user->getCpf(),
            'dataNascimento' => $user->getDataNascimento() ? $user->getDataNascimento()->format('Y-m-d') : null,
            'imagem' => $imageData,
        ];
    }
}
