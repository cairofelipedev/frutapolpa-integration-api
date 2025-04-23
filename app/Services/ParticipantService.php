<?php

namespace App\Services;

use App\Models\Participant;
use App\Models\Coupon;
use App\Jobs\SendWhatsAppMessage;
use App\Models\CouponCode;
use Illuminate\Support\Facades\Log;

class ParticipantService
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    public function handleParticipantMessage(Participant $participant, $phoneNumber, $textMessage, $buttonId, $mediaUrl = null)
    {
        if ($buttonId) {
            return $this->handleButtonMessage($participant, $phoneNumber, $buttonId);
        }

        if ($mediaUrl && $participant->step === 3) {
            return $this->handleImageSubmission($participant, $phoneNumber, $mediaUrl);
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

            Coupon::create([
                'participant_id' => $participant->id,
                'image' => null,
            ]);

            return $this->sendPolpaOptions($phoneNumber);
        }

        if (in_array($buttonId, ['1', '3', '5', '10', '15', '20', 'outro_valor'])) {
            $quantity = is_numeric($buttonId) ? intval($buttonId) : 0;

            $coupon = $participant->coupons()->latest()->first();
            if ($coupon) {
                $coupon->quantity = $quantity;
                $coupon->save();

                $this->generateCouponCodes($participant, $coupon, $quantity);

                $participant->step = 3;
                $participant->save();

                $this->whatsAppService->sendImageMessage(
                    $phoneNumber,
                    "https://cdn.cobrefacil.com.br/website/base/3b1/91c/2bd/modelo-cupom-fiscal-tradicional.png",
                    "Certo! Agora envie a foto do seu comprovante 📸\n\nCaso ele seja muito grande, você pode dobrá-lo, mas lembre-se: as informações da compra das polpas devem estar visíveis."
                );

                // return $this->sendTextMessage($phoneNumber, "Estamos quase lá! Envie agora a foto do seu cupom fiscal para validar sua participação.");
            }

            // return $this->sendTextMessage($phoneNumber, "Erro: cupom não encontrado.");
        }

        return $this->sendInitialOptions($phoneNumber);
    }

    protected function handleTextMessage(Participant $participant, $phoneNumber, $textMessage)
    {
        switch ($participant->step) {
            case 4:
                return $this->processCouponQuantity($participant, $phoneNumber, $textMessage);
            default:
                return $this->sendInitialOptions($phoneNumber);
        }
    }

    protected function handleImageSubmission(Participant $participant, $phoneNumber, $mediaUrl)
    {
        $coupon = $participant->coupons()->latest()->first();

        if ($coupon) {
            $coupon->image = $mediaUrl;
            $coupon->save();

            // Buscar os códigos da sorte já salvos
            $codes = $coupon->codes()->pluck('code')->toArray();

            if (!empty($codes)) {
                $message = "Imagem recebida com sucesso! 🎉\n\nMaravilha! Aqui estão os seus *números da sorte*:\n";
                $message .= implode("\n", $codes);
            } else {
                $message = "Imagem recebida, mas não encontramos os cupons gerados. Tente novamente ou fale com o suporte.";
            }
            
            $this->sendTextMessage($phoneNumber, $message);

            $participant->step = 0;
            $participant->save();

            Log::info("Imagem salva no cupom ID: {$coupon->id}, participante: {$participant->id}");
        } else {
            Log::warning("Nenhum cupom encontrado para participante ID: {$participant->id}");
            $this->sendTextMessage($phoneNumber, "Não encontramos um cupom ativo para salvar essa imagem. Tente novamente.");
        }

        return response()->json(['status' => 'image saved']);
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
            ['id' => 'cadastrar_cupom', 'label' => 'Cadastrar novo cupom'],
        ];

        return $this->whatsAppService->sendButtonListMessage($phoneNumber, "Identifiquei que você já possui cadastro na promoção *Polpa Premiada 2025*! 🎉\n\nO que você deseja fazer?", $buttons);
    }

    public function sendNotRegisteredMessage($phoneNumber)
    {
        $message = "🍓 *Bem-vindo à Polpa Premiada 2025, da Fruta Polpa!* 🎉  \n\nVocê está a um passo de concorrer a prêmios incríveis com a *Melhor polpa de frutas do Brasil*!  \n\n👉 Cadastre-se agora mesmo — é rápido e fácil:  *frutapolpa.com.br/participe* e clique em \"Fazer meu primeiro cadastro\" e pronto.\n\n💥 Aproveite nossa promoção, quanto mais você compra, mais chances tem de ganhar!";
        dispatch(new SendWhatsAppMessage($phoneNumber, $message));

        Log::info("Mensagem enviada para número não cadastrado: {$phoneNumber}");
    }
    protected function sendPolpaOptions($phoneNumber)
    {
        $buttons = [
            // ['id' => '1', 'label' => '1'],
            ['id' => '3', 'label' => '3'],
            ['id' => '5', 'label' => '5'],
            ['id' => '10', 'label' => '10'],
            ['id' => '15', 'label' => '15'],
            ['id' => '20', 'label' => '20'],
            // ['id' => 'outro_valor', 'label' => 'Outro valor'],
        ];

        return $this->whatsAppService->sendButtonListMessage(
            $phoneNumber,
            "Quantas polpas você comprou?",
            $buttons
        );
    }

    protected function generateCouponCodes(Participant $participant, Coupon $coupon, int $quantity)
    {
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
            $code = rand(100000, 999999);

            CouponCode::create([
                'participant_id' => $participant->id,
                'coupon_id' => $coupon->id,
                'code' => $code,
            ]);

            $generatedCoupons[] = $code;
        }

        // if (count($generatedCoupons)) {
        //     $this->sendTextMessage($participant->phone, "Maravilha! Estes são seus cupons da sorte:\n" . implode("\n", $generatedCoupons));
        // }
    }
}
