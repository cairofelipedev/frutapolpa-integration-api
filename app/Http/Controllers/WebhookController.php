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

    public function handleCallback(Request $request)
    {
        $data = $request->all();
        Log::info('Webhook recebido', $data);

        $phoneNumber = $data['phone'];
        $participant = Participant::where('phone', $phoneNumber)->first();

        if (!$participant) {
            Log::warning('Participante não encontrado ou inativo.', ['phone' => $phoneNumber]);
            $this->participantService->sendNotRegisteredMessage($phoneNumber);
            return response()->json(['status' => 'error', 'message' => 'Participante não encontrado ou inativo.']);
        }

        return $this->participantService->handleParticipantMessage(
            $participant,
            $phoneNumber,
            $data['text']['message'] ?? null,
            $data['buttonsResponseMessage']['buttonId'] ?? null
        );
    }
}
