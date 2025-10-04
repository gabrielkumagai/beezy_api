<?php

namespace App\Controller;

use App\Entity\Post;
use App\Entity\PostLike;
use App\Entity\PostPicture;
use App\Entity\Comment;
use App\Repository\PostRepository;
use App\Repository\UserRepository;
use App\Repository\PostLikeRepository;
use App\Dto\PostDto;
use App\Dto\CommentDto;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;

class ApiPostController extends AbstractController
{
    #[Route('/api/posts', name: 'api_post_list', methods: ['GET'])]
    public function listPosts(PostRepository $postRepository): JsonResponse
    {
        $posts = $postRepository->findAll();
        $data = array_map(fn(Post $post) => $this->formatPostData($post), $posts);

        return new JsonResponse($data);
    }

    #[Route('/api/posts/{id}', name: 'api_post_get', methods: ['GET'])]
    public function getPost(int $id, PostRepository $postRepository): JsonResponse
    {
        $post = $postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Postagem não encontrada'], 404);
        }

        return new JsonResponse($this->formatPostData($post));
    }

    #[Route('/api/users/{userId}/posts', name: 'api_user_posts', methods: ['GET'])]
    public function getPostsByUser(int $userId, PostRepository $postRepository, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $posts = $postRepository->findBy(['author' => $user]);
        $data = array_map(fn(Post $post) => $this->formatPostData($post), $posts);

        return new JsonResponse($data);
    }

    #[Route('/api/posts', name: 'api_post_create', methods: ['POST'])]
    public function createPost(Request $request, EntityManagerInterface $em, UserRepository $userRepository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new JsonResponse(['error' => 'JSON inválido: ' . json_last_error_msg()], 400);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado.'], 404);
        }

        $postDto = new PostDto();
        $postDto->description = $data['description'] ?? null;
        $postDto->tag = $data['tag'] ?? null;
        $postDto->pictures = $data['pictures'] ?? [];

        if (empty($postDto->description)) {
            return new JsonResponse(['error' => 'A descrição da postagem é obrigatória.'], 400);
        }

        $post = new Post();
        $post->setAuthor($user);
        $post->setDescription($postDto->description);
        $post->setTag($postDto->tag);

        foreach ($postDto->pictures as $base64Image) {
            $picture = new PostPicture();
            $picture->setBase64Data($base64Image);
            $post->addPicture($picture);
        }

        $em->persist($post);
        $em->flush();

        return new JsonResponse([
            'message' => 'Postagem criada com sucesso',
            'post' => $this->formatPostData($post)
        ], 201);
    }

    #[Route('/api/posts/{id}/comment', name: 'api_post_comment', methods: ['POST'])]
    public function addComment(int $id, Request $request, EntityManagerInterface $em, PostRepository $postRepository, UserRepository $userRepository): JsonResponse
    {
        $post = $postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Postagem não encontrada'], 404);
        }

        $data = json_decode($request->getContent(), true);
        $commentDto = new CommentDto();
        $commentDto->content = $data['content'] ?? null;

        if (!$commentDto->content) {
            return new JsonResponse(['error' => 'Conteúdo do comentário é obrigatório.'], 400);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado.'], 404);
        }

        $comment = new Comment();
        $comment->setPost($post);
        $comment->setAuthor($user);
        $comment->setContent($commentDto->content);
        $em->persist($comment);
        $em->flush();

        return new JsonResponse([
            'message' => 'Comentário adicionado com sucesso',
            'comment' => [
                'id' => $comment->getId(),
                'content' => $comment->getContent(),
                'author' => $comment->getAuthor()->getNome(),
                'timestamp' => $comment->getTimestamp()->format('Y-m-d H:i:s'),
            ]
        ], 201);
    }

    #[Route('/api/users/{userId}/liked-posts', name: 'api_user_liked_posts', methods: ['GET'])]
    public function getLikedPostsByUser(int $userId, PostLikeRepository $postLikeRepository, UserRepository $userRepository): JsonResponse
    {
        $user = $userRepository->find($userId);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $likedPosts = $postLikeRepository->findBy(['user' => $user]);

        $postsData = array_map(fn(PostLike $like) => $this->formatPostData($like->getPost()), $likedPosts);

        return new JsonResponse($postsData);
    }


    #[Route('/api/posts/{id}/like', name: 'api_post_like', methods: ['POST'])]
    public function likePost(int $id, EntityManagerInterface $em, PostRepository $postRepository, UserRepository $userRepository, PostLikeRepository $postLikeRepository): JsonResponse
    {
        $post = $postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Postagem não encontrada'], 404);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $user = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado.'], 404);
        }

        $existingLike = $postLikeRepository->findOneBy(['user' => $user, 'post' => $post]);

        if ($existingLike) {
            $em->remove($existingLike);
            $message = 'Curtida removida com sucesso';
        } else {
            $like = new PostLike();
            $like->setPost($post);
            $like->setUser($user);
            $em->persist($like);
            $message = 'Postagem curtida com sucesso';
        }

        $em->flush();

        $newLikesCount = $postLikeRepository->count(['post' => $post]);

        return new JsonResponse([
            'message' => $message,
            'likes' => $newLikesCount
        ]);
    }


    #[Route('/api/posts/{id}', name: 'api_post_delete', methods: ['DELETE'])]
    public function deletePost(int $id, EntityManagerInterface $em, PostRepository $postRepository): JsonResponse
    {
        $post = $postRepository->find($id);

        if (!$post) {
            return new JsonResponse(['error' => 'Postagem não encontrada'], 404);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        if ($post->getAuthor()->getEmail() !== $currentUser->getUserIdentifier()) {
            return new JsonResponse(['error' => 'Acesso negado'], 403);
        }

        $em->remove($post);
        $em->flush();

        return new JsonResponse(['message' => 'Postagem deletada com sucesso']);
    }

    private function formatPostData(Post $post): array
    {
        $comments = array_map(fn(Comment $comment) => [
            'id' => $comment->getId(),
            'content' => $comment->getContent(),
            'author' => $comment->getAuthor()->getNome(),
            'timestamp' => $comment->getTimestamp()->format('Y-m-d H:i:s'),
        ], $post->getComments()->toArray());

        $pictures = array_map(fn(PostPicture $picture) => $picture->getBase64Data(), $post->getPictures()->toArray());

        $userPhotoBase64 = null;
        if ($post->getAuthor()->getImagem() !== null) {
            $userPhoto = $post->getAuthor()->getImagem();
            if (is_resource($userPhoto)) {
                $userPhoto = stream_get_contents($userPhoto);
            }
            $userPhotoBase64 = base64_encode($userPhoto);
        }

        return [
            'id' => $post->getId(),
            'username' => $post->getAuthor()->getNome(),
            'userphoto' => $userPhotoBase64,
            'timestamp' => $post->getTimestamp()->format('Y-m-d H:i:s'),
            'pictures' => $pictures,
            'description' => $post->getDescription(),
            'likes' => count($post->getLikesByUsers()),
            'comments' => $comments,
            'tag' => $post->getTag(),
        ];
    }
}
