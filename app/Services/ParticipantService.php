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

        if (in_array($buttonId, ['3', '6', '9', '12', '15'])) {
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

                return $this->sendTextMessage($phoneNumber, "Estamos quase lá! Envie agora a foto do seu cupom fiscal para validar sua participação.");
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

            $codes = $coupon->codes()->pluck('code')->toArray();

            if (!empty($codes)) {
                $message = "Obrigado por participar! Continue comprando Fruta Polpa e aumente a sua sorte para o próximo sorteio. *Fruta Polpa, a melhor!*🎉\n\nAqui estão os seus *números da sorte*:\n";
                $message .= implode("\n", $codes);
                $message .= "\n\n👉 Acompanhe aqui seus números da sorte, acesse com seu login e senha cadastrados no site  *https://frutapolpa.com.br/admin/login.php*";
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

        $coupon = $participant->coupons()->latest()->first();

        if (!$coupon) {
            return $this->sendTextMessage($phoneNumber, "Erro: nenhum cupom ativo encontrado.");
        }

        // Pega os códigos já salvos no banco
        $codes = $coupon->codes()->pluck('code')->toArray();

        if (!empty($codes)) {
            $message = "Maravilha! Estes são seus cupons da sorte:\n" . implode("\n", $codes);
        } else {
            $message = "Não encontramos cupons gerados. Tente novamente ou fale com o suporte.";
        }

        $this->sendTextMessage($phoneNumber, $message);

        $participant->step = 0;
        $participant->save();

        return response()->json(['status' => 'coupons sent', 'coupons' => $codes]);
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
        $message = "🍓 *Bem-vindo à Polpa Premiada 2025, da Fruta Polpa!* 🎉\n\nVocê está a um passo de concorrer a uma moto 0 km com a *Melhor polpa de frutas do Brasil*! 🚀\n\n👉 Gostaria de iniciar seu cadastro?";

        $buttons = [
            ['id' => 'register_yes', 'label' => 'SIM'],
            ['id' => 'register_no', 'label' => 'NÃO'],
        ];

        return $this->whatsAppService->sendButtonListMessage($phoneNumber, $message, $buttons);
    }


    protected function sendPolpaOptions($phoneNumber)
    {
        $buttons = [
            // ['id' => '1', 'label' => '1'],
            ['id' => '3', 'label' => '3'],
            ['id' => '6', 'label' => '6'],
            ['id' => '9', 'label' => '9'],
            ['id' => '12', 'label' => '12'],
            ['id' => '15', 'label' => '15'],
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
        $couponCount = min(intdiv($quantity, 3), 5);

        $generatedCoupons = [];

        for ($i = 0; $i < $couponCount; $i++) {
            $code = $this->generateUniqueCode();

            CouponCode::create([
                'participant_id' => $participant->id,
                'coupon_id'      => $coupon->id,
                'code'           => $code,
            ]);

            $generatedCoupons[] = $code;
        }

        return $generatedCoupons;
    }

    protected function generateUniqueCode()
    {
        do {
            $code = rand(100000, 999999);
        } while (CouponCode::where('code', $code)->exists());

        return $code;
    }

    public function handleNewParticipantFlow($phoneNumber, $textMessage, $buttonId = null)
    {
        $participant = Participant::where('phone', $phoneNumber)->first();

        // Se ainda não existe participante → primeira interação
        if (!$participant) {
            // Se clicou em botão
            if ($buttonId === 'register_yes') {
                $participant = Participant::create([
                    'phone' => $phoneNumber,
                    'step_register' => 1,
                ]);

                return $this->sendTextMessage(
                    $phoneNumber,
                    "Ótimo! Vamos começar seu cadastro 🎉\nQual o seu *nome completo*?"
                );
            }

            if ($buttonId === 'register_no') {
                return $this->sendTextMessage(
                    $phoneNumber,
                    "Tudo bem! Caso queira participar depois, é só mandar uma mensagem por aqui 🍓"
                );
            }

            // Primeira mensagem → exibe menu de boas-vindas com botões
            return $this->sendNotRegisteredMessage($phoneNumber);
        }

        // Já existe participante em processo de cadastro
        switch ($participant->step_register) {
            case 1:
                $this->saveName($participant, $textMessage);
                $participant->step_register = 2;
                $participant->save();
                return $this->sendTextMessage($phoneNumber, "Perfeito! Agora me informe o seu *CEP* 🏠");

            case 2:
                $participant->cep = $textMessage;
                $participant->step_register = 3;
                $participant->save();
                return $this->sendTextMessage($phoneNumber, "Obrigado! Qual é a sua *cidade*?");

            case 3:
                $participant->city = $textMessage;
                $participant->step_register = 4;
                $participant->save();
                return $this->sendTextMessage($phoneNumber, "Beleza! Agora digite o seu *bairro*");

            case 4:
                $participant->neighborhood = $textMessage;
                $participant->step_register = 0; // cadastro finalizado
                $participant->save();

                $this->sendTextMessage($phoneNumber, "🎉 Cadastro concluído com sucesso! Agora você já pode cadastrar seus cupons.");

                // 👉 já cai no fluxo normal
                return $this->sendInitialOptions($phoneNumber);
        }

        return response()->json(['status' => 'awaiting register']);
    }

    protected function saveName(Participant $participant, $fullName)
    {
        $participant->first_name = trim($fullName); // nome completo direto aqui
        $participant->save();
    }
}
