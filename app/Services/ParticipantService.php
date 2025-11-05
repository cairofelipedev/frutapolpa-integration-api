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

        if ($mediaUrl && $participant->step === 2) {
            return $this->handleImageSubmission($participant, $phoneNumber, $mediaUrl);
        }

        if ($textMessage) {
            return $this->handleTextMessage($participant, $phoneNumber, $textMessage);
        }

        return response()->json(['status' => 'ignored']);
    }

    protected function handleButtonMessage(Participant $participant, $phoneNumber, $buttonId)
    {
        // 👉 Novo fluxo da corrida
        if ($buttonId === 'acompanhar_inscricao') {
            $participant->step = 1;
            $participant->save();

            $this->whatsAppService->sendImageMessage(
                $phoneNumber,
                "https://frutapolpa.365chats.com/cupom.jpeg", // substitua pelo link da imagem do QR Code PIX
                "🏃‍♀️ *Inscrição na Corrida APCEF 2025*\n\nO valor da inscrição é de *R$ 90,00*.\n\nEnvie o comprovante de pagamento para o *PIX*: `apcef@gmail.com` 💳\n\nAssim que realizar o pagamento, envie aqui a *imagem do comprovante* para concluirmos sua inscrição."
            );

            $participant->step = 2;
            $participant->save();

            return $this->sendTextMessage($phoneNumber, "Após o pagamento, envie a *foto do comprovante* aqui 📸");
        }

        return $this->sendInitialOptions($phoneNumber);
    }



    protected function handleTextMessage(Participant $participant, $phoneNumber, $textMessage)
    {
        // Nenhum texto esperado neste fluxo (somente imagem)
        return $this->sendInitialOptions($phoneNumber);
    }


    protected function handleImageSubmission(Participant $participant, $phoneNumber, $mediaUrl)
    {
        // Salvar o comprovante no campo image (se quiser)
        $participant->step = 0;
        $participant->save();

        $this->sendTextMessage(
            $phoneNumber,
            "✅ Obrigado, *{$participant->first_name}*! Recebemos o seu comprovante de pagamento.\n\nSua inscrição na *Corrida APCEF 2025* foi registrada com sucesso! 🏅\n\nEntraremos em contato caso seja necessário confirmar alguma informação."
        );

        Log::info("📎 Comprovante recebido de {$participant->first_name} ({$participant->phone}) - {$mediaUrl}");

        return response()->json(['status' => 'payment proof received']);
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
            ['id' => 'acompanhar_inscricao', 'label' => 'Acompanhar inscrição 🏃‍♀️'],
        ];

        return $this->whatsAppService->sendButtonListMessage(
            $phoneNumber,
            "🏃‍♂️ Olá, *{$firstName}!* 👋\nBem-vindo à *Corrida APCEF 2025*! 🏅\n\nO que você gostaria de fazer?",
            $buttons
        );
    }

    public function sendNotRegisteredMessage($phoneNumber, $senderName = null)
    {
        $firstName = $senderName ? explode(' ', trim($senderName))[0] : 'participante';

        $message = "🏃‍♂️ *Olá, {$firstName}!* 🎉\n\nBem-vindo à *Corrida APCEF 2025*! 🏅\n\nParticipe dessa experiência incrível de esporte e bem-estar! 💪\n\nA inscrição tem o valor de *R$ 90,00*, e é necessário preencher alguns dados para continuar.\n\n👉 Deseja iniciar sua inscrição agora?";

        $buttons = [
            ['id' => 'register_yes', 'label' => 'SIM, quero me inscrever'],
            ['id' => 'register_no', 'label' => 'NÃO no momento'],
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
                    "Tudo bem! Caso queira participar depois, é só mandar uma mensagem por aqui 🏃‍♂️"
                );
            }

            return $this->sendNotRegisteredMessage($phoneNumber, $senderName);
        }

        switch ($participant->step_register) {
            case 1:
                // Recebe o nome completo
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

                    return $this->sendTextMessage($phoneNumber, "Perfeito, *{$firstName}*! Agora me informe o seu *CPF* (apenas números).");
                }

                if ($buttonId === 'confirm_name_no') {
                    $participant->full_name = null;
                    $participant->save();

                    return $this->sendTextMessage($phoneNumber, "Sem problemas! Me diga novamente o seu *nome completo* 😊");
                }

                break;

            case 2:
                // CPF com validação
                $cpf = preg_replace('/\D/', '', $textMessage);

                if (strlen($cpf) !== 11 || !$this->isValidCPF($cpf)) {
                    return $this->sendTextMessage($phoneNumber, "❌ CPF inválido. Informe um CPF válido, *{$participant->first_name}*.");
                }

                $participant->cpf = $cpf;
                $participant->step_register = 3;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Show, *{$participant->first_name}*! Agora me diga a sua *idade* 🕐");

            case 3:
                // Idade
                $idade = intval(trim($textMessage));
                if ($idade <= 0) {
                    return $this->sendTextMessage($phoneNumber, "Por favor, informe uma *idade válida*, *{$participant->first_name}*.");
                }

                $participant->cep = $idade;
                $participant->step_register = 4;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Certo! Agora, qual é o nome da sua *equipe*?");

            case 4:
                // Equipe
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Digite o nome da sua *equipe*, *{$participant->first_name}*.");
                }

                $participant->last_name = trim($textMessage);
                $participant->step_register = 5;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Perfeito! Agora me informe o *patrocinador* (se houver). Se não tiver, digite *Nenhum*.");

            case 5:
                // Patrocinador
                $participant->state = trim($textMessage);
                $participant->step_register = 6;
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Beleza! Agora informe o nome do seu *plano de saúde* 🏥");

            case 6:
                // Plano de saúde
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Por favor, digite o nome do seu *plano de saúde*, *{$participant->first_name}*.");
                }

                $participant->city = trim($textMessage);
                $participant->step_register = 0;
                $participant->save();

                $this->sendTextMessage($phoneNumber, "✅ Cadastro concluído com sucesso, *{$participant->first_name}*! Agora vamos iniciar sua *inscrição na corrida* 🏃‍♂️");
                // Aqui começa o fluxo de corrida no handleParticipantMessage
                return response()->json(['status' => 'register complete']);
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
