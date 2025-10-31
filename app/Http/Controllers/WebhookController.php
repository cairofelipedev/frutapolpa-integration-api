<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Participant;
use App\Services\ParticipantService;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    protected $participantService;

    public function __construct(ParticipantService $participantService)
    {
        $this->participantService = $participantService;
    }

    protected $allowedPhones = [
        '558681931714',
        '558699598080',
    ];

    public function handleCallback(Request $request)
    {
        $data = $request->all();
        Log::info('Webhook recebido', $data);

        $phoneNumber = $data['phone'] ?? null;
        $senderName = $data['senderName'] ?? null;

        if (!in_array($phoneNumber, $this->allowedPhones)) {
            Log::warning("Número não autorizado: {$phoneNumber}");
            return response()->json(['status' => 'ignored']);
        }


        $participant = Participant::where('phone', $phoneNumber)->first();

        if ($participant) {
            $participant->update(['last_message_at' => now()]);
        }

        Log::info('passou allowedPhones', ['phone' => $phoneNumber]);

        if (!$participant || $participant->step_register != 0) {
            Log::info('Entrando em handleNewParticipantFlow', ['phone' => $phoneNumber]);
            return $this->participantService->handleNewParticipantFlow(
                $phoneNumber,
                $data['text']['message'] ?? null,
                $data['buttonsResponseMessage']['buttonId'] ?? null,
                $senderName
            );
        }

        Log::info('Entrando em handleParticipantMessage', ['phone' => $phoneNumber]);
        return $this->participantService->handleParticipantMessage(
            $participant,
            $phoneNumber,
            $data['text']['message'] ?? null,
            $data['buttonsResponseMessage']['buttonId'] ?? null,
            $data['image']['imageUrl'] ?? null
        );
    }
}
