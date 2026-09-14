<?php

declare(strict_types=1);

namespace App\Presentation\Security;

use App\Application\User\Register\RegisterClient;
use App\Domain\User\Exception\EmailAlreadyRegistered;
use App\Infrastructure\Security\SecurityUser;
use App\Presentation\Security\Form\RegistrationFormType;
use LogicException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    public function __construct(
        private readonly RegisterClient $registerClient,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function home(): Response
    {
        $user = $this->getUser();

        if (!$user instanceof SecurityUser) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->isAdmin()) {
            return $this->redirectToRoute('admin_home');
        }

        return $this->redirectToRoute('client_home');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof SecurityUser) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request): Response
    {
        if ($this->getUser() instanceof SecurityUser) {
            return $this->redirectToRoute('app_home');
        }

        $form = $this->createForm(RegistrationFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            $password = $form->get('plainPassword')->getData();

            if (!is_string($email) || !is_string($password)) {
                $form->addError(new FormError('Registration details are invalid.'));
            } else {
                try {
                    $this->registerClient->execute($email, $password);
                    $this->addFlash('success', 'Your account was created. You can now sign in.');

                    return $this->redirectToRoute('app_login');
                } catch (EmailAlreadyRegistered) {
                    $form->get('email')->addError(new FormError('This email is already registered.'));
                }
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new LogicException('Logout is handled by the firewall.');
    }
}
