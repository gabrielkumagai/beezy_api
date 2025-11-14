<?php

namespace App\Controller;

use WebSocket\Client;
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
                $otherUser = null;
                foreach ($existingChat->getUsers() as $user) {
                    if ($user->getId() !== $user1->getId()) {
                        $otherUser = $user;
                        break;
                    }
                }

                return new JsonResponse([
                    'message' => 'Chat já existe',
                    'chatId' => $existingChat->getId(),
                    'otherUser' => $otherUser ? [
                        'id' => $otherUser->getId(),
                        'nome' => $otherUser->getNome(),
                        'imagem' => $otherUser->getImagem()
                            ? base64_encode(stream_get_contents($otherUser->getImagem()))
                            : null
                    ] : null
                ]);
            }


        $chat = new Chat();
        $chat->addUser($user1);
        $chat->addUser($user2);

        $em->persist($chat);
        $em->flush();

        return new JsonResponse([
            'message' => 'Chat iniciado com sucesso',
            'chatId' => $chat->getId(),
            'otherUser' => [
                'id' => $user2->getId(),
                'nome' => $user2->getNome(),
                'imagem' => $user2->getImagem() ? base64_encode(stream_get_contents($user2->getImagem())) : null
            ]
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

        $messages = $chat->getMessages()
            ->matching(\Doctrine\Common\Collections\Criteria::create()->orderBy(['timestamp' => 'ASC']))
            ->map(function(Message $message) {
                return [
                    'id' => $message->getId(),
                    'sender' => $message->getSender()->getNome(),
                    'senderId' => $message->getSender()->getId(),
                    'content' => $message->getContent(),
                    'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s')
                ];
            })->toArray();


        return new JsonResponse($messages);
    }

    #[Route('/api/chat/{chatId}/send', name: 'api_chat_send_message', methods: ['POST'])]
    public function sendMessage(
        int $chatId,
        Request $request,
        EntityManagerInterface $em,
        ChatRepository $chatRepository,
        UserRepository $userRepository
    ): JsonResponse {
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

        try {
            $payload = json_encode([
                'chatId' => $chat->getId(),
                'messageId' => $message->getId(),
                'sender' => $sender->getNome(),
                'senderId' => $sender->getId(),
                'content' => $content,
                'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s')
            ]);

            $client = new Client("ws://127.0.0.1:8080");
            $client->send($payload);
            $client->close();
        } catch (\Exception $e) {
            error_log("Erro ao enviar mensagem WS: " . $e->getMessage());
        }

        return new JsonResponse([
            'message' => 'Mensagem enviada com sucesso',
            'chatId' => $chat->getId(),
            'messageId' => $message->getId(),
            'timestamp' => $message->getTimestamp()->format('Y-m-d H:i:s')
        ], 201);
    }
}
