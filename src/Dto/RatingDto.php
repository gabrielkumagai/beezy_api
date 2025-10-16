<?php
namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

class RatingDto
{
    /**
     * @Assert\NotBlank
     */
    public ?int $ratedUserId = null;

    /**
     * @Assert\NotBlank
     * @Assert\Range(min=1, max=5)
     */
    public ?int $score = null;
}
