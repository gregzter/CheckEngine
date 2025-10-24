<?php

namespace App\Controller;

use App\Entity\Vehicle;
use App\Entity\VehicleModel;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/vehicles')]
class VehicleController extends AbstractController
{
    public function __construct(private Security $security) {}

    #[Route('', name: 'api_vehicles_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $vehicles = array_map(function (Vehicle $vehicle) {
            return [
                'id' => $vehicle->getId(),
                'vin' => $vehicle->getVin(),
                'year' => $vehicle->getYear(),
                'mileage' => $vehicle->getMileage(),
                'nickname' => $vehicle->getNickname(),
                'created_at' => $vehicle->getCreatedAt()?->format('Y-m-d H:i:s'),
                'model' => [
                    'id' => $vehicle->getModel()->getId(),
                    'manufacturer' => $vehicle->getModel()->getManufacturer(),
                    'model' => $vehicle->getModel()->getModel(),
                    'generation' => $vehicle->getModel()->getGeneration(),
                ]
            ];
        }, $user->getVehicles()->toArray());

        return new JsonResponse($vehicles);
    }

    #[Route('', name: 'api_vehicles_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        // Validation
        if (!isset($data['vin']) || !isset($data['year']) || !isset($data['mileage'])) {
            return new JsonResponse([
                'error' => 'VIN, year and mileage are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le VIN n'existe pas déjà pour cet utilisateur
        $existingVehicle = $entityManager->getRepository(Vehicle::class)
            ->findOneBy(['vin' => $data['vin'], 'owner' => $user]);

        if ($existingVehicle) {
            return new JsonResponse([
                'error' => 'You already have a vehicle with this VIN'
            ], Response::HTTP_CONFLICT);
        }

        // Pour l'instant, on ne supporte que la Toyota Prius+ (id=1)
        $vehicleModel = $entityManager->getRepository(VehicleModel::class)->find(1);
        if (!$vehicleModel) {
            return new JsonResponse([
                'error' => 'Vehicle model not found. Only Toyota Prius+ is currently supported.'
            ], Response::HTTP_BAD_REQUEST);
        }

        $vehicle = new Vehicle();
        $vehicle->setOwner($user);
        $vehicle->setModel($vehicleModel);
        $vehicle->setVin($data['vin']);
        $vehicle->setYear((int) $data['year']);
        $vehicle->setMileage((int) $data['mileage']);

        if (isset($data['nickname'])) {
            $vehicle->setNickname($data['nickname']);
        }

        $entityManager->persist($vehicle);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Vehicle created successfully',
            'vehicle' => [
                'id' => $vehicle->getId(),
                'vin' => $vehicle->getVin(),
                'year' => $vehicle->getYear(),
                'mileage' => $vehicle->getMileage(),
                'nickname' => $vehicle->getNickname(),
                'created_at' => $vehicle->getCreatedAt()?->format('Y-m-d H:i:s'),
                'model' => [
                    'id' => $vehicleModel->getId(),
                    'manufacturer' => $vehicleModel->getManufacturer(),
                    'model' => $vehicleModel->getModel(),
                    'generation' => $vehicleModel->getGeneration(),
                ]
            ]
        ], Response::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_vehicles_show', methods: ['GET'])]
    public function show(
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $vehicle = $entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le véhicule appartient à l'utilisateur
        if ($vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        return new JsonResponse([
            'id' => $vehicle->getId(),
            'vin' => $vehicle->getVin(),
            'year' => $vehicle->getYear(),
            'mileage' => $vehicle->getMileage(),
            'nickname' => $vehicle->getNickname(),
            'created_at' => $vehicle->getCreatedAt()?->format('Y-m-d H:i:s'),
            'model' => [
                'id' => $vehicle->getModel()->getId(),
                'manufacturer' => $vehicle->getModel()->getManufacturer(),
                'model' => $vehicle->getModel()->getModel(),
                'generation' => $vehicle->getModel()->getGeneration(),
                'year_start' => $vehicle->getModel()->getYearStart(),
                'year_end' => $vehicle->getModel()->getYearEnd(),
                'engine_code' => $vehicle->getModel()->getEngineCode(),
            ],
            'trips_count' => $vehicle->getTrips()->count()
        ]);
    }

    #[Route('/{id}', name: 'api_vehicles_update', methods: ['PUT'])]
    public function update(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $vehicle = $entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le véhicule appartient à l'utilisateur
        if ($vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        // Mise à jour des champs modifiables
        if (isset($data['mileage'])) {
            $vehicle->setMileage((int) $data['mileage']);
        }

        if (isset($data['nickname'])) {
            $vehicle->setNickname($data['nickname']);
        }

        // Le VIN et l'année ne devraient pas être modifiables après création
        // mais on peut les autoriser si besoin
        if (isset($data['year'])) {
            $vehicle->setYear((int) $data['year']);
        }

        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Vehicle updated successfully',
            'vehicle' => [
                'id' => $vehicle->getId(),
                'vin' => $vehicle->getVin(),
                'year' => $vehicle->getYear(),
                'mileage' => $vehicle->getMileage(),
                'nickname' => $vehicle->getNickname(),
                'created_at' => $vehicle->getCreatedAt()?->format('Y-m-d H:i:s'),
            ]
        ]);
    }

    #[Route('/{id}', name: 'api_vehicles_delete', methods: ['DELETE'])]
    public function delete(
        int $id,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], Response::HTTP_UNAUTHORIZED);
        }

        $vehicle = $entityManager->getRepository(Vehicle::class)->find($id);

        if (!$vehicle) {
            return new JsonResponse(['error' => 'Vehicle not found'], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le véhicule appartient à l'utilisateur
        if ($vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], Response::HTTP_FORBIDDEN);
        }

        // La suppression en cascade supprimera aussi les LogSessions et DataPoints
        $entityManager->remove($vehicle);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Vehicle deleted successfully'
        ]);
    }
}
