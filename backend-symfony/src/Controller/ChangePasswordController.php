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
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class ChangePasswordController extends AbstractController
{
    #[Route('/api/change-password', name: 'api_change_password', methods: ['POST'])]
    public function changePassword(
        Request $request,
        #[CurrentUser] ?User $user,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        if (!$user) {
            return new JsonResponse([
                'error' => 'Authentication required'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            return new JsonResponse([
                'error' => 'Current password and new password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier le mot de passe actuel
        if (!$passwordHasher->isPasswordValid($user, $data['current_password'])) {
            return new JsonResponse([
                'error' => 'Current password is incorrect'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier que le nouveau mot de passe est différent
        if ($data['current_password'] === $data['new_password']) {
            return new JsonResponse([
                'error' => 'New password must be different from current password'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Hash et sauvegarde du nouveau mot de passe
        $hashedPassword = $passwordHasher->hashPassword(
            $user,
            $data['new_password']
        );
        $user->setPassword($hashedPassword);

        $entityManager->flush();

        return new JsonResponse([
            'message' => 'Password changed successfully'
        ], Response::HTTP_OK);
    }
}
