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

    public function handleParticipantMessage(Participant $participant, $phoneNumber, $textMessage, $buttonId)
    {
        if ($buttonId) {
            return $this->handleButtonMessage($participant, $phoneNumber, $buttonId);
        }

        if ($textMessage) {
            return $this->handleTextMessage($participant, $phoneNumber, $textMessage);
        }

        return response()->json(['status' => 'ignored']);
    }

    protected function handleButtonMessage(Participant $participant, $phoneNumber, $buttonId)
    {
        if ($buttonId === 'cadastrar_cupom') {
            $participant->step = 1;
            $participant->save();
            return $this->sendTextMessage($phoneNumber, 'Quantos cupons você deseja cadastrar?');
        }

        return $this->sendInitialOptions($phoneNumber);
    }

    protected function handleTextMessage(Participant $participant, $phoneNumber, $textMessage)
    {
        switch ($participant->step) {
            case 1:
                return $this->processCouponQuantity($participant, $phoneNumber, $textMessage);
            default:
                return $this->sendInitialOptions($phoneNumber);
        }
    }

    protected function processCouponQuantity(Participant $participant, $phoneNumber, $quantity)
    {
        $quantity = intval($quantity);
        $couponCount = 0;

        if ($quantity >= 3 && $quantity < 5) {
            $couponCount = 1;
        } elseif ($quantity >= 5 && $quantity < 10) {
            $couponCount = 2;
        } elseif ($quantity >= 10) {
            $couponCount = 3;
        }

        $generatedCoupons = [];
        for ($i = 0; $i < $couponCount; $i++) {
            $generatedCoupons[] = rand(100000, 999999);
        }

        $message = "Maravilha! Estes são seus cupons da sorte:\n" . implode("\n", $generatedCoupons);
        $this->sendTextMessage($phoneNumber, $message);

        $participant->step = 0;
        $participant->save();

        return response()->json(['status' => 'coupons generated']);
    }

    protected function sendTextMessage($phoneNumber, $messageBody)
    {
        dispatch(new SendWhatsAppMessage($phoneNumber, $messageBody));
    }

    protected function sendInitialOptions($phoneNumber)
    {
        $buttons = [
            ['id' => 'cadastrar_cupom', 'label' => 'Cadastrar Cupom'],
        ];

        return $this->whatsAppService->sendButtonListMessage($phoneNumber, "O que você deseja fazer?", $buttons);
    }

    public function sendNotRegisteredMessage($phoneNumber)
    {
        $message = "Olá, seu número não se encontra cadastrado. Para participar, acesse agora *frutapolpa.com.br/participe*, preencha seu cadastro e comece a participar!";
        dispatch(new SendWhatsAppMessage($phoneNumber, $message));

        Log::info("Mensagem enviada para número não cadastrado: {$phoneNumber}");
    }
}
