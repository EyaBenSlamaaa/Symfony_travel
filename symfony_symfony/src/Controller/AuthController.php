<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AuthController extends AbstractController
{
    private $emi;
    private $noteRepository;
    
    public function __construct(EntityManagerInterface $em) {
        $this->emi = $em;
    }

    #[Route('/home', name: 'home_page')]
    public function home(Request $req): Response{
        if(!$req->getSession()->get('user_id')){
            return $this->redirectToRoute('connexion_page');
        }

        // Récupérer l'ID de l'utilisateur dans la session
        $userId = $req->getSession()->get('user_id');
        
        // Chercher l'utilisateur par son ID
        $user = $this->emi->getRepository(User::class)->findOneBy(['id' => $userId]);

        // Si l'utilisateur n'existe pas, déconnecter et rediriger vers la page de connexion
        if (!$user) {
            $req->getSession()->remove('user_id');
            return $this->redirectToRoute('connexion_page');
        }

        // Vous pouvez remplacer la partie des notes par n'importe quel autre contenu
        // Par exemple, vous pouvez afficher un message personnalisé ou des données supplémentaires
        $message = "Bienvenue sur votre page d'accueil !"; // Exemple de message personnalisé

        return $this->render('pages/home.html.twig', [
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'message' => $message, // Affichage du message ou autre contenu personnalisé
            'user' => $user
        ]);
    }

    public function ConfigNav(Request $req): array {
        $userId = $req->getSession()->get('user_id');
        $user = $userId ? $this->emi->getRepository(User::class)->findOneBy(['id' => $userId]) : null;
        return [
            'is_logged_in' => $user !== null,
            'username' => $user ? $user->getUsername() : null
        ];
    }

    #[Route('/', name: 'connexion_page')]
    public function login(Request $req): Response{
        if($req->getSession()->get('user_id')){
            return $this->redirectToRoute('home_page');
        }
        
        $error = null;
        
        if($req->isMethod('POST')){
            $email = $req->request->get('email');
            $password = $req->request->get('password');
            
            if (!$email || !$password) {
                $error = 'Please provide both email and password';
            } else {
                $user = $this->emi->getRepository(User::class)->findOneBy(['email' => $email]);
                
                if (!$user || !password_verify($password, $user->getPassword())) {
                    $error = 'Invalid credentials. Please check your email and password.';
                } else {
                    $this->ConfigNav($req);
                    $req->getSession()->set('user_id', $user->getId());
                    
                    if ($req->request->get('remember_me')) {
                        $req->getSession()->set('session_lifetime', 'extended');
                    }
                    
                    return $this->redirectToRoute('home_page');
                }
            }
        }
        
        return $this->render('pages/connexion.html.twig', [
            'error' => $error
        ]);
    }

   
    
    #[Route('/inscription', name: 'inscription_page')]
    public function signup(Request $req): Response{
        if($req->getSession()->get('user_id')){
            return $this->redirectToRoute('home_page');
        }
        
        $error = null;
        
        if($req->isMethod('POST')){
            $username = $req->request->get('username');
            $email = $req->request->get('email');
            $password = $req->request->get('password');
            $confirmPassword = $req->request->get('confirm_password');
            
            if (!$username || !$email || !$password || !$confirmPassword) {
                $error = 'All fields are required';
            } elseif (strlen($username) < 3) {
                $error = 'Username should be at least 3 characters';
            } elseif (strlen($password) < 6) {
                $error = 'Password should be at least 6 characters';
            } elseif ($password !== $confirmPassword) {
                $error = 'Passwords do not match';
            } elseif ($this->emi->getRepository(User::class)->findOneBy(['email' => $email])) {
                $error = 'Email already in use';
            } elseif ($this->emi->getRepository(User::class)->findOneBy(['username' => $username])) {
                $error = 'Username already in use';
            } else {
                $user = new User();
                $user->setUsername($username);
                $user->setEmail($email);
                $user->setPassword(password_hash($password, PASSWORD_DEFAULT));
                
                $this->emi->persist($user);
                $this->emi->flush();
                
                $this->addFlash('success', 'Registration successful! You can now login.');
                return $this->redirectToRoute('connexion_page');
            }
        }
        
        return $this->render('pages/inscription.html.twig', [
            'error' => $error
        ]);
    }

    

    #[Route('/logout', name: 'deconnexion_page')]
    public function logout(Request $req): Response{
        $req->getSession()->remove('user_id');
        return $this->redirectToRoute('connexion_page');
    }
}