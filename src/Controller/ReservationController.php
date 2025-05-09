<?php
namespace App\Controller;
use App\Repository\ReservationRepository;
use App\Entity\Reservation;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\SecurityBundle\Security; 
class ReservationController extends AbstractController
{
   #[Route('/reservation', name: 'app_reservation')]
public function reservation(Request $request, EntityManagerInterface $em, Security $security): Response
{
    // Récupérer l'admin connecté avant d'utiliser la variable
    $admin = $security->getUser(); // doit être une instance de Admin

    if ($request->isMethod('POST')) {
        $reservation = new Reservation();

        $reservation->setNom($request->request->get('nom'));
        $reservation->setPrenom($request->request->get('prenom'));
        $reservation->setEmail($request->request->get('email'));
        $reservation->setTelephone($request->request->get('telephone'));
        $reservation->setDateNaissance(new \DateTime($request->request->get('dateNaissance')));
        $reservation->setAge($request->request->get('age'));
        $reservation->setAdmin($admin); // Maintenant $admin est défini

        $em->persist($reservation);
        $em->flush();

        $this->addFlash('success', 'Reservation successful!');
        return $this->redirectToRoute('my_reservations');
    }

    return $this->render('reservation/index.html.twig');
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
