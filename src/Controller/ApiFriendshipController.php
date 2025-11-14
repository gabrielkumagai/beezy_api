<?php

namespace App\Controller;

use App\Entity\Friendship;
use App\Entity\User;
use App\Repository\FriendshipRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiFriendshipController extends AbstractController
{
    #[Route('/api/friendship/send', name: 'api_friendship_send', methods: ['POST'])]
    public function sendFriendshipRequest(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $sender = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        $receiverId = $data['receiverId'] ?? null;
        if (!$receiverId) {
            return new JsonResponse(['error' => 'ID do destinatário é obrigatório'], 400);
        }

        $receiver = $userRepository->find($receiverId);
        if (!$receiver) {
            return new JsonResponse(['error' => 'Destinatário não encontrado'], 404);
        }

        if ($sender->getId() === $receiver->getId()) {
            return new JsonResponse(['error' => 'Você não pode enviar uma solicitação de amizade para si mesmo'], 400);
        }

        $existingFriendship = $em->getRepository(Friendship::class)->findOneBy([
            'sender' => $sender,
            'receiver' => $receiver
        ]);
        if (!$existingFriendship) {
            $existingFriendship = $em->getRepository(Friendship::class)->findOneBy([
                'sender' => $receiver,
                'receiver' => $sender
            ]);
        }

        if ($existingFriendship) {
            return new JsonResponse(['error' => 'Solicitação de amizade já existe'], 409);
        }

        $friendship = new Friendship();
        $friendship->setSender($sender);
        $friendship->setReceiver($receiver);
        $friendship->setStatus('pending');

        $em->persist($friendship);
        $em->flush();

        return new JsonResponse(['message' => 'Solicitação de amizade enviada com sucesso'], 201);
    }

    #[Route('/api/friendship/accept/{id}', name: 'api_friendship_accept', methods: ['POST'])]
    public function acceptFriendshipRequest(int $id, EntityManagerInterface $em, FriendshipRepository $friendshipRepository, UserRepository $userRepository): JsonResponse
    {
        $friendship = $friendshipRepository->find($id);

        if (!$friendship) {
            return new JsonResponse(['error' => 'Solicitação de amizade não encontrada'], 404);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if ($friendship->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Você não tem permissão para aceitar esta solicitação'], 403);
        }

        $friendship->setStatus('accepted');
        $em->flush();

        return new JsonResponse(['message' => 'Solicitação de amizade aceita com sucesso']);
    }

    #[Route('/api/friendship/decline/{id}', name: 'api_friendship_decline', methods: ['DELETE'])]
    public function declineFriendshipRequest(int $id, EntityManagerInterface $em, FriendshipRepository $friendshipRepository, UserRepository $userRepository): JsonResponse
    {
        $friendship = $friendshipRepository->find($id);

        if (!$friendship) {
            return new JsonResponse(['error' => 'Solicitação de amizade não encontrada'], 404);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if ($friendship->getReceiver()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Você não tem permissão para recusar esta solicitação'], 403);
        }

        $em->remove($friendship);
        $em->flush();

        return new JsonResponse(['message' => 'Solicitação de amizade recusada com sucesso']);
    }

    #[Route('/api/friendship/remove/{id}', name: 'api_friendship_remove', methods: ['DELETE'])]
    public function removeFriend(int $id, EntityManagerInterface $em, FriendshipRepository $friendshipRepository, UserRepository $userRepository): JsonResponse
    {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        $friendship = $friendshipRepository->createQueryBuilder('f')
            ->where('f.status = :status')
            ->andWhere('(f.sender = :user AND f.receiver = :friendId) OR (f.sender = :friendId AND f.receiver = :user)')
            ->setParameters([
                'status' => 'accepted',
                'user' => $user,
                'friendId' => $id
            ])
            ->getQuery()
            ->getOneOrNullResult();

        if (!$friendship) {
            return new JsonResponse(['error' => 'Amizade não encontrada ou não é aceita'], 404);
        }

        $em->remove($friendship);
        $em->flush();

        return new JsonResponse(['message' => 'Amizade removida com sucesso']);
    }

    #[Route('/api/user/friends/{userId}', name: 'api_user_friends', methods: ['GET'])]
    public function getUserFriends(int $userId, FriendshipRepository $friendshipRepository, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $friendships = $friendshipRepository->createQueryBuilder('f')
            ->where('f.status = :status')
            ->andWhere('f.sender = :user OR f.receiver = :user')
            ->setParameter('status', 'accepted')
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        $friends = [];

        foreach ($friendships as $friendship) {
            // Se o usuário for o sender, o amigo é o receiver
            if ($friendship->getSender()->getId() === $user->getId()) {
                $friends[] = $this->formatUserData($friendship->getReceiver());
            } else {
                // Se o usuário for o receiver, o amigo é o sender
                $friends[] = $this->formatUserData($friendship->getSender());
            }
        }

        return new JsonResponse($friends);
    }


    #[Route('/api/friendship/received', name: 'api_friendship_received', methods: ['GET'])]
    public function getReceivedFriendRequests(FriendshipRepository $friendshipRepository, UserRepository $userRepository): JsonResponse
    {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        $requests = $friendshipRepository->findBy([
            'receiver' => $user,
            'status' => 'pending'
        ]);

        $result = [];
        foreach ($requests as $req) {
            $result[] = [
                'id' => $req->getId(),
                'sender' => [
                    'id' => $req->getSender()->getId(),
                    'nome' => $req->getSender()->getNome(),
                    'email' => $req->getSender()->getEmail()
                ],
                'status' => $req->getStatus()
            ];
        }

        return new JsonResponse($result);
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
