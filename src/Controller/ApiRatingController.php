<?php

namespace App\Controller;

use App\Entity\Rating;
use App\Dto\RatingDto;
use App\Repository\RatingRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ApiRatingController extends AbstractController
{
    #[Route('/api/rating', name: 'api_rating_create', methods: ['POST'])]
    public function createRating(
        Request $request,
        EntityManagerInterface $em,
        UserRepository $userRepository,
        ValidatorInterface $validator
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        $ratingDto = new RatingDto();
        $ratingDto->ratedUserId = $data['ratedUserId'] ?? null;
        $ratingDto->score = $data['score'] ?? null;

        $errors = $validator->validate($ratingDto);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return new JsonResponse(['errors' => $errorMessages], 400);
        }

        /** @var UserInterface $currentUser */
        $currentUser = $this->getUser();
        $rater = $userRepository->findOneBy(['email' => $currentUser->getUserIdentifier()]);

        if (!$rater) {
            return new JsonResponse(['error' => 'Usuário autenticado não encontrado.'], 404);
        }

        $ratedUser = $userRepository->find($ratingDto->ratedUserId);
        if (!$ratedUser) {
            return new JsonResponse(['error' => 'Usuário a ser avaliado não encontrado.'], 404);
        }

        if ($rater->getId() === $ratedUser->getId()) {
            return new JsonResponse(['error' => 'Você não pode avaliar seu próprio perfil.'], 400);
        }

        // Verifica se a avaliação já existe para o mesmo usuário
        $existingRating = $em->getRepository(Rating::class)->findOneBy([
            'rater' => $rater,
            'ratedUser' => $ratedUser
        ]);

        if ($existingRating) {
            // Se existir, atualiza a avaliação existente
            $existingRating->setScore($ratingDto->score);
            $message = 'Avaliação atualizada com sucesso';
        } else {
            // Se não existir, cria uma nova
            $rating = new Rating();
            $rating->setRater($rater);
            $rating->setRatedUser($ratedUser);
            $rating->setScore($ratingDto->score);
            $em->persist($rating);
            $message = 'Avaliação criada com sucesso';
        }

        $em->flush();

        return new JsonResponse([
            'message' => $message,
            'ratedUserId' => $ratedUser->getId(),
            'score' => $ratingDto->score
        ], 201);
    }

    #[Route('/api/user/rating/{userId}', name: 'api_user_rating', methods: ['GET'])]
    public function getUserAverageRating(int $userId, UserRepository $userRepository, RatingRepository $ratingRepository): JsonResponse
    {
        $user = $userRepository->find($userId);
        if (!$user) {
            return new JsonResponse(['error' => 'Usuário não encontrado'], 404);
        }

        $averageScore = $ratingRepository->getAverageScoreForUser($userId);
        $totalRatings = count($user->getReceivedRatings());

        return new JsonResponse([
            'userId' => $userId,
            'averageRating' => round($averageScore, 2),
            'totalRatings' => $totalRatings
        ]);
    }
}
