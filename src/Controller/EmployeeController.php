<?php

namespace App\Controller;

use App\DTO\CreateEmployeeRequest;
use App\Entity\Employee;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/employees')]
final class EmployeeController extends AbstractController
{
    #[Route('', name: 'employee_create', methods: ['POST'])]
    public function create(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        try {
            /** @var CreateEmployeeRequest $employeeRequest */
            $employeeRequest = $serializer->deserialize(
                $request->getContent(),
                CreateEmployeeRequest::class,
                'json'
            );

            $errors = $validator->validate($employeeRequest);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json(['errors' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $employee = new Employee();
            $employee->setName($employeeRequest->name);
            $employee->setLastName($employeeRequest->lastName);

            $entityManager->persist($employee);
            $entityManager->flush();

            return $this->json(
                [
                    'id' => $employee->getId(),
                    'name' => $employee->getName(),
                    'lastName' => $employee->getLastName()
                ],
                Response::HTTP_CREATED
            );
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        }
    }
}
