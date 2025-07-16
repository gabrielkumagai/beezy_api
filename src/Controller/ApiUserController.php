<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use App\Dto\UserDto;

class ApiUserController extends AbstractController
{
    #[Route('/api/user/create', name: 'app_api_user_create', methods: ['POST'])]
    public function createUser(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        try {
            $dto = UserDto::fromArray($data);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $user = new User();
        $user->setNome($dto->nome);
        $user->setTelefone($dto->telefone);
        $user->setSenha($dto->senha);
        $user->setRg($dto->rg);
        $user->setDataNascimento($dto->dataNascimento);

        $em->persist($user);
        $em->flush();

        return new JsonResponse(['message' => 'User created', 'id' => $user->getId(), 'com sucesso'], 201);
    }


    #[Route('/api/user/delete/{id}', name: 'app_api_user_delete', methods: ['DELETE'])]
    public function deleteUser(int $id, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuario nao encontrado'], 404);
        }

        $em->remove($user);
        $em->flush();

        return new JsonResponse(['message' => 'Usuario deletado com sucesso'], 200);
    }


    #[Route('/api/user/get/{id}', name: 'app_api_user_get', methods: ['GET'])]
    public function findUser(int $id, EntityManagerInterface $em): JsonResponse
    {

        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        return new JsonResponse([
            'id' => $user->getId(),
            'nome' => $user->getNome(),
            'telefone' => $user->getTelefone(),
            'rg' => $user->getRg(),
            'dataNascimento' => $user->getDataNascimento()->format('Y-m-d')
        ]);
    }

    #[Route('/api/user/update/{id}', name: 'app_api_user_update', methods: ['PUT'])]
    public function updateUser(int $id, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $em->getRepository(User::class)->find($id);

        if (!$user) {
            return new JsonResponse(['error' => 'User not found'], 404);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        try {
            $dto = UserDto::fromArray($data);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 400);
        }

        $user->setNome($dto->nome);
        $user->setTelefone($dto->telefone);
        $user->setSenha($dto->senha);
        $user->setRg($dto->rg);
        $user->setDataNascimento($dto->dataNascimento);

        $em->flush();

        return new JsonResponse(['message' => 'User updated']);
    }


}
