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

        // Check for duplicate reservation
        $existingReservation = $this->entityManager->getRepository(Reservation::class)->findOneBy([
            'user' => $user,
            'admin' => $admin,
        ]);
        if ($existingReservation) {
            $this->logger->warning('Duplicate reservation attempt', [
                'userId' => $userId,
                'adminId' => $admin_id,
            ]);
            $this->addFlash('error', 'Vous avez déjà une réservation pour ce voyage.');
            return $this->render('reservation/index.html.twig', [
                'admin' => $admin,
                'user' => $user,
                'formData' => [],
                'showAlert' => true,
            ]);
        }

        $reservation = new Reservation();
        $formData = [];

        if ($request->isMethod('POST')) {
            $this->logger->debug('Processing POST request', [
                'nom' => $request->request->get('nom'),
                'prenom' => $request->request->get('prenom'),
                'telephone' => $request->request->get('telephone'),
                'dateNaissance' => $request->request->get('dateNaissance'),
                'age' => $request->request->get('age'),
            ]);

            // Set form data
            $formData['nom'] = $request->request->get('nom', '');
            $formData['prenom'] = $request->request->get('prenom', '');
            $formData['telephone'] = $request->request->get('telephone', '');
            $formData['dateNaissance'] = $request->request->get('dateNaissance', '');
            $formData['age'] = $request->request->get('age', '');

            // Basic validation
            if (!$formData['nom'] || !$formData['prenom'] || !$formData['telephone'] || !$formData['dateNaissance'] || !$formData['age']) {
                $this->logger->warning('Missing or empty form fields', $formData);
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                ]);
            }

            $reservation->setNom($formData['nom']);
            $reservation->setPrenom($formData['prenom']);
            $reservation->setTelephone($formData['telephone']);

            // Validate date of birth
            try {
                $dateNaissance = new \DateTime($formData['dateNaissance']);
                $reservation->setDateNaissance($dateNaissance);
            } catch (\Exception $e) {
                $this->logger->error('Invalid date of birth format', [
                    'dateNaissance' => $formData['dateNaissance'],
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Format de date de naissance invalide.');
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                ]);
            }

            // Validate age
            $age = (int) $formData['age'];
            if ($age <= 0) {
                $this->logger->warning('Invalid age', ['age' => $age]);
                $this->addFlash('error', 'L\'âge doit être supérieur à 0.');
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                ]);
            }
            $reservation->setAge($age);

            // Set admin and user
            $reservation->setAdmin($admin);
            $reservation->setUser($user);

            // Log the reservation data before saving
            $this->logger->info('Attempting to save reservation', [
                'userId' => $user->getId(),
                'adminId' => $admin->getId(),
                'nom' => $formData['nom'],
                'prenom' => $formData['prenom'],
                'telephone' => $formData['telephone'],
                'dateNaissance' => $formData['dateNaissance'],
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
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                ]);
            }
        }

        $this->logger->debug('Rendering reservation form');
        return $this->render('reservation/index.html.twig', [
            'admin' => $admin,
            'user' => $user,
            'formData' => $formData,
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

    #[Route('/reservation/edit/{id}', name: 'edit_reservation')]
    public function editReservation(int $id, Request $request): Response
    {
        $this->logger->debug('Entering editReservation method', ['reservation_id' => $id, 'method' => $request->getMethod()]);

        // Get user ID from session
        $userId = $request->getSession()->get('user_id');
        $this->logger->debug('Checking user ID in session', ['userId' => $userId]);
        if (!$userId) {
            $this->logger->warning('No user ID in session');
            $this->addFlash('error', 'Vous devez être connecté pour modifier une réservation.');
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

        // Fetch reservation
        $reservation = $this->entityManager->getRepository(Reservation::class)->find($id);
        if (!$reservation || $reservation->getUser()->getId() !== $userId) {
            $this->logger->error('Reservation not found or unauthorized', ['reservation_id' => $id, 'userId' => $userId]);
            $this->addFlash('error', 'Réservation non trouvée ou accès non autorisé.');
            return $this->redirectToRoute('my_reservations');
        }

        // Fetch admin (trip)
        $admin = $reservation->getAdmin();
        if (!$admin) {
            $this->logger->error('Admin not found for reservation', ['reservation_id' => $id]);
            $this->addFlash('error', 'Voyage associé non trouvé.');
            return $this->redirectToRoute('my_reservations');
        }

        $formData = [
            'nom' => $reservation->getNom(),
            'prenom' => $reservation->getPrenom(),
            'telephone' => $reservation->getTelephone(),
            'dateNaissance' => $reservation->getDateNaissance()->format('Y-m-d'),
            'age' => $reservation->getAge(),
        ];

        if ($request->isMethod('POST')) {
            $this->logger->debug('Processing POST request for edit', [
                'nom' => $request->request->get('nom'),
                'prenom' => $request->request->get('prenom'),
                'telephone' => $request->request->get('telephone'),
                'dateNaissance' => $request->request->get('dateNaissance'),
                'age' => $request->request->get('age'),
            ]);

            // Set form data
            $formData['nom'] = $request->request->get('nom', '');
            $formData['prenom'] = $request->request->get('prenom', '');
            $formData['telephone'] = $request->request->get('telephone', '');
            $formData['dateNaissance'] = $request->request->get('dateNaissance', '');
            $formData['age'] = $request->request->get('age', '');

            // Basic validation
            if (!$formData['nom'] || !$formData['prenom'] || !$formData['telephone'] || !$formData['dateNaissance'] || !$formData['age']) {
                $this->logger->warning('Missing or empty form fields', $formData);
                $this->addFlash('error', 'Tous les champs sont obligatoires.');
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                    'isEdit' => true,
                    'reservationId' => $id,
                ]);
            }

            $reservation->setNom($formData['nom']);
            $reservation->setPrenom($formData['prenom']);
            $reservation->setTelephone($formData['telephone']);

            // Validate date of birth
            try {
                $dateNaissance = new \DateTime($formData['dateNaissance']);
                $reservation->setDateNaissance($dateNaissance);
            } catch (\Exception $e) {
                $this->logger->error('Invalid date of birth format', [
                    'dateNaissance' => $formData['dateNaissance'],
                    'error' => $e->getMessage(),
                ]);
                $this->addFlash('error', 'Format de date de naissance invalide.');
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                    'isEdit' => true,
                    'reservationId' => $id,
                ]);
            }

            // Validate age
            $age = (int) $formData['age'];
            if ($age <= 0) {
                $this->logger->warning('Invalid age', ['age' => $age]);
                $this->addFlash('error', 'L\'âge doit être supérieur à 0.');
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                    'isEdit' => true,
                    'reservationId' => $id,
                ]);
            }
            $reservation->setAge($age);

            // Log the updated reservation data
            $this->logger->info('Attempting to update reservation', [
                'reservationId' => $id,
                'userId' => $user->getId(),
                'adminId' => $admin->getId(),
                'nom' => $formData['nom'],
                'prenom' => $formData['prenom'],
                'telephone' => $formData['telephone'],
                'dateNaissance' => $formData['dateNaissance'],
                'age' => $age,
            ]);

            // Persist to database
            try {
                $this->entityManager->flush();
                $this->logger->info('Reservation updated successfully', ['reservationId' => $id]);
                $this->addFlash('success', 'Réservation modifiée avec succès.');
                return $this->redirectToRoute('my_reservations');
            } catch (\Exception $e) {
                $this->logger->error('Failed to update reservation', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $this->addFlash('error', 'Erreur lors de la modification: ' . $e->getMessage());
                return $this->render('reservation/index.html.twig', [
                    'admin' => $admin,
                    'user' => $user,
                    'formData' => $formData,
                    'isEdit' => true,
                    'reservationId' => $id,
                ]);
            }
        }

        $this->logger->debug('Rendering edit reservation form');
        return $this->render('reservation/index.html.twig', [
            'admin' => $admin,
            'user' => $user,
            'formData' => $formData,
            'isEdit' => true,
            'reservationId' => $id,
        ]);
    }

    #[Route('/reservation/delete/{id}', name: 'delete_reservation', methods: ['POST'])]
    public function deleteReservation(int $id, Request $request): Response
    {
        $this->logger->debug('Entering deleteReservation method', ['reservation_id' => $id]);

        // Get user ID from session
        $userId = $request->getSession()->get('user_id');
        $this->logger->debug('Checking user ID in session', ['userId' => $userId]);
        if (!$userId) {
            $this->logger->warning('No user ID in session');
            $this->addFlash('error', 'Vous devez être connecté pour supprimer une réservation.');
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

        // Fetch reservation
        $reservation = $this->entityManager->getRepository(Reservation::class)->find($id);
        if (!$reservation || $reservation->getUser()->getId() !== $userId) {
            $this->logger->error('Reservation not found or unauthorized', ['reservation_id' => $id, 'userId' => $userId]);
            $this->addFlash('error', 'Réservation non trouvée ou accès non autorisé.');
            return $this->redirectToRoute('my_reservations');
        }

        // Verify CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_reservation_' . $id, $token)) {
            $this->logger->warning('Invalid CSRF token', ['reservation_id' => $id]);
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('my_reservations');
        }

        // Log and delete
        $this->logger->info('Attempting to delete reservation', ['reservationId' => $id, 'userId' => $userId]);
        try {
            $this->entityManager->remove($reservation);
            $this->entityManager->flush();
            $this->logger->info('Reservation deleted successfully', ['reservationId' => $id]);
            $this->addFlash('success', 'Réservation supprimée avec succès.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete reservation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->addFlash('error', 'Erreur lors de la suppression: ' . $e->getMessage());
        }

        return $this->redirectToRoute('my_reservations');
    }
}