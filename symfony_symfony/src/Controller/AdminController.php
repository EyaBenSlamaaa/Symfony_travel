<?php

namespace App\Controller;

use App\Entity\Admin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminController extends AbstractController
{
    private EntityManagerInterface $emi;

    public function __construct(EntityManagerInterface $emi)
    {
        $this->emi = $emi;
    }

    #[Route('/admin/login', name: 'connexionAdmin_page')]
    public function login(Request $req): Response{
        if($req->getSession()->get('admin_id')){
            return $this->redirectToRoute('home_page');
        }
        
        $error = null;
        
        if($req->isMethod('POST')){
            $email = $req->request->get('email');
            $password = $req->request->get('password');
            
            if (!$email || !$password) {
                $error = 'Please provide both email and password';
            } else {
                $admin = $this->emi->getRepository(admin::class)->findOneBy(['email' => $email]);
                
                if (!$admin || !password_verify($password, $admin->getPassword())) {
                    $error = ' Please check your email and password.';
                } else {
                    $this->ConfigNav($req);
                    $req->getSession()->set('admin_id', $admin->getId());
                    
                    if ($req->request->get('remember_me')) {
                        $req->getSession()->set('session_lifetime', 'extended');
                    }
                    
                    return $this->redirectToRoute('home_page');
                }
            }
        }
        
        return $this->render('admin/login.html.twig', [
            'error' => $error
        ]);
    }
    
}
