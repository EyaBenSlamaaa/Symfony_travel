<?php

namespace App\Controller;

// use GuzzleHttp\Psr7\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

class OpenRouterController extends AbstractController
{
    #[Route('/generateDescription', name: 'chat_endpoint')]
    public function chat(Request $req): JsonResponse{
        
        $data = json_decode($req->getContent(), true);
        $prompt = $data["prompt"] ?? null;
        if (!$prompt) {
            return $this->json([
                'error' => 'Prompt is required',
            ], 400);
        }

        $client = HttpClient::create();
        $response = $client->request('POST', "https://openrouter.ai/api/v1/chat/completions", [
            'headers' => [
                'Authorization' => 'Bearer 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',
                'Content-Type' => 'application/json',
                'HTTP-Referer' => 'Symfony_Travel',
                'X-Title' => 'Description_Generator',
            ],
            'json' => [
                // 'model' => "nousresearch/deephermes-3-mistral-24b-preview:free",
                'model' => "google/gemma-3-27b-it:free",
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => '
                            You are a helpful assistant that generates descriptions for travel destinations.
                            You will be given a travel title and destination and you need to provide a detailed description of it.
                            The description should include information about the location, attractions, culture, and any other relevant details.
                            Please ensure that the description is informative and engaging.
                            The description should be in language provided in title and destination.
                            the response should be text only, no code blocks or any other formatting.
                        '
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ],
                ],
            ],
        ]);
        $data = $response->toArray(false);
        return $this->json([
            'response' => $data['choices'][0]['message']['content'] ?? 'No reply',
        ]);
    }
}
