<?php
namespace App\Controller;

use App\Entity\Admin;
use App\Entity\User;
use App\Repository\AdminRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
{
    private $entityManager;
    private $adminRepository;

    public function __construct(EntityManagerInterface $entityManager, AdminRepository $adminRepository)
    {
        $this->entityManager = $entityManager;
        $this->adminRepository = $adminRepository;
    }

    #[Route('/admin/login', name: 'admin_list')]
    public function login(Request $req): Response
    {
        // Vérifie que l'utilisateur a une session valide en tant qu'admin
        if (!$req->getSession()->get('user_id') || $req->getSession()->get('user_role') !== "admin") {
            // Redirige vers la page d'accueil si non autorisé
            return $this->redirectToRoute('home_page');
        }
    
        // Si l'utilisateur est un admin valide, afficher la liste des admins
        $admins = $this->entityManager->getRepository(Admin::class)->findAll();
    
        return $this->render('admin/index.html.twig', [
            'admins' => $admins,
        ]);
    }
    

    #[Route('/admin/new', name: 'admin_new')]
public function new(Request $request, EntityManagerInterface $entityManager): Response
{
    // Récupérer l'id de l'utilisateur depuis la session
    $userId = $request->getSession()->get('user_id');
    if (!$userId) {
        return $this->redirectToRoute('connexion_page');
    }

    // Vérifier si l'utilisateur existe
    $user = $entityManager->getRepository(User::class)->find($userId);
    if (!$user) {
        $request->getSession()->remove('user_id');
        return $this->redirectToRoute('connexion_page');
    }

    // Créer un nouvel objet Admin (anciennement Voyage)
    $voyage = new Admin();

    // Vérifier si le formulaire a été soumis
    if ($request->isMethod('POST')) {
        // Récupérer les données du formulaire
        $titre = $request->request->get('titre');
        $destination = $request->request->get('destination');
        $imageUrl = $request->request->get('imageUrl');
        $prix = (float) $request->request->get('prix');
        $duree = (int) $request->request->get('duree');
        $dateDepartStr = $request->request->get('dateDepart');
        $description = $request->request->get('description');

        // Vérifier si les champs obligatoires sont remplis
        if (!empty($titre) && !empty($destination) && !empty($prix) && !empty($duree) && !empty($dateDepartStr) && !empty($description)) {
            try {
                $dateDepart = new \DateTime($dateDepartStr);
                
                // Remplir l'objet voyage avec les données
                $voyage->setTitre($titre)
                       ->setDestination($destination)
                       ->setImageUrl($imageUrl)
                       ->setPrix($prix)
                       ->setDuree($duree)
                       ->setDateDepart($dateDepart)
                       ->setDescription($description);

                // Sauvegarder dans la base de données
                $entityManager->persist($voyage);
                $entityManager->flush();

                $this->addFlash('success', 'Voyage ajouté avec succès !');
                return $this->redirectToRoute('admin_list');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Date de départ invalide.');
            }
        } else {
            $this->addFlash('error', 'Tous les champs doivent être remplis.');
        }
    }

    // Rendre la vue du formulaire
    return $this->render('admin/new.html.twig', [
        'user' => $user,
        'voyage' => $voyage,
    ]);
}


#[Route('/admin/{id}/edit', name: 'admin_edit')]
public function edit(int $id, Request $request, EntityManagerInterface $entityManager): Response
{
    // Vérifier la session
    $userId = $request->getSession()->get('user_id');
    if (!$userId) {
        return $this->redirectToRoute('connexion_page');
    }

    // Récupérer l'utilisateur
    $user = $entityManager->getRepository(User::class)->find($userId);
    if (!$user) {
        $request->getSession()->remove('user_id');
        return $this->redirectToRoute('connexion_page');
    }

    // Récupérer le voyage existant
    $voyage = $entityManager->getRepository(Admin::class)->find($id);
    if (!$voyage) {
        throw $this->createNotFoundException('Voyage non trouvé.');
    }

    // Si le formulaire est soumis
    if ($request->isMethod('POST')) {
        $titre = $request->request->get('titre');
        $destination = $request->request->get('destination');
        $imageUrl = $request->request->get('imageUrl');
        $prix = (float) $request->request->get('prix');
        $duree = (int) $request->request->get('duree');
        $dateDepartStr = $request->request->get('dateDepart');
        $description = $request->request->get('description');

        if (!empty($titre) && !empty($destination) && !empty($prix) && !empty($duree) && !empty($dateDepartStr) && !empty($description)) {
            try {
                $dateDepart = new \DateTime($dateDepartStr);

                $voyage->setTitre($titre)
                       ->setDestination($destination)
                       ->setImageUrl($imageUrl)
                       ->setPrix($prix)
                       ->setDuree($duree)
                       ->setDateDepart($dateDepart)
                       ->setDescription($description);

                $entityManager->flush();

                $this->addFlash('success', 'Voyage modifié avec succès !');
                return $this->redirectToRoute('admin_list');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Date de départ invalide.');
            }
        } else {
            $this->addFlash('error', 'Tous les champs doivent être remplis.');
        }
    }

    // Afficher le formulaire avec les données existantes
    return $this->render('admin/edit.html.twig', [
        'user' => $user,
        'voyage' => $voyage,
    ]);
}

#[Route('/admin/{id}/delete', name: 'admin_delete')]
public function delete(Request $request, int $id, EntityManagerInterface $entityManager): Response
{
    // Vérifier si l'utilisateur est connecté
    $userId = $request->getSession()->get('user_id');
    if (!$userId) {
        return $this->redirectToRoute('connexion_page');
    }

    // Vérifier si l'utilisateur existe
    $user = $entityManager->getRepository(User::class)->find($userId);
    if (!$user) {
        $request->getSession()->remove('user_id');
        return $this->redirectToRoute('connexion_page');
    }

    // Chercher l'admin/voyage à supprimer
    $voyage = $entityManager->getRepository(Admin::class)->find($id);
    if (!$voyage) {
        throw $this->createNotFoundException('Voyage introuvable');
    }

    // (Optionnel) Vérifier que l'utilisateur a les droits pour supprimer
    // Exemple : si l'entité Admin a un champ `user`, on peut restreindre la suppression
    // if ($voyage->getUser()->getId() !== $user->getId()) {
    //     throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer ce voyage.');
    // }

    $entityManager->remove($voyage);
    $entityManager->flush();

    $this->addFlash('success', 'Voyage supprimé avec succès !');
    return $this->redirectToRoute('admin_list');
}


#[Route('/admin/voyage', name: 'admin_voyage')]
public function voyage(Request $req): Response
{
    // Vérifie que l'utilisateur est un admin connecté
    if (!$req->getSession()->get('user_id') ) {
        return $this->redirectToRoute('home_page');
    }

    // Récupère tous les admins depuis la base de données
    $admins = $this->entityManager->getRepository(Admin::class)->findAll();

    return $this->render('admin/voyage.html.twig', [
        'admins' => $admins,
    ]);
}

    
}
