<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Participant;
use App\Services\ParticipantService;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function handleCallback(Request $request, ParticipantService $participantService)
    {
        $data = $request->all();
        $phoneNumber = $data['phone'];
        $messageBody = $data['text']['message'] ?? $data['buttonsResponseMessage']['message'] ?? null;
        $isButtonResponse = isset($data['buttonsResponseMessage']);

        // Verifica se o número está cadastrado como participante
        $participant = Participant::where('phone', $phoneNumber)->first();

        if (!$participant) {
            // Envia mensagem informando que o número não está cadastrado
            $participantService->sendNotRegisteredMessage($phoneNumber);
            return response()->json(['message' => 'Número não cadastrado'], 403);
        }

        return response()->json(
            $participantService->handleParticipantMessage($participant, $phoneNumber, $messageBody, $isButtonResponse, $data)
        );
    }
}
