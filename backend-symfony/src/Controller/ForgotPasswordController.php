<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Annotation\Route;

class ForgotPasswordController extends AbstractController
{
    #[Route('/api/forgot-password', name: 'api_forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return new JsonResponse([
                'error' => 'Email is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['email']]);

        // Toujours retourner un succès pour éviter l'énumération d'emails
        if (!$user) {
            return new JsonResponse([
                'message' => 'If this email exists, a password reset link has been sent'
            ], Response::HTTP_OK);
        }

        // Générer un token de réinitialisation
        $resetToken = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $user->setResetToken($resetToken);
        $user->setResetTokenExpiresAt($expiresAt);

        $entityManager->flush();

        // Envoyer l'email
        $email = (new TemplatedEmail())
            ->from('noreply@checkengine.local')
            ->to($user->getEmail())
            ->subject('Password Reset Request')
            ->html(sprintf(
                '<p>Hello,</p>
                <p>You requested a password reset. Click the link below to reset your password:</p>
                <p><a href="http://localhost:5173/reset-password?token=%s">Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you did not request this, please ignore this email.</p>',
                $resetToken
            ));

        try {
            $mailer->send($email);
            $emailSent = true;
        } catch (\Exception $e) {
            // En mode dev, on continue même si l'email n'est pas envoyé
            $emailSent = false;
        }

        // En dev, on retourne le token pour pouvoir tester
        $response = [
            'message' => 'If this email exists, a password reset link has been sent'
        ];

        if ($this->getParameter('kernel.environment') === 'dev') {
            $response['debug'] = [
                'token' => $resetToken,
                'email_sent' => $emailSent,
                'expires_at' => $expiresAt->format('Y-m-d H:i:s')
            ];
        }

        return new JsonResponse($response, Response::HTTP_OK);
    }
}
