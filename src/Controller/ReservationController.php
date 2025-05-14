<?php

namespace App\Controller;

use App\Repository\ReservationRepository;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Repository\AdminRepository;
use Symfony\Component\Security\Core\Security;

class ReservationController extends AbstractController
{
   #[Route('/reservation/{admin_id}', name: 'app_reservation')]
    public function reservation(
        int $admin_id,
        Request $request,
        EntityManagerInterface $em,
        AdminRepository $adminRepository
    ): Response {
        // Récupérer l'admin par ID
        $admin = $adminRepository->find($admin_id);

        if (!$admin) {
            $this->addFlash('error', 'Administrateur non trouvé.');
            return $this->redirectToRoute('app_home'); // Rediriger vers une page d'accueil ou autre
        }

        if ($request->isMethod('POST')) {
            $reservation = new Reservation();

            $reservation->setNom($request->request->get('nom'));
            $reservation->setPrenom($request->request->get('prenom'));
            $reservation->setTelephone($request->request->get('telephone'));

            try {
                $dateNaissance = new \DateTime($request->request->get('dateNaissance'));
            } catch (\Exception $e) {
                $this->addFlash('error', 'Date de naissance invalide.');
                return $this->redirectToRoute('app_reservation', ['admin_id' => $admin_id]);
            }

            $now = new \DateTime();
            $age = $now->diff($dateNaissance)->y;

            $reservation->setDateNaissance($dateNaissance);
            $reservation->setAge($age);
            $reservation->setAdmin($admin);

            $em->persist($reservation);
            $em->flush();

            $this->addFlash('success', 'Réservation enregistrée avec succès.');
            return $this->redirectToRoute('my_reservations');
        }

        return $this->render('reservation/index.html.twig', [
            'admins' => $admin,
             // Passer l'admin sélectionné au template
        ]);
    }
    #[Route('/my-reservations', name: 'my_reservations')]
    public function myReservations(ReservationRepository $repository): Response
    {
        $reservations = $repository->findAll();

        return $this->render('reservation/my_reservations.html.twig', [
            'reservations' => $reservations,
        ]);
    }
}
