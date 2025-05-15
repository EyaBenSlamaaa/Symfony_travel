<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class OpenAIController extends AbstractController
{
    #[Route('/admin/generate-image', name: 'generate_image', methods: ['POST'])]
    public function generateImage(Request $request): JsonResponse
    {
        // Get the image description from the request
        $description = $request->request->get('description');
        
        if (empty($description)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Description is required'
            ], 400);
    
}
        
        // Your OpenAI API key should be stored as an environment variable
         $apiKey = 'sk-...Su0A';
        
        try {
            // Prepare cURL request to OpenAI API
            $ch = curl_init('https://api.openai.com/v1/images/generations');
            
            $headers = [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ];
            
            $postData = json_encode([
                'prompt' => $description,
                'n' => 1,
                'size' => '512x512',
            ]);
            
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
            
            curl_close($ch);
            
            $content = json_decode($response, true);
            
            if (isset($content['error'])) {
                throw new \Exception($content['error']['message'] ?? 'Unknown OpenAI error');
            }
            
            // Return the image URL
            return new JsonResponse([
                'success' => true,
                'imageUrl' => $content['data'][0]['url']
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error generating image: ' . $e->getMessage()
            ], 500);
        }
    }
}