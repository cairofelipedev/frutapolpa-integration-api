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
                    "https://frutapolpa.365chats.com/cupom.jpeg",
                    "Certo! Agora envie a foto do seu comprovante 📸\n\nCaso ele seja muito grande, você pode dobrá-lo, mas lembre-se: as informações da compra das polpas devem estar visíveis."
                );

                return $this->sendTextMessage($phoneNumber, "Estamos quase lá! Envie agora a foto do seu cupom fiscal para validar sua participação.");
            }
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
                $message = "Obrigado por participar, *{$participant->first_name}*! 🎉\n\nContinue comprando Fruta Polpa e aumente a sua sorte para o próximo sorteio. 🍓\n\nSeus *números da sorte* são:\n";
                $message .= implode("\n", $codes);
                $message .= "\n\n👉 Cadastre um novo cupom sempre que quiser para aumentar suas chances de ganhar!";
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

        $codes = $coupon->codes()->pluck('code')->toArray();

        if (!empty($codes)) {
            $message = "Perfeito, *{$participant->first_name}*! Estes são seus cupons da sorte:\n" . implode("\n", $codes);
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
        $participant = Participant::where('phone', $phoneNumber)->first();
        $firstName = $participant && $participant->first_name ? $participant->first_name : 'participante';

        $buttons = [
            ['id' => 'cadastrar_cupom', 'label' => 'Cadastrar novo cupom'],
        ];

        return $this->whatsAppService->sendButtonListMessage(
            $phoneNumber,
            "🍓 Olá, *{$firstName}!* 👋\nBem-vindo novamente à *Apcef*! 🎉\n\nO que você deseja fazer?",
            $buttons
        );
    }

    public function sendNotRegisteredMessage($phoneNumber, $senderName = null)
    {
        $firstName = $senderName ? explode(' ', trim($senderName))[0] : 'participante';

        $message = "🍓 *Olá, {$firstName}!* 🎉\n\nBem-vindo à *Polpa Premiada 2025, da Fruta Polpa!* 🎁\n\nVocê está a um passo de concorrer a uma *Moto 0 km* 🚀 com a *Melhor polpa de frutas do Brasil*! 😍\n\n👉 Gostaria de iniciar seu cadastro?";

        $buttons = [
            ['id' => 'register_yes', 'label' => 'SIM'],
            ['id' => 'register_no', 'label' => 'NÃO'],
        ];

        return $this->whatsAppService->sendButtonListMessage($phoneNumber, $message, $buttons);
    }

    protected function sendPolpaOptions($phoneNumber)
    {
        $buttons = [
            ['id' => '3', 'label' => '3'],
            ['id' => '6', 'label' => '6'],
            ['id' => '9', 'label' => '9'],
            ['id' => '12', 'label' => '12'],
            ['id' => '15', 'label' => '15'],
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

    public function handleNewParticipantFlow($phoneNumber, $textMessage, $buttonId = null, $senderName = null)
    {
        $participant = Participant::where('phone', $phoneNumber)->first();

        if (!$participant) {
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

            return $this->sendNotRegisteredMessage($phoneNumber, $senderName);
        }

        switch ($participant->step_register) {
            case 1:
                if ($buttonId === null && trim($textMessage) !== '') {
                    $participant->full_name = trim($textMessage);
                    $participant->save();

                    $buttons = [
                        ['id' => 'confirm_name_yes', 'label' => 'SIM'],
                        ['id' => 'confirm_name_no', 'label' => 'NÃO'],
                    ];

                    return $this->whatsAppService->sendButtonListMessage(
                        $phoneNumber,
                        "Confirme, este é seu nome completo?",
                        $buttons
                    );
                }

                if ($buttonId === 'confirm_name_yes') {
                    $fullName = trim($participant->full_name);
                    $firstName = explode(' ', $fullName)[0] ?? '';

                    $participant->first_name = $firstName;
                    $participant->step_register = 2;
                    $participant->save();

                    return $this->sendTextMessage($phoneNumber, "Perfeito, *{$firstName}*! Agora me informe o seu *CEP* 🏠");
                }

                if ($buttonId === 'confirm_name_no') {
                    $participant->full_name = null;
                    $participant->save();

                    return $this->sendTextMessage($phoneNumber, "Sem problemas! Me diga novamente o seu *nome completo* 😊");
                }

                break;

            case 2:
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Por favor, informe seu *CEP*, *{$participant->first_name}*.");
                }

                $participant->cep = trim($textMessage);
                $participant->step_register = 3;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Obrigado, *{$participant->first_name}*! Qual é o seu *Estado*?");

            case 3:
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Digite o *Estado*, *{$participant->first_name}*.");
                }

                $participant->state = trim($textMessage);
                $participant->step_register = 4;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Beleza, *{$participant->first_name}*! Agora digite a sua *Cidade*.");

            case 4:
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Informe o *Bairro*, *{$participant->first_name}*.");
                }

                $participant->neighborhood = trim($textMessage);
                $participant->step_register = 5;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Certo, *{$participant->first_name}*! Agora preciso do seu *CPF* (apenas números).");

            case 5:
                $cpf = preg_replace('/\D/', '', $textMessage);

                if (strlen($cpf) !== 11 || !$this->isValidCPF($cpf)) {
                    return $this->sendTextMessage($phoneNumber, "❌ CPF inválido. Informe um CPF válido, *{$participant->first_name}*.");
                }

                $participant->cpf = $cpf;
                $participant->step_register = 6;
                $participant->save();

                $buttons = [
                    ['id' => 'privacy_yes', 'label' => 'SIM'],
                    ['id' => 'privacy_no', 'label' => 'NÃO'],
                ];

                return $this->whatsAppService->sendButtonListMessage(
                    $phoneNumber,
                    "📜 {$participant->first_name}, para finalizar seu cadastro, você autoriza o uso dos seus dados conforme a LGPD?",
                    $buttons
                );

            case 6:
                if ($buttonId === 'privacy_yes') {
                    $participant->step_register = 0;
                    $participant->save();

                    $this->sendTextMessage($phoneNumber, "🎉 Cadastro concluído com sucesso, *{$participant->first_name}*! Agora você já pode cadastrar seus cupons.");
                    return $this->sendInitialOptions($phoneNumber);
                }

                if ($buttonId === 'privacy_no') {
                    $participant->step_register = 0;
                    $participant->save();

                    return $this->sendTextMessage($phoneNumber, "😢 Tudo bem, *{$participant->first_name}*! Sem aceitar a política de privacidade não é possível participar da promoção.");
                }

                return $this->sendTextMessage($phoneNumber, "Por favor, escolha uma opção: SIM ou NÃO, *{$participant->first_name}*.");
        }

        return response()->json(['status' => 'awaiting register']);
    }

    protected function isValidCPF($cpf)
    {
        $cpf = preg_replace('/\D/', '', $cpf);
        if (strlen($cpf) != 11 || preg_match('/(\d)\1{10}/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $d = 0;
            for ($c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $digito = ((10 * $d) % 11) % 10;
            if ($cpf[$t] != $digito) {
                return false;
            }
        }

        return true;
    }
}
