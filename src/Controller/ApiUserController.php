<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use App\Dto\UserDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class ApiUserController extends AbstractController
{
    #[Route('/api/user/create', name: 'app_api_user_create', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'JSON inválido.'], 400);
        }

        try {
            $dto = UserDto::fromArray($data);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Dados inválidos: ' . $e->getMessage()], 400);
        }

        $user = new User();
        $user->setNome($dto->nome);
        $user->setTelefone($dto->telefone);
        $user->setEmail($dto->email);
        $user->setSenha($passwordHasher->hashPassword($user, $dto->senha));
        $user->setCpf($dto->cpf);
        $user->setDataNascimento($dto->dataNascimento);

        if ($dto->imagemBase64) {
            $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $dto->imagemBase64);
            $imageData = base64_decode($base64Data);
            if ($imageData !== false) {
                $user->setImagem($imageData);
            }
        }

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

    #[Route('/api/user/delete/{id}', name: 'app_api_user_delete', methods: ['DELETE'])]
    public function deleteUser(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['message' => 'Usuário deletado com sucesso'], 200);
    }

    #[Route('/api/user/update/{id}', name: 'app_api_user_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'JSON inválido'], 400);
        }

        try {
            if (array_key_exists('nome', $data)) {
                $user->setNome($data['nome']);
            }
            if (array_key_exists('telefone', $data)) {
                $user->setTelefone($data['telefone']);
            }
            if (array_key_exists('email', $data)) {
                $user->setEmail($data['email']);
            }
            if (array_key_exists('senha', $data)) {
                $user->setSenha($passwordHasher->hashPassword($user, $data['senha']));
            }
            if (array_key_exists('cpf', $data)) {
                $user->setCpf($data['cpf']);
            }
            if (array_key_exists('dataNascimento', $data)) {
                $user->setDataNascimento($data['dataNascimento'] ? new \DateTime($data['dataNascimento']) : null);
            }
            if (array_key_exists('imagem', $data)) {
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

        return new JsonResponse(['message' => 'Usuário atualizado com sucesso']);
    }

    #[Route('/api/user/get/{id}', name: 'app_api_user_get', methods: ['GET'])]
    public function findUser(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $imagemBase64 = null;
        if ($user->getImagem() !== null) {
            if (is_resource($user->getImagem())) {
                $imagemData = stream_get_contents($user->getImagem());
            } else {
                $imagemData = $user->getImagem();
            }

            $imagemBase64 = base64_encode($imagemData);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'nome' => $user->getNome(),
            'telefone' => $user->getTelefone(),
            'email' => $user->getEmail(),
            'cpf' => $user->getCpf(),
            'dataNascimento' => $user->getDataNascimento()?->format('Y-m-d'),
            'imagem' => $imagemBase64, // Adiciona a imagem em Base64 na resposta
        ]);
    }
}
