<?php

namespace App\Repository;

use App\Entity\Employee;
use App\Entity\WorkTime;
use DateTimeInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bridge\Doctrine\Types\UuidType;

/**
 * @extends ServiceEntityRepository<WorkTime>
 */
class WorkTimeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, WorkTime::class);
    }

    /**
     * Find work time entries for an employee on a specific date
     *
     * @param Employee $employee employee entity
     * @param DateTimeInterface $date date to check
     * @return WorkTime[]
     */
    public function findByEmployeeAndDate(Employee $employee, DateTimeInterface $date): array
    {
        // Extract just the date part (year-month-day) for comparison
        $dateString = $date->format('Y-m-d');

        // Create start and end of the day for comparison
        $startOfDay = new \DateTimeImmutable($dateString . ' 00:00:00');
        $endOfDay = new \DateTimeImmutable($dateString . ' 23:59:59');

        // Use a single DQL query with proper date range comparison
        return $this->createQueryBuilder('w')
            ->andWhere('w.employee = :employee')
            ->andWhere('w.timeStart >= :startOfDay')
            ->andWhere('w.timeStart <= :endOfDay')
            ->setParameter('employee', $employee->getId(), UuidType::NAME)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getResult();
    }

    /**
     * Find work time entries for an employee within a date range
     *
     * @param Employee $employee The employee to check
     * @param DateTimeInterface $startDate Start of the date range
     * @param DateTimeInterface $endDate End of the date range
     * @return WorkTime[] Returns an array of WorkTime objects
     */
    public function findByEmployeeAndDateRange(Employee $employee, DateTimeInterface $startDate, DateTimeInterface $endDate): array
    {
        $startDateStr = $startDate->format('Y-m-d 00:00:00');
        $endDateStr = $endDate->format('Y-m-d 23:59:59');

        return $this->createQueryBuilder('w')
            ->andWhere('w.employee = :employee')
            ->andWhere('w.timeStart >= :startOfDay')
            ->andWhere('w.timeStart <= :endOfDay')
            ->setParameter('employee', $employee->getId(), UuidType::NAME)
            ->setParameter('startOfDay', $startDateStr)
            ->setParameter('endOfDay', $endDateStr)
            ->getQuery()
            ->getResult();
    }


    /**
     * Find work time entries that OVERLAP with a specific date
     * This includes entries that:
     * - Start on the given date
     * - End on the given date
     * - Span across the given date
     *
     * @param Employee $employee employee entity
     * @param DateTimeInterface $date date to check for overlapping work times
     * @return WorkTime[]
     */
    public function findByEmployeeAndDateOverlap(Employee $employee, DateTimeInterface $date): array
    {
        $dateString = $date->format('Y-m-d');

        $startOfDay = new \DateTimeImmutable($dateString . ' 00:00:00');
        $endOfDay = new \DateTimeImmutable($dateString . ' 23:59:59');

        return $this->createQueryBuilder('w')
            ->andWhere('w.employee = :employee')
            ->andWhere(
            // Entry starts on the given date
                '(w.timeStart >= :startOfDay AND w.timeStart <= :endOfDay) OR '
                // Entry ends on the given date
                . '(w.timeEnd >= :startOfDay AND w.timeEnd <= :endOfDay) OR '
                // Entry spans across the given date
                . '(w.timeStart <= :startOfDay AND w.timeEnd >= :endOfDay)'
            )
            ->setParameter('employee', $employee->getId(), UuidType::NAME)
            ->setParameter('startOfDay', $startOfDay)
            ->setParameter('endOfDay', $endOfDay)
            ->getQuery()
            ->getResult();
    }
}