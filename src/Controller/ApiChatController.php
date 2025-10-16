<?php

namespace App\Controller;

use App\Entity\Chat;
use App\Entity\Message;
use App\Entity\User;
use App\Repository\ChatRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;

class ApiChatController extends AbstractController
{
    #[Route('/api/chat/start/{userId}', name: 'api_chat_start', methods: ['POST'])]
    public function startChatWithUser(int $userId, EntityManagerInterface $em, UserRepository $userRepository, ChatRepository $chatRepository): JsonResponse
    {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user1 = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$user1) {
            return new JsonResponse(['error' => 'Usuário autenticado não encontrado.'], 404);
        }

        $user2 = $userRepository->find($userId);
        if (!$user2) {
            return new JsonResponse(['error' => 'Usuário destinatário não encontrado.'], 404);
        }

        if ($user1->getId() === $user2->getId()) {
            return new JsonResponse(['error' => 'Não é possível iniciar um chat consigo mesmo.'], 400);
        }

        // Verifica se já existe um chat entre os dois usuários
        $existingChat = $chatRepository->createQueryBuilder('c')
            ->join('c.users', 'u1')
            ->join('c.users', 'u2')
            ->where('u1.id = :user1Id')
            ->andWhere('u2.id = :user2Id')
            ->setParameter('user1Id', $user1->getId())
            ->setParameter('user2Id', $user2->getId())
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingChat) {
            return new JsonResponse([
                'message' => 'Chat já existe',
                'chatId' => $existingChat->getId()
            ]);
        }

        $chat = new Chat();
        $chat->addUser($user1);
        $chat->addUser($user2);

        $em->persist($chat);
        $em->flush();

        return new JsonResponse([
            'message' => 'Chat iniciado com sucesso',
            'chatId' => $chat->getId()
        ], 201);
    }

    #[Route('/api/chat/{chatId}/messages', name: 'api_chat_messages', methods: ['GET'])]
    public function getMessages(int $chatId, ChatRepository $chatRepository): JsonResponse
    {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $chat = $chatRepository->find($chatId);

        if (!$chat) {
            return new JsonResponse(['error' => 'Chat não encontrado'], 404);
        }

        $isUserInChat = false;
        foreach ($chat->getUsers() as $user) {
            if ($user->getUserIdentifier() === $currentUser->getUserIdentifier()) {
                $isUserInChat = true;
                break;
            }
        }

        if (!$isUserInChat) {
            return new JsonResponse(['error' => 'Você não tem acesso a este chat.'], 403);
        }

        $messages = $chat->getMessages()->map(function(Message $message) {
            return [
                'id' => $message->getId(),
                'sender' => $message->getSender()->getNome(),
                'content' => $message->getContent(),
                'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s')
            ];
        })->toArray();

        return new JsonResponse($messages);
    }

    #[Route('/api/chat/{chatId}/send', name: 'api_chat_send_message', methods: ['POST'])]
    public function sendMessage(int $chatId, Request $request, EntityManagerInterface $em, ChatRepository $chatRepository, UserRepository $userRepository): JsonResponse
    {
        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $sender = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$sender) {
            return new JsonResponse(['error' => 'Usuário autenticado não encontrado.'], 404);
        }

        $chat = $chatRepository->find($chatId);
        if (!$chat) {
            return new JsonResponse(['error' => 'Chat não encontrado.'], 404);
        }

        $isUserInChat = false;
        foreach ($chat->getUsers() as $user) {
            if ($user->getUserIdentifier() === $currentUser->getUserIdentifier()) {
                $isUserInChat = true;
                break;
            }
        }

        if (!$isUserInChat) {
            return new JsonResponse(['error' => 'Você não pode enviar mensagens para este chat.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        $content = $data['content'] ?? null;

        if (!$content) {
            return new JsonResponse(['error' => 'O conteúdo da mensagem é obrigatório.'], 400);
        }

        $message = new Message();
        $message->setChat($chat);
        $message->setSender($sender);
        $message->setContent($content);

        $em->persist($message);
        $em->flush();

        return new JsonResponse([
            'message' => 'Mensagem enviada com sucesso',
            'chatId' => $chat->getId(),
            'messageId' => $message->getId(),
            'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s')
        ], 201);
    }
}
