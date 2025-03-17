<?php

namespace App\Services;

use App\Models\Participant;
use App\Jobs\SendWhatsAppMessage;
use Illuminate\Support\Facades\Log;

class ParticipantService
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function handleParticipantMessage($participant, $phoneNumber, $messageBody, $isButtonResponse, $data)
    {
        if ($isButtonResponse) {
            if ($messageBody === 'Cadastrar Cupom') {
                dispatch(new SendWhatsAppMessage($phoneNumber, 'Digite o código do seu cupom:'));
                $participant->update(['conversation_step' => 'AWAITING_COUPON']);
            } elseif ($messageBody === 'Verificar Saldo') {
                dispatch(new SendWhatsAppMessage($phoneNumber, 'Seu saldo é de X pontos.'));
                $participant->update(['conversation_step' => 'INITIAL']);
            }
        } else {
            switch ($participant->conversation_step) {
                case 'AWAITING_COUPON':
                    dispatch(new SendWhatsAppMessage($phoneNumber, 'Seu cupom foi cadastrado com sucesso!'));
                    $participant->update(['conversation_step' => 'INITIAL']);
                    break;
                default:
                    $this->sendButtonListMessage($phoneNumber);
            }
        }

        return ['message' => 'Webhook processado'];
    }

    public function sendNotRegisteredMessage($phoneNumber)
    {
        dispatch(new SendWhatsAppMessage($phoneNumber, 'Seu número não está cadastrado como participante.'));
        Log::info("Mensagem enviada para número não cadastrado: {$phoneNumber}");
    }

    public function sendButtonListMessage($phoneNumber)
    {
        $buttons = [
            ['id' => 'option1', 'label' => 'Cadastrar Cupom'],
            ['id' => 'option2', 'label' => 'Verificar Saldo'],
        ];

        $messageBody = "O que você gostaria de fazer?";

        $this->whatsAppService->sendButtonListMessage($phoneNumber, $messageBody, $buttons);

        Log::info("Mensagem com opções enviada para: {$phoneNumber}");
    }
}
