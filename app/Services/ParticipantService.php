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

        // 1. PASSO (MENSAGEM INICIAL)
        $message = "🍓 *Olá, {$firstName}!* 🎉\n\nBem-vindo à *XIV CORRIDA FENAE DO PESSOAL DA CAIXA 2025* 🎁\n\nVocê está a um passo de preencher sua ficha de inscrição!* 🚀  😍\n\n👉 Gostaria de iniciar seu cadastro?";

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
                'coupon_id' => $coupon->id,
                'code' => $code,
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
    
    // =================================================================================================================================
    // FUNÇÕES AUXILIARES PARA O NOVO FLUXO DE CORRIDA (ADICIONADAS PARA SUPORTAR A LÓGICA DE CATEGORIA)
    // =================================================================================================================================

    protected function getFaixasEtarias($cat_base, $percurso)
    {
        $map = [
            'cat_geral' => [
                '5 km' => [['id' => 'A', 'label' => '15 a 17 ANOS (Teen)'], ['id' => 'B', 'label' => '18 a 29 ANOS'], ['id' => 'C', 'label' => '30 a 39 ANOS'], ['id' => 'D', 'label' => '40 a 49 ANOS'], ['id' => 'E', 'label' => '50 a 59 ANOS'], ['id' => 'F', 'label' => '60 a 69 ANOS'], ['id' => 'G', 'label' => '70 OU MAIS']],
                '10 km' => [['id' => 'H', 'label' => '18 a 29 ANOS'], ['id' => 'I', 'label' => '30 a 39 ANOS'], ['id' => 'J', 'label' => '40 a 49 ANOS'], ['id' => 'K', 'label' => '50 a 59 ANOS'], ['id' => 'L', 'label' => '60 a 69 ANOS'], ['id' => 'M', 'label' => '70 OU MAIS']],
            ],
            'cat_socio' => [
                '5 km' => [['id' => 'N', 'label' => '18 a 39 ANOS'], ['id' => 'O', 'label' => '40 a 50 ANOS'], ['id' => 'P', 'label' => '51 a 60 ANOS'], ['id' => 'Q', 'label' => '61 OU MAIS']],
                '10 km' => [['id' => 'R', 'label' => '18 a 39 ANOS'], ['id' => 'S', 'label' => '40 a 50 ANOS'], ['id' => 'T', 'label' => '51 a 60 ANOS'], ['id' => 'U', 'label' => '61 OU MAIS']],
            ],
        ];
        return $map[$cat_base][$percurso] ?? [];
    }
    
    protected function getCategoriaDescription($code)
    {
        $descriptions = [
            'A' => 'Público Geral 15-17 (5 km)', 'B' => 'Público Geral 18-29 (5 km)', 'C' => 'Público Geral 30-39 (5 km)',
            'D' => 'Público Geral 40-49 (5 km)', 'E' => 'Público Geral 50-59 (5 km)', 'F' => 'Público Geral 60-69 (5 km)',
            'G' => 'Público Geral 70+ (5 km)', 'H' => 'Público Geral 18-29 (10 km)', 'I' => 'Público Geral 30-39 (10 km)',
            'J' => 'Público Geral 40-49 (10 km)', 'K' => 'Público Geral 50-59 (10 km)', 'L' => 'Público Geral 60-69 (10 km)',
            'M' => 'Público Geral 70+ (10 km)', 'N' => 'Sócio Efetivo 18-39 (5 km)', 'O' => 'Sócio Efetivo 40-50 (5 km)',
            'P' => 'Sócio Efetivo 51-60 (5 km)', 'Q' => 'Sócio Efetivo 61+ (5 km)', 'R' => 'Sócio Efetivo 18-39 (10 km)',
            'S' => 'Sócio Efetivo 40-50 (10 km)', 'T' => 'Sócio Efetivo 51-60 (10 km)', 'U' => 'Sócio Efetivo 61+ (10 km)',
            'V' => 'PCD (5 km)',
        ];
        return $descriptions[$code] ?? 'Categoria Desconhecida';
    }

    // =================================================================================================================================

    public function handleNewParticipantFlow($phoneNumber, $textMessage, $buttonId = null, $senderName = null)
    {
        $participant = Participant::where('phone', $phoneNumber)->first();

        if (!$participant) {
            // 1. PASSO: Resposta ao botão SIM/NÃO da mensagem inicial
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
                // 2. PASSO: Recebe o Nome Completo E Pede Confirmação
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

                // 3. PASSO: Confirmação do Nome
                if ($buttonId === 'confirm_name_yes') {
                    $fullName = trim($participant->full_name);
                    $firstName = explode(' ', $fullName)[0] ?? '';

                    $participant->first_name = $firstName;
                    $participant->step_register = 2; // << NOVO PASSO: CPF
                    $participant->save();

                    // MENSAGEM ALTERADA PARA SOLICITAR CPF NOVO
                    return $this->sendTextMessage($phoneNumber, "Perfeito, *{$firstName}*! Agora me informe o seu *CPF* (somente números) 😀");
                }

                if ($buttonId === 'confirm_name_no') {
                    $participant->full_name = null;
                    $participant->save();

                    return $this->sendTextMessage($phoneNumber, "Sem problemas! Me diga novamente o seu *nome completo* 😊");
                }

                return $this->sendTextMessage($phoneNumber, "Por favor, digite seu nome completo.");

            case 2:
                // NOVO PASSO 4: Recebe e Valida o CPF
                $cpf = preg_replace('/\D/', '', $textMessage);

                if (strlen($cpf) !== 11 || !$this->isValidCPF($cpf)) {
                    return $this->sendTextMessage($phoneNumber, "❌ CPF inválido. Informe um CPF válido (somente 11 números), *{$participant->first_name}*.");
                }

                $participant->cpf = $cpf;
                $participant->step_register = 3; // << AVANÇA PARA CATEGORIA BASE
                $participant->save();

                // NOVO PASSO 5: Solicitar Categoria Base (Público Geral/Sócio/PCD)
                $buttons = [
                    ['id' => 'cat_geral', 'label' => 'Público Geral'],
                    ['id' => 'cat_socio', 'label' => 'Sócio Efetivo (Caixa)'],
                    ['id' => 'cat_pcd', 'label' => 'PCD (Pessoa com Deficiência)'],
                ];
                
                return $this->whatsAppService->sendButtonListMessage(
                    $phoneNumber,
                    "Obrigado, *{$participant->first_name}*! Agora, em qual destas categorias você se enquadra?",
                    $buttons
                );

            case 3:
                // NOVO PASSO 6: Processa a Categoria Base
                $firstName = $participant->first_name;
                
                // FLUXO RÁPIDO PCD (Define Categoria V)
                if ($buttonId === 'cat_pcd') {
                    $participant->categoria = 'V'; 
                    $participant->step_register = 6; // PULA PARA CEP (antigo case 2)
                    $participant->save();
                    
                    return $this->sendTextMessage($phoneNumber, "Ótimo! Sua categoria foi definida como *PCD (5 km)*. Vamos para os dados pessoais. Agora, qual o seu **CEP** 🏠");
                }
                
                // FLUXO NORMAL (Geral ou Sócio) -> Pede Percurso
                if (in_array($buttonId, ['cat_geral', 'cat_socio'])) {
                    
                    $participant->temp_cat_base = $buttonId; 
                    $participant->step_register = 4; // AVANÇA PARA PERCURSO
                    $participant->save();

                    $buttons = [
                        ['id' => 'percurso_5km', 'label' => '5 km'],
                        ['id' => 'percurso_10km', 'label' => '10 km'],
                    ];
                    
                    return $this->whatsAppService->sendButtonListMessage(
                        $phoneNumber,
                        "Ótimo! Registrado. Agora, me informe qual o tamanho do percurso que deseja concorrer:",
                        $buttons
                    );
                }
                
                return $this->sendTextMessage($phoneNumber, "Por favor, escolha uma das categorias usando os botões.");
                
            case 4:
                // NOVO PASSO 7: Recebe o Percurso e Pede a Faixa Etária
                $percurso = null;
                if ($buttonId === 'percurso_5km') {
                    $percurso = '5 km';
                } elseif ($buttonId === 'percurso_10km') {
                    $percurso = '10 km';
                }
                
                if ($percurso) {
                    $cat_base = $participant->temp_cat_base;
                    $faixas = $this->getFaixasEtarias($cat_base, $percurso);
                    
                    $participant->temp_percurso = $percurso;
                    $participant->step_register = 5; // AVANÇA PARA FAIXA ETÁRIA
                    $participant->save();

                    return $this->whatsAppService->sendButtonListMessage(
                        $phoneNumber,
                        "Obrigado por informar! Agora, preciso que me informe qual faixa etária de idade você possui:",
                        $faixas
                    );
                }
                
                return $this->sendTextMessage($phoneNumber, "Por favor, escolha uma opção de percurso usando os botões (5 km ou 10 km).");

            case 5:
                // NOVO PASSO 8: Recebe a Faixa Etária e Finaliza a Categoria
                $categoria_final = $buttonId;
                
                // Salva a Categoria Final
                $participant->categoria = $categoria_final;
                $participant->temp_cat_base = null;
                $participant->temp_percurso = null;
                
                $participant->step_register = 6; // AVANÇA PARA CEP (início dos campos pessoais)
                $participant->save();
                
                $categoria_desc = $this->getCategoriaDescription($categoria_final);
                
                return $this->sendTextMessage($phoneNumber, "Excelente! Sua categoria (*{$categoria_desc}*) foi definida. Vamos para os dados pessoais. Qual o seu **CEP** 🏠");

            case 6:
                // ANTIGO case 2: Recebe o CEP (Deslocado)
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Por favor, informe seu *CEP*, *{$participant->first_name}*.");
                }

                $participant->cep = trim($textMessage);
                $participant->step_register = 7; // Próximo: ESTADO
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Obrigado, *{$participant->first_name}*! Qual é o seu *Estado*?");

            case 7:
                // ANTIGO case 3: Recebe o Estado (Deslocado)
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Digite o *Estado*, *{$participant->first_name}*.");
                }

                $participant->state = trim($textMessage);
                $participant->step_register = 8; // Próximo: CIDADE
                $participant->save();

                return $this->sendTextMessage($phoneNumber, "Beleza, *{$participant->first_name}*! Agora digite a sua *Cidade*.");

            case 8:
                // ANTIGO case 4: Recebe a Cidade (Deslocado)
                if (trim($textMessage) === '') {
                    return $this->sendTextMessage($phoneNumber, "Informe o *Bairro*, *{$participant->first_name}*.");
                }

                $participant->neighborhood = trim($textMessage);
                $participant->step_register = 9; // Próximo: CPF (AGORA TELEFONE?)
                $participant->save();

                // *Aviso*: O case 4 original pedia Bairro, mas a mensagem falava em Bairro. Aqui o próximo passo deve ser o que você precisa.
                return $this->sendTextMessage($phoneNumber, "Certo, *{$participant->first_name}*! Agora preciso do seu *CPF* (apenas números).");


            case 9:
                // ANTIGO case 5: Recebe o CPF (Deslocado)
                $cpf = preg_replace('/\D/', '', $textMessage);

                if (strlen($cpf) !== 11 || !$this->isValidCPF($cpf)) {
                    return $this->sendTextMessage($phoneNumber, "❌ CPF inválido. Informe um CPF válido, *{$participant->first_name}*.");
                }

                $participant->cpf = $cpf;
                $participant->step_register = 10; // Próximo: LGPD
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

            case 10:
                // ANTIGO case 6: Recebe a LGPD (Deslocado)
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