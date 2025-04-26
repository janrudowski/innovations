<?php

namespace App\Controller;

use App\DTO\DailyReportRequest;
use App\DTO\MonthlyReportRequest;
use App\Entity\Employee;
use App\Service\WorkTimeCalculator;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/reports')]
final class ReportController extends AbstractController
{
    public function __construct(
        private readonly WorkTimeCalculator $workTimeCalculator
    ) {
    }

    #[Route('/daily/{employeeId}/{year}/{month}/{day}', name: 'report_daily', methods: ['GET'])]
    public function dailyReport(
        string $employeeId,
        int $year,
        int $month,
        int $day,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {

            if ($year < 2000 || $year > 2100) {
                return $this->json(['error' => 'Year must be between 2000 and 2100'], Response::HTTP_BAD_REQUEST);
            }

            if ($month < 1 || $month > 12) {
                return $this->json(['error' => 'Month must be between 1 and 12'], Response::HTTP_BAD_REQUEST);
            }

            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            if ($day < 1 || $day > $daysInMonth) {
                return $this->json(
                    ['error' => "Day must be between 1 and $daysInMonth for $year-$month"],
                    Response::HTTP_BAD_REQUEST
                );
            }

            $employee = $entityManager->getRepository(Employee::class)->find($employeeId);
            if (!$employee) {
                return $this->json(['error' => 'Employee not found'], Response::HTTP_NOT_FOUND);
            }

            $date = new DateTimeImmutable("$year-$month-$day");

            $report = $this->workTimeCalculator->calculateDailyReport($employee, $date);

            return $this->json($report);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }

    #[Route('/monthly/{employeeId}/{year}/{month}', name: 'report_monthly', methods: ['GET'])]
    public function monthlyReport(
        string $employeeId,
        int $year,
        int $month,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {

            if ($year < 2000 || $year > 2100) {
                return $this->json(['error' => 'Year must be between 2000 and 2100'], Response::HTTP_BAD_REQUEST);
            }

            if ($month < 1 || $month > 12) {
                return $this->json(['error' => 'Month must be between 1 and 12'], Response::HTTP_BAD_REQUEST);
            }

            $employee = $entityManager->getRepository(Employee::class)->find($employeeId);
            if (!$employee) {
                return $this->json(['error' => 'Employee not found'], Response::HTTP_NOT_FOUND);
            }

            $report = $this->workTimeCalculator->calculateMonthlyReport(
                $employee,
                $year,
                $month
            );

            return $this->json($report);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
