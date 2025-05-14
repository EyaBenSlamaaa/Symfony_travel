<?php

namespace App\Controller;

use App\Entity\Reservation;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Psr\Log\LoggerInterface;

class ReservationController extends AbstractController
{
    private $entityManager;
    private $adminRepository;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, AdminRepository $adminRepository, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->adminRepository = $adminRepository;
        $this->logger = $logger;
    }

    #[Route('/reservation/{admin_id}', name: 'app_reservation')]
    public function reservation(int $admin_id, Request $request): Response
    {
        $this->logger->debug('Entering reservation method', ['admin_id' => $admin_id, 'method' => $request->getMethod()]);

        // Fetch admin (trip)
        $admin = $this->adminRepository->find($admin_id);
        if (!$admin) {
            $this->logger->error('Admin not found', ['admin_id' => $admin_id]);
            $this->addFlash('error', 'Administrateur non trouvé.');
            return $this->redirectToRoute('app_home');
        }

        // Get user ID from session
        $userId = $request->getSession()->get('user_id');
        $this->logger->debug('Checking user ID in session', ['userId' => $userId]);
        if (!$userId) {
            $this->logger->warning('No user ID in session');
            $this->addFlash('error', 'Vous devez être connecté pour faire une réservation.');
            return $this->redirectToRoute('app_login');
        }

        // Fetch user
        $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        if (!$user) {
            $this->logger->error('User not found', ['userId' => $userId]);
            $request->getSession()->remove('user_id');
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {
            $this->logger->debug('Processing POST request', [
                'nom' => $request->request->get('nom'),
                'prenom' => $request->request->get('prenom'),
                'telephone' => $request->request->get('telephone'),
                'dateNaissance' => $request->request->get('dateNaissance'),
                'age' => $request->request->get('age'),
            ]);

            $reservation = new Reservation();

            // Set form data
            $nom = $request->request->get('nom', '');
            $prenom = $request->request->get('prenom', '');
            $telephone = $request->request->get('telephone', '');
            $dateNaissanceInput = $request->request->get('dateNaissance', '');
            $age = (int) $request->request->get('age', 0);

            // Basic validation
            if (!$nom || !$prenom || !$telephone || !$dateNaissanceInput || !$age) {
                $this->logger->warning('Missing or empty form fields', [
                    'nom' => $nom,
                    'prenom' => $prenom,
                    'telephone' => $telephone,
                    'dateNaissance' => $dateNaissanceInput,
                    'age' => $age,
                ]);
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->redirectToRoute('app_reservation', ['admin_id' => $admin_id]);
            }

            $reservation->setNom($nom);
            $reservation->setPrenom($prenom);
            $reservation->setTelephone($telephone);

            // Validate date of birth
            try {
                $dateNaissance = new \DateTime($dateNaissanceInput);
                $reservation->setDateNaissance($dateNaissance);
            } catch (\Exception $e) {
                $this->logger->error('Invalid date of birth format', [
                    'dateNaissance' => $dateNaissanceInput,
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Format de date de naissance invalide.');
                return $this->redirectToRoute('app_reservation', ['admin_id' => $admin_id]);
            }

            // Validate age
            if ($age <= 0) {
                $this->logger->warning('Invalid age', ['age' => $age]);
                $this->addFlash('error', 'L\'âge doit être supérieur à 0.');
                return $this->redirectToRoute('app_reservation', ['admin_id' => $admin_id]);
            }
            $reservation->setAge($age);

            // Set admin and user
            $reservation->setAdmin($admin);
            $reservation->setUser($user);

            // Log the reservation data before saving
            $this->logger->info('Attempting to save reservation', [
                'userId' => $user->getId(),
                'adminId' => $admin->getId(),
                'nom' => $nom,
                'prenom' => $prenom,
                'telephone' => $telephone,
                'dateNaissance' => $dateNaissanceInput,
                'age' => $age,
            ]);

            // Persist to database
            try {
                $this->entityManager->persist($reservation);
                $this->entityManager->flush();
                $this->logger->info('Reservation saved successfully', ['reservationId' => $reservation->getId()]);
                $this->addFlash('success', 'Réservation enregistrée avec succès.');
                return $this->redirectToRoute('my_reservations');
            } catch (\Exception $e) {
                $this->logger->error('Failed to save reservation', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addFlash('error', 'Erreur lors de l\'enregistrement: ' . $e->getMessage());
                return $this->redirectToRoute('app_reservation', ['admin_id' => $admin_id]);
            }
        }

        $this->logger->debug('Rendering reservation form');
        return $this->render('reservation/index.html.twig', [
            'admin' => $admin,
            'user' => $user,
        ]);
    }

    #[Route('/my-reservations', name: 'my_reservations')]
    public function myReservations(Request $request): Response
    {
        // Get user ID from session
        $userId = $request->getSession()->get('user_id');
        $this->logger->debug('Checking user ID in myReservations', ['userId' => $userId]);
        if (!$userId) {
            $this->logger->warning('No user ID in session');
            $this->addFlash('error', 'Vous devez être connecté pour voir vos réservations.');
            return $this->redirectToRoute('app_login');
        }

        // Fetch user
        $user = $this->entityManager->getRepository(\App\Entity\User::class)->find($userId);
        if (!$user) {
            $this->logger->error('User not found', ['userId' => $userId]);
            $request->getSession()->remove('user_id');
            $this->addFlash('error', 'Utilisateur non trouvé.');
            return $this->redirectToRoute('app_login');
        }

        // Fetch reservations for the user
        $reservations = $this->entityManager->getRepository(Reservation::class)->findBy(['user' => $user]);
        $this->logger->debug('Fetched reservations', ['count' => count($reservations)]);

        return $this->render('reservation/my_reservations.html.twig', [
            'reservations' => $reservations,
            'user' => $user,
        ]);
    }
}