<?php

namespace App\Service;

use App\Entity\Employee;
use App\Entity\WorkTime;
use App\Repository\WorkTimeRepository;
use DateTimeImmutable;
use DateTimeInterface;

class WorkTimeCalculator
{
    private float $defaultHourlyRate;
    private int $monthlyHourLimit;
    private float $overtimeRatePercent;

    public function __construct(
        private readonly WorkTimeRepository $workTimeRepository,
        array $timeManagementConfig
    ) {
        $this->defaultHourlyRate = $timeManagementConfig['default_hourly_rate'];
        $this->monthlyHourLimit = $timeManagementConfig['monthly_hour_limit'];
        $this->overtimeRatePercent = $timeManagementConfig['overtime_rate_percent'] / 100;
    }

    public function calculateDailyReport(Employee $employee, DateTimeInterface $date): array
    {
        $workTimes = $this->workTimeRepository->findByEmployeeAndDateOverlap($employee, $date);

        $totalHours = 0;
        $dateString = $date->format('Y-m-d');
        $startOfDay = new \DateTimeImmutable($dateString . ' 00:00:00');
        $endOfDay = new \DateTimeImmutable($dateString . ' 23:59:59');

        foreach ($workTimes as $workTime) {
            // Calculate hours worked only for the portion that falls within the given day
            $start = max($workTime->getTimeStart(), $startOfDay);
            $end = min($workTime->getTimeEnd(), $endOfDay);

            $totalHours += $this->calculateHoursFromDates($start, $end);
        }

        $normalHours = $totalHours;
        $overtimeHours = 0;

        return $this->calculatePay($normalHours, $overtimeHours);
    }

    public function calculateMonthlyReport(Employee $employee, int $year, int $month): array
    {
        $startDate = new DateTimeImmutable("$year-$month-01 00:00:00");
        $endDate = $startDate->modify('last day of this month 23:59:59');

        $workTimes = $this->workTimeRepository->findByEmployeeAndDateRange($employee, $startDate, $endDate);

        $totalHours = 0;

        foreach ($workTimes as $workTime) {
            $start = max($workTime->getTimeStart(), $startDate);
            $end = min($workTime->getTimeEnd(), $endDate);

            $totalHours += $this->calculateHoursFromDates($start, $end);
        }

        $normalHours = min($totalHours, $this->monthlyHourLimit);
        $overtimeHours = max(0, $totalHours - $this->monthlyHourLimit);

        return $this->calculatePay($normalHours, $overtimeHours);
    }

    /**
     * @param mixed $normalHours
     * @param mixed $overtimeHours
     * @return array
     */
    public function calculatePay(mixed $normalHours, mixed $overtimeHours): array
    {
        $normalRate = $this->defaultHourlyRate;
        $overtimeRate = $this->defaultHourlyRate * $this->overtimeRatePercent;

        $normalPay = $normalHours * $normalRate;
        $overtimePay = $overtimeHours * $overtimeRate;
        $totalPay = $normalPay + $overtimePay;

        return [
            'normal_hours' => $normalHours,
            'rate' => $normalRate . ' PLN',
            'overtime_hours' => $overtimeHours,
            'overtime_rate' => $overtimeRate . ' PLN',
            'sum' => $totalPay . ' PLN'
        ];
    }

    private function calculateHoursWorked(WorkTime $workTime): float
    {
        $start = $workTime->getTimeStart();
        $end = $workTime->getTimeEnd();

        return $this->calculateHoursFromDates($start, $end);
    }

    private function calculateHoursFromDates(\DateTimeInterface $start, \DateTimeInterface $end): float
    {
        $interval = $start->diff($end);
        $hours = $interval->h + ($interval->days * 24);
        $minutes = $interval->i;

        // Round to nearest half hour
        // If minutes are 15-44, round to half hour
        // If minutes are 0-14, round down to the hour
        // If minutes are 45-59, round up to the next hour
        if ($minutes < 15) {
            // Round down to the hour (no change to hours, discard minutes)
            return (float)$hours;
        } elseif ($minutes < 45) {
            // Round to half hour
            return $hours + 0.5;
        } else {
            // Round up to the next hour
            return $hours + 1.0;
        }
    }
}
