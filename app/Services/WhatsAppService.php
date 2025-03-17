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
        $this->baseUrl = 'https://api.z-api.io';
        $this->instanceId = '3DC0E0D64A9790DFD0149E273AF67FF4';
        $this->token = '83EBFA6CEFB51769F56359B0';
        $this->clientToken = 'F0da12f62c208459eab9b5139d3732df5S';
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
}
