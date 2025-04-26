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
            ->getResult()
            ;
    }
}
