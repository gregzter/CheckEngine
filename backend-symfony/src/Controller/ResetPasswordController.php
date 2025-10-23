<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

class ResetPasswordController extends AbstractController
{
    #[Route('/api/reset-password', name: 'api_reset_password', methods: ['POST'])]
    public function resetPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['token']) || !isset($data['password'])) {
            return new JsonResponse([
                'error' => 'Token and password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)
            ->findOneBy(['resetToken' => $data['token']]);

        if (!$user) {
            return new JsonResponse([
                'error' => 'Invalid or expired reset token'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$user->isResetTokenValid()) {
            return new JsonResponse([
                'error' => 'Reset token has expired'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Hasher le nouveau mot de passe
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['password']
        );
        $user->setPassword($hashedPassword);

        // Nettoyer le token de réinitialisation
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Password has been reset successfully'
        ], Response::HTTP_OK);
    }
}
