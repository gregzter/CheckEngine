<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Message\ParseCsvMessage;
use App\Service\OBD2CsvParser;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
// use Symfony\Component\Messenger\Attribute\AsMessageHandler;

// TODO: Installer symfony/messenger pour activer cette fonctionnalité
// #[AsMessageHandler]
class ParseCsvHandler
{
    public function __construct(
        private readonly OBD2CsvParser $parser,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
        private readonly string $uploadDir
    ) {}

    public function __invoke(ParseCsvMessage $message): void
    {
        $filename = $message->getFilename();
        $filepath = $this->uploadDir . '/' . $filename;

        $this->logger->info("Starting async CSV parsing", [
            'filename' => $filename,
            'user_id' => $message->getUserId(),
            'vehicle_id' => $message->getVehicleId(),
        ]);

        try {
            // Load user and vehicle
            $user = $this->em->getRepository(User::class)->find($message->getUserId());
            if (!$user) {
                throw new \RuntimeException("User #{$message->getUserId()} not found");
            }

            $vehicle = null;
            if ($message->getVehicleId()) {
                $vehicle = $this->em->getRepository(Vehicle::class)->find($message->getVehicleId());
                if (!$vehicle) {
                    throw new \RuntimeException("Vehicle #{$message->getVehicleId()} not found");
                }
            }

            // Parse CSV
            $trip = $this->parser->parseAndStore($filepath, $user, $vehicle);

            $this->logger->info("Async CSV parsing completed", [
                'trip_id' => $trip->getId(),
                'status' => $trip->getStatus(),
                'data_points' => $trip->getDataPointsCount(),
            ]);

            // TODO: Send notification to user (webhook, SSE, WebSocket, etc.)
            // Example: $this->notificationService->notifyTripCompleted($user, $trip);

        } catch (\Exception $e) {
            $this->logger->error("Async CSV parsing failed", [
                'filename' => $filename,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // TODO: Notify user of failure
            // Example: $this->notificationService->notifyTripFailed($user, $filename, $e->getMessage());

            throw $e;
        } finally {
            // Cleanup: delete uploaded file after processing
            if (file_exists($filepath)) {
                @unlink($filepath);
                $this->logger->info("Uploaded file deleted", ['filename' => $filename]);
            }
        }
    }
}
