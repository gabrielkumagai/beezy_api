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
    public function getUserFriends(int $userId, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $friends = [];
        $friendships = $userRepository->createQueryBuilder('u')
            ->select('u')
            ->innerJoin('u.sentFriendships', 'sf', 'WITH', 'sf.status = :status')
            ->where('sf.sender = :userId')
            ->orWhere('sf.receiver = :userId')
            ->setParameter('status', 'accepted')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();

        foreach ($friendships as $friendship) {
            if ($friendship->getId() !== $userId) {
                $friends[] = $this->formatUserData($friendship);
            }
        }

        $friendshipsAsReceiver = $userRepository->createQueryBuilder('u')
            ->select('u')
            ->innerJoin('u.receivedFriendships', 'rf', 'WITH', 'rf.status = :status')
            ->where('rf.receiver = :userId')
            ->setParameter('status', 'accepted')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getResult();

        foreach ($friendshipsAsReceiver as $friend) {
            if ($friend->getId() !== $userId) {
                $friends[] = $this->formatUserData($friend);
            }
        }

        return new JsonResponse($friends);
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
