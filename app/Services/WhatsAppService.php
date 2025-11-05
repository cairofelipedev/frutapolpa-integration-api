<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class WhatsAppService
{
    protected $baseUrl;
    protected $instanceId;
    protected $token;
    protected $clientToken;

    public function __construct()
    {
  $this->baseUrl = env('WHATSAPP_BASE_URL', 'https://api.z-api.io');
        $this->instanceId = env('WHATSAPP_INSTANCE_ID');
        $this->token = env('WHATSAPP_TOKEN');
        $this->clientToken = env('WHATSAPP_CLIENT_TOKEN');
    }

    protected function getHeaders()
    {
        return [
            'Client-Token' => $this->clientToken,
            'Content-Type' => 'application/json',
        ];
    }

    protected function buildUrl($endpoint)
    {
        return "{$this->baseUrl}/instances/{$this->instanceId}/token/{$this->token}/{$endpoint}";
    }

    public function sendButtonListMessage($phone, $message, $buttons)
    {
        $url = $this->buildUrl('send-button-list');

        $body = [
            'phone' => $phone,
            'message' => $message,
            'buttonList' => ['buttons' => $buttons],
            'delayTyping' => 3
        ];

        $response = Http::withHeaders($this->getHeaders())->post($url, $body);

        return $response->json();
    }

    public function sendTextMessage($phone, $message)
    {
        $url = $this->buildUrl('send-text');

        $body = [
            'phone' => $phone,
            'message' => $message,
        ];

        $response = Http::withHeaders($this->getHeaders())->post($url, $body);

        return $response->json();
    }

    public function sendImageMessage($phone, $imageUrl, $caption = '', $viewOnce = false)
    {
        $url = $this->buildUrl('send-image');

        $body = [
            'phone' => $phone,
            'image' => $imageUrl,
            'caption' => $caption,
            'viewOnce' => $viewOnce,
        ];

        $response = Http::withHeaders($this->getHeaders())->post($url, $body);

        return $response->json();
    }
}
