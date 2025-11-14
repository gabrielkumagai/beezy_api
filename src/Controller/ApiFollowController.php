<?php

namespace App\Controller;

use App\Entity\Follow;
use App\Entity\User;
use App\Repository\FollowRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

#[Route('/api/user')]
class ApiFollowController extends AbstractController
{
    #[Route('/{id}/follow', name: 'api_user_follow', methods: ['POST'])]
    public function follow(
        int $id,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        FollowRepository $followRepository
    ): JsonResponse {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $follower = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$follower) {
            return new JsonResponse(['error' => 'Usuário autenticado não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $following = $userRepository->find($id);
        if (!$following) {
            return new JsonResponse(['error' => 'Usuário a ser seguido não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        if ($follower->getId() === $following->getId()) {
            return new JsonResponse(['error' => 'Você não pode seguir a si mesmo.'], Response::HTTP_BAD_REQUEST);
        }

        $existingFollow = $followRepository->findOneBy([
            'follower' => $follower,
            'following' => $following
        ]);

        if ($existingFollow) {
            return new JsonResponse(['error' => 'Você já segue este usuário.'], Response::HTTP_CONFLICT);
        }

        $follow = new Follow();
        $follow->setFollower($follower);
        $follow->setFollowing($following);

        $em->persist($follow);
        $em->flush();

        return new JsonResponse(['message' => 'Usuário seguido com sucesso.'], Response::HTTP_CREATED);
    }

    #[Route('/{id}/unfollow', name: 'api_user_unfollow', methods: ['DELETE'])]
    public function unfollow(
        int $id,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        FollowRepository $followRepository
    ): JsonResponse {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $follower = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$follower) {
            return new JsonResponse(['error' => 'Usuário autenticado não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $following = $userRepository->find($id);
        if (!$following) {
            return new JsonResponse(['error' => 'Usuário não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $follow = $followRepository->findOneBy([
            'follower' => $follower,
            'following' => $following
        ]);

        if (!$follow) {
            return new JsonResponse(['error' => 'Você não segue este usuário.'], Response::HTTP_NOT_FOUND);
        }

        $em->remove($follow);
        $em->flush();

        return new JsonResponse(['message' => 'Você deixou de seguir o usuário.'], Response::HTTP_OK);
    }

    #[Route('/{id}/followers', name: 'api_user_followers', methods: ['GET'])]
    public function getFollowers(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $followers = $user->getFollowers()->map(function (Follow $follow) {
            return $this->formatUserData($follow->getFollower());
        });

        return new JsonResponse($followers->toArray());
    }

    #[Route('/{id}/following', name: 'api_user_following', methods: ['GET'])]
    public function getFollowing(int $id, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($id);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado.'], Response::HTTP_NOT_FOUND);
        }

        $following = $user->getFollowing()->map(function (Follow $follow) {
            return $this->formatUserData($follow->getFollowing());
        });

        return new JsonResponse($following->toArray());
    }

    // Helper para formatar dados do usuário (similar ao que existe no ApiUserController)
    private function formatUserData(?User $user): ?array
    {
        if (!$user) {
            return null;
        }

        $imageData = null;
        $image = $user->getImagem();
        if ($image !== null) {
            if (is_resource($image)) {
                $image = stream_get_contents($image);
            }
            if ($image !== false) {
                $imageData = base64_encode($image);
            }
        }

        return [
            'id' => $user->getId(),
            'nome' => $user->getNome(),
            'imagem' => $imageData,
        ];
    }
}
