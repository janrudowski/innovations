<?php

namespace App\Tests\Unit\Service;

use App\Entity\Employee;
use App\Entity\WorkTime;
use App\Repository\WorkTimeRepository;
use App\Service\WorkTimeCalculator;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class WorkTimeCalculatorTest extends TestCase
{
    private WorkTimeRepository $workTimeRepository;
    private array $timeManagementConfig;
    private WorkTimeCalculator $calculator;

    protected function setUp(): void
    {
        $this->workTimeRepository = $this->createMock(WorkTimeRepository::class);
        $this->timeManagementConfig = [
            'default_hourly_rate' => 50.0,
            'monthly_hour_limit' => 160,
            'overtime_rate_percent' => 150,
        ];
        $this->calculator = new WorkTimeCalculator(
            $this->workTimeRepository,
            $this->timeManagementConfig
        );
    }

    public function testCalculateHoursFromDates(): void
    {
        // Test exact hour
        $start = new DateTimeImmutable('2025-04-26 09:00:00');
        $end = new DateTimeImmutable('2025-04-26 17:00:00');
        $result = $this->invokePrivateMethod($this->calculator, 'calculateHoursFromDates', [$start, $end]);
        $this->assertEquals(8.0, $result);

        // Test minutes less than 15 (round down)
        $start = new DateTimeImmutable('2025-04-26 09:00:00');
        $end = new DateTimeImmutable('2025-04-26 17:14:59');
        $result = $this->invokePrivateMethod($this->calculator, 'calculateHoursFromDates', [$start, $end]);
        $this->assertEquals(8.0, $result);

        // Test minutes between 15 and 44 (round to half hour)
        $start = new DateTimeImmutable('2025-04-26 09:00:00');
        $end = new DateTimeImmutable('2025-04-26 17:30:00');
        $result = $this->invokePrivateMethod($this->calculator, 'calculateHoursFromDates', [$start, $end]);
        $this->assertEquals(8.5, $result);

        // Test minutes 45 or more (round up)
        $start = new DateTimeImmutable('2025-04-26 09:00:00');
        $end = new DateTimeImmutable('2025-04-26 17:45:00');
        $result = $this->invokePrivateMethod($this->calculator, 'calculateHoursFromDates', [$start, $end]);
        $this->assertEquals(9.0, $result);
    }

    public function testCalculateHoursWorked(): void
    {
        $workTime = $this->createMock(WorkTime::class);
        $workTime->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-26 09:00:00'));
        $workTime->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-26 17:30:00'));

        $result = $this->invokePrivateMethod($this->calculator, 'calculateHoursWorked', [$workTime]);
        $this->assertEquals(8.5, $result);
    }

    public function testCalculatePay(): void
    {
        $normalHours = 160.0;
        $overtimeHours = 10.0;

        $result = $this->calculator->calculatePay($normalHours, $overtimeHours);

        $this->assertEquals(160.0, $result['normal_hours']);
        $this->assertEquals('50 PLN', $result['rate']);
        $this->assertEquals(10.0, $result['overtime_hours']);
        $this->assertEquals('75 PLN', $result['overtime_rate']);
        $this->assertEquals('8750 PLN', $result['sum']);
    }

    public function testCalculateDailyReport(): void
    {
        $employee = $this->createMock(Employee::class);
        $date = new DateTimeImmutable('2025-04-26');

        $workTime1 = $this->createMock(WorkTime::class);
        $workTime1->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-26 09:00:00'));
        $workTime1->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-26 17:30:00'));

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateOverlap')
            ->with($employee, $date)
            ->willReturn([$workTime1]);

        $result = $this->calculator->calculateDailyReport($employee, $date);

        $this->assertEquals(8.5, $result['normal_hours']);
        $this->assertEquals(0, $result['overtime_hours']);
        $this->assertEquals('425 PLN', $result['sum']);
    }

    public function testCalculateDailyReportWithMultipleEntries(): void
    {
        $employee = $this->createMock(Employee::class);
        $date = new DateTimeImmutable('2025-04-26');

        $workTime1 = $this->createMock(WorkTime::class);
        $workTime1->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-26 09:00:00'));
        $workTime1->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-26 12:00:00'));

        $workTime2 = $this->createMock(WorkTime::class);
        $workTime2->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-26 13:00:00'));
        $workTime2->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-26 17:30:00'));

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateOverlap')
            ->with($employee, $date)
            ->willReturn([$workTime1, $workTime2]);

        $result = $this->calculator->calculateDailyReport($employee, $date);

        $this->assertEquals(7.5, $result['normal_hours']);
        $this->assertEquals(0, $result['overtime_hours']);
        $this->assertEquals('375 PLN', $result['sum']);
    }

    public function testCalculateMonthlyReport(): void
    {
        $employee = $this->createMock(Employee::class);
        $year = 2025;
        $month = 4;

        $workTime1 = $this->createMock(WorkTime::class);
        $workTime1->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-01 09:00:00'));
        $workTime1->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-01 17:00:00'));

        $workTime2 = $this->createMock(WorkTime::class);
        $workTime2->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-02 09:00:00'));
        $workTime2->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-02 17:00:00'));

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateRange')
            ->willReturn([$workTime1, $workTime2]);

        $result = $this->calculator->calculateMonthlyReport($employee, $year, $month);

        $this->assertEquals(16.0, $result['normal_hours']);
        $this->assertEquals(0, $result['overtime_hours']);
        $this->assertEquals('800 PLN', $result['sum']);
    }

    public function testCalculateMonthlyReportWithOvertime(): void
    {
        $employee = $this->createMock(Employee::class);
        $year = 2025;
        $month = 4;

        // Create enough work times to exceed the monthly limit (160 hours)
        $workTimes = [];
        for ($day = 1; $day <= 21; $day++) {
            $workTime = $this->createMock(WorkTime::class);
            $workTime->method('getTimeStart')->willReturn(new DateTimeImmutable("2025-04-{$day} 09:00:00"));
            $workTime->method('getTimeEnd')->willReturn(new DateTimeImmutable("2025-04-{$day} 17:00:00"));
            $workTimes[] = $workTime;
        }

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateRange')
            ->willReturn($workTimes);

        $result = $this->calculator->calculateMonthlyReport($employee, $year, $month);

        $this->assertEquals(160.0, $result['normal_hours']);
        $this->assertEquals(8.0, $result['overtime_hours']);
        $this->assertEquals('8600 PLN', $result['sum']);
    }

    /**
     * Test edge case: work time spanning over month boundary
     */
    public function testCalculateMonthlyReportWithWorkTimeSpanningMonths(): void
    {
        $employee = $this->createMock(Employee::class);
        $year = 2025;
        $month = 4;

        // Work time that starts in April and ends in May
        $workTime = $this->createMock(WorkTime::class);
        $workTime->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-30 20:00:00'));
        $workTime->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-05-01 04:00:00'));

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateRange')
            ->willReturn([$workTime]);

        $result = $this->calculator->calculateMonthlyReport($employee, $year, $month);

        // Only hours until midnight of April 30 should be counted (4 hours)
        $this->assertEquals(4.0, $result['normal_hours']);
        $this->assertEquals(0, $result['overtime_hours']);
        $this->assertEquals('200 PLN', $result['sum']);
    }

    /**
     * Test edge case: work time spanning over month boundary in reverse
     */
    public function testCalculateMonthlyReportWithWorkTimeSpanningFromPreviousMonth(): void
    {
        $employee = $this->createMock(Employee::class);
        $year = 2025;
        $month = 5; // May

        // Work time that starts in April and ends in May
        $workTime = $this->createMock(WorkTime::class);
        $workTime->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-30 20:00:00'));
        $workTime->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-05-01 04:00:00'));

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateRange')
            ->willReturn([$workTime]);

        $result = $this->calculator->calculateMonthlyReport($employee, $year, $month);

        // Only hours from midnight of May 1 should be counted (4 hours)
        $this->assertEquals(4.0, $result['normal_hours']);
        $this->assertEquals(0, $result['overtime_hours']);
        $this->assertEquals('200 PLN', $result['sum']);
    }

    /**
     * Test edge case: work time spanning over multiple days within month
     */
    public function testCalculateMonthlyReportWithWorkTimeSpanningMultipleDays(): void
    {
        $employee = $this->createMock(Employee::class);
        $year = 2025;
        $month = 4;

        // Work time that spans multiple days
        $workTime = $this->createMock(WorkTime::class);
        $workTime->method('getTimeStart')->willReturn(new DateTimeImmutable('2025-04-15 20:00:00'));
        $workTime->method('getTimeEnd')->willReturn(new DateTimeImmutable('2025-04-17 08:00:00'));

        $this->workTimeRepository
            ->expects($this->once())
            ->method('findByEmployeeAndDateRange')
            ->willReturn([$workTime]);

        $result = $this->calculator->calculateMonthlyReport($employee, $year, $month);

        // 36 hours total (20:00 on 15th to 08:00 on 17th)
        $this->assertEquals(36.0, $result['normal_hours']);
        $this->assertEquals(0, $result['overtime_hours']);
        $this->assertEquals('1800 PLN', $result['sum']);
    }

    /**
     * Helper method to invoke private methods for testing
     */
    private function invokePrivateMethod($object, string $methodName, array $parameters = [])
    {
        $reflection = new \ReflectionClass(get_class($object));
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);
        return $method->invokeArgs($object, $parameters);
    }
}
