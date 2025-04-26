<?php

namespace App\Controller;

use App\DTO\CreateWorkTimeRequest;
use App\Entity\Employee;
use App\Entity\WorkTime;
use App\Repository\WorkTimeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/work-times')]
final class WorkTimeController extends AbstractController
{
    #[Route('', name: 'work_time_create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        WorkTimeRepository $workTimeRepository
    ): JsonResponse {
        try {

            /** @var CreateWorkTimeRequest $workTimeRequest */
            $workTimeRequest = $serializer->deserialize(
                $request->getContent(),
                CreateWorkTimeRequest::class,
                'json'
            );

            $errors = $validator->validate($workTimeRequest);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $employee = $entityManager->getRepository(Employee::class)->find($workTimeRequest->employeeId);
            if (!$employee) {
                return $this->json(['error' => 'Employee not found'], Response::HTTP_NOT_FOUND);
            }

            $timeStart = new \DateTimeImmutable($workTimeRequest->timeStart);
            $timeEnd = new \DateTimeImmutable($workTimeRequest->timeEnd);

            $interval = $timeStart->diff($timeEnd);
            $hoursWorked = $interval->h + ($interval->days * 24);

            $existingEntries = $workTimeRepository->findByEmployeeAndDate($employee, $timeStart);
            if (count($existingEntries) > 0) {
                return $this->json(['error' => 'Employee already has a work time entry for this day'], Response::HTTP_BAD_REQUEST);
            }

            $workTime = new WorkTime();
            $workTime->setEmployee($employee);
            $workTime->setTimeStart($timeStart);
            $workTime->setTimeEnd($timeEnd);

            $entityManager->persist($workTime);
            $entityManager->flush();

            return $this->json(
                [
                    'id' => $workTime->getId(),
                    'employeeId' => $employee->getId(),
                    'timeStart' => $workTime->getTimeStart()->format('Y-m-d H:i:s'),
                    'timeEnd' => $workTime->getTimeEnd()->format('Y-m-d H:i:s'),
                    'hoursWorked' => $hoursWorked
                ],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
