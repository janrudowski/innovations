<?php

namespace App\Entity;

use App\Repository\WorkTimeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorkTimeRepository::class)]
class WorkTime
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'workTimes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employee $employee = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $timeStart = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $timeEnd = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    public function getTimeStart(): ?\DateTimeImmutable
    {
        return $this->timeStart;
    }

    public function setTimeStart(\DateTimeImmutable $timeStart): static
    {
        $this->timeStart = $timeStart;

        return $this;
    }

    public function getTimeEnd(): ?\DateTimeImmutable
    {
        return $this->timeEnd;
    }

    public function setTimeEnd(\DateTimeImmutable $timeEnd): static
    {
        $this->timeEnd = $timeEnd;

        return $this;
    }
}
