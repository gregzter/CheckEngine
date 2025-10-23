<?php

namespace App\Controller;

use App\Entity\Trip;
use App\Entity\Vehicle;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class TripController extends AbstractController
{
    public function __construct(private Security $security)
    {
    }

    #[Route('/vehicles/{vehicleId}/trips/upload', name: 'api_trips_upload', methods: ['POST'])]
    public function upload(
        int $vehicleId,
        Request $request,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // Vérifier l'authentification
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }

        // Vérifier que le véhicule existe et appartient à l'utilisateur
        $vehicle = $entityManager->getRepository(Vehicle::class)->find($vehicleId);
        if (!$vehicle) {
            return new JsonResponse(['error' => 'Vehicle not found'], 404);
        }

        if ($vehicle->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Récupérer le fichier uploadé
        $uploadedFile = $request->files->get('file');
        if (!$uploadedFile) {
            return new JsonResponse(['error' => 'No file provided'], 400);
        }

        // Vérifier que c'est un fichier ZIP
        $mimeType = $uploadedFile->getMimeType();
        if ($mimeType !== 'application/zip' && $mimeType !== 'application/x-zip-compressed') {
            return new JsonResponse([
                'error' => 'Invalid file type. Only ZIP files are allowed',
                'received_mime' => $mimeType
            ], 400);
        }

        // Vérifier la taille (max 50MB)
        $maxSize = 50 * 1024 * 1024; // 50MB
        if ($uploadedFile->getSize() > $maxSize) {
            return new JsonResponse([
                'error' => 'File too large. Maximum size is 50MB',
                'size' => $uploadedFile->getSize()
            ], 400);
        }

        // Générer un nom de fichier unique
        $originalFilename = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $originalFilename);
        $newFilename = $safeFilename . '_' . uniqid() . '.zip';

        // Déplacer le fichier dans le répertoire de stockage
        $uploadsDir = $this->getParameter('kernel.project_dir') . '/var/uploads/trips';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        try {
            $uploadedFile->move($uploadsDir, $newFilename);
        } catch (FileException $e) {
            return new JsonResponse([
                'error' => 'Failed to upload file',
                'message' => $e->getMessage()
            ], 500);
        }

        // Créer l'entité Trip
        $trip = new Trip();
        $trip->setVehicle($vehicle);
        $trip->setFilename($newFilename);
        $trip->setFilePath($uploadsDir . '/' . $newFilename);
        $trip->setSessionDate(new \DateTimeImmutable());
        $trip->setStatus('pending');

        $entityManager->persist($trip);
        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Trip uploaded successfully',
            'trip' => [
                'id' => $trip->getId(),
                'filename' => $trip->getFilename(),
                'status' => $trip->getStatus(),
                'uploaded_at' => $trip->getUploadedAt()->format('Y-m-d H:i:s'),
                'vehicle_id' => $vehicle->getId()
            ]
        ], 201);
    }

    #[Route('/trips', name: 'api_trips_list', methods: ['GET'])]
    public function list(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }

        // Récupérer tous les véhicules de l'utilisateur
        $vehicles = $entityManager->getRepository(Vehicle::class)->findBy(['owner' => $user]);
        
        // Récupérer tous les trips de ces véhicules
        $allTrips = [];
        foreach ($vehicles as $vehicle) {
            foreach ($vehicle->getTrips() as $trip) {
                $allTrips[] = [
                    'id' => $trip->getId(),
                    'filename' => $trip->getFilename(),
                    'session_date' => $trip->getSessionDate()->format('Y-m-d H:i:s'),
                    'duration' => $trip->getDuration(),
                    'distance' => $trip->getDistance(),
                    'status' => $trip->getStatus(),
                    'uploaded_at' => $trip->getUploadedAt()->format('Y-m-d H:i:s'),
                    'vehicle' => [
                        'id' => $vehicle->getId(),
                        'nickname' => $vehicle->getNickname()
                    ]
                ];
            }
        }

        return new JsonResponse($allTrips);
    }

    #[Route('/trips/{id}', name: 'api_trips_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }

        $trip = $entityManager->getRepository(Trip::class)->find($id);
        if (!$trip) {
            return new JsonResponse(['error' => 'Trip not found'], 404);
        }

        // Vérifier que le trip appartient à un véhicule de l'utilisateur
        if ($trip->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        return new JsonResponse([
            'id' => $trip->getId(),
            'filename' => $trip->getFilename(),
            'session_date' => $trip->getSessionDate()->format('Y-m-d H:i:s'),
            'duration' => $trip->getDuration(),
            'distance' => $trip->getDistance(),
            'data_points_count' => $trip->getDataPointsCount(),
            'status' => $trip->getStatus(),
            'uploaded_at' => $trip->getUploadedAt()->format('Y-m-d H:i:s'),
            'analyzed_at' => $trip->getAnalyzedAt()?->format('Y-m-d H:i:s'),
            'catalyst_efficiency' => $trip->getCatalystEfficiency(),
            'avg_fuel_trim_st' => $trip->getAvgFuelTrimST(),
            'avg_fuel_trim_lt' => $trip->getAvgFuelTrimLT(),
            'analysis_results' => $trip->getAnalysisResults(),
            'vehicle' => [
                'id' => $trip->getVehicle()->getId(),
                'nickname' => $trip->getVehicle()->getNickname(),
                'vin' => $trip->getVehicle()->getVin()
            ]
        ]);
    }

    #[Route('/trips/{id}', name: 'api_trips_delete', methods: ['DELETE'])]
    public function delete(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse(['error' => 'Authentication required'], 401);
        }

        $trip = $entityManager->getRepository(Trip::class)->find($id);
        if (!$trip) {
            return new JsonResponse(['error' => 'Trip not found'], 404);
        }

        // Vérifier que le trip appartient à un véhicule de l'utilisateur
        if ($trip->getVehicle()->getOwner()->getId() !== $user->getId()) {
            return new JsonResponse(['error' => 'Access denied'], 403);
        }

        // Supprimer le fichier physique
        $filePath = $trip->getFilePath();
        if ($filePath && file_exists($filePath)) {
            unlink($filePath);
        }

        $entityManager->remove($trip);
        $entityManager->flush();

        return new JsonResponse(['message' => 'Trip deleted successfully']);
    }
}
