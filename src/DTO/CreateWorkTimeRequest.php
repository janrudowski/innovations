<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class CreateWorkTimeRequest
{
    #[Assert\NotBlank(message: 'Employee ID is required')]
    #[Assert\Uuid(message: 'Employee ID must be a valid UUID')]
    public string $employeeId;

    #[Assert\NotBlank(message: 'Start time is required')]
    #[Assert\DateTime(message: 'Start time must be a valid date/time')]
    public string $timeStart;

    #[Assert\NotBlank(message: 'End time is required')]
    #[Assert\DateTime(message: 'End time must be a valid date/time')]
    public string $timeEnd;

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // Skip validation if basic constraints failed
        if (empty($this->timeStart) || empty($this->timeEnd)) {
            return;
        }

        try {
            $start = new \DateTimeImmutable($this->timeStart);
            $end = new \DateTimeImmutable($this->timeEnd);

            // Validation 1: End time must be after start time
            if ($end <= $start) {
                $context->buildViolation('End time must be after start time')
                    ->atPath('timeEnd')
                    ->addViolation();
                return;
            }

            // Validation 2: Work time cannot exceed 12 hours
            $interval = $start->diff($end);
            $hoursWorked = $interval->h + ($interval->days * 24);

            if ($hoursWorked > 12) {
                $context->buildViolation('Work time cannot exceed 12 hours')
                    ->atPath('timeEnd')
                    ->addViolation();
            }
        } catch (\Exception $e) {
            $context->buildViolation('Invalid date format: ' . $e->getMessage())
                ->atPath('timeStart')
                ->addViolation();
        }
    }
}
