<?php

namespace App\Helpers;

class CouponMessageHelper
{
    /**
     * Mensagens para envio da foto do cupom fiscal.
     */
    public static function getSendReceiptMessages($firstName)
    {
        return [
            "Tudo certo, {$firstName}. Envie agora a foto do seu cupom fiscal para validar sua participação.",
            "{$firstName}, agora envie a imagem do seu comprovante de compra.",
            "Certo, {$firstName}. Preciso que envie a foto do cupom fiscal para continuar.",
            "{$firstName}, envie a foto do cupom fiscal. Lembre-se de manter as informações da compra visíveis.",
            "Perfeito, {$firstName}. Envie agora a foto do seu cupom para registrar sua participação."
        ];
    }

    /**
     * Mensagens após envio da imagem e geração dos cupons.
     */
    public static function getFinalizationMessages($firstName, array $codes)
    {
        $codesText = implode("\n", $codes);

        $messages = [
            "Obrigado por participar, {$firstName}.\n\nContinue comprando Fruta Polpa para aumentar suas chances de ganhar.\n\nSeus números da sorte são:\n{$codesText}\n\nCadastre novos cupons sempre que quiser.",
            "Cadastro concluído, {$firstName}.\n\nAqui estão seus números da sorte:\n{$codesText}\n\nQuanto mais cupons você cadastrar, mais chances terá de ganhar.",
            "Participação registrada com sucesso, {$firstName}.\n\nSeus números da sorte são:\n{$codesText}\n\nContinue participando enviando novos cupons.",
            "Pronto, {$firstName}. Seu cupom foi validado com sucesso.\n\nNúmeros da sorte:\n{$codesText}\n\nBoa sorte e continue participando.",
        ];

        return $messages[array_rand($messages)];
    }

    /**
     * Mensagens de erro para cupons não encontrados.
     */
    public static function getCouponNotFoundMessages()
    {
        return [
            "Não encontramos um cupom ativo para salvar essa imagem. Tente novamente.",
            "Nenhum cupom válido foi localizado. Por favor, inicie um novo cadastro de cupom.",
            "Cupom não identificado. Verifique se iniciou corretamente o processo de cadastro.",
        ];
    }

    /**
     * Mensagens iniciais do menu de opções.
     */
    public static function getInitialOptionMessages($firstName)
    {
        return [
            "Olá, {$firstName}. Bem-vindo novamente à Polpa Premiada 2025.\nO que deseja fazer?",
            "Oi, {$firstName}. Você já está participando da Polpa Premiada 2025.\nEscolha uma opção para continuar.",
            "Bem-vindo de volta, {$firstName}.\nO que você gostaria de fazer agora?",
            "Olá novamente, {$firstName}.\nEscolha abaixo o que deseja fazer na Polpa Premiada 2025.",
        ];
    }

    /**
     * Mensagens para exibir os cupons gerados.
     */
    public static function getShowCouponsMessages($firstName, array $codes)
    {
        $codesText = implode("\n", $codes);

        return [
            "Perfeito, {$firstName}. Estes são seus cupons da sorte:\n{$codesText}",
            "{$firstName}, aqui estão os seus cupons da sorte:\n{$codesText}",
            "Tudo certo, {$firstName}. Seus cupons válidos são:\n{$codesText}",
            "Aqui estão seus números da sorte, {$firstName}:\n{$codesText}",
        ];
    }

    /**
     * Mensagens para quando o participante escolhe a quantidade de polpas.
     */
    public static function getQuantityConfirmationMessages($firstName)
    {
        return [
            "Certo, {$firstName}. Agora envie a foto do seu cupom fiscal para validar sua participação.",
            "Tudo bem, {$firstName}. Envie a imagem do cupom para finalizar o cadastro.",
            "{$firstName}, agora envie a foto do seu comprovante de compra.",
            "Estamos quase terminando, {$firstName}. Envie o cupom fiscal agora.",
        ];
    }

    /**
     * Mensagens enviadas quando o usuário clica em 'Cadastrar cupom'.
     */
    public static function getStartCouponMessages($firstName)
    {
        return [
            "Certo, {$firstName}. Vamos começar o cadastro do seu cupom.",
            "Tudo bem, {$firstName}. Informe agora a quantidade de polpas compradas.",
            "Vamos registrar seu cupom, {$firstName}. Quantas polpas você comprou?",
            "{$firstName}, para começar, escolha a quantidade de polpas da sua compra.",
        ];
    }

    /**
     * Mensagens genéricas de erro.
     */
    public static function getGenericErrorMessages()
    {
        return [
            "Ocorreu um erro inesperado. Tente novamente mais tarde.",
            "Não foi possível concluir a ação. Por favor, tente novamente.",
            "Houve uma falha no processo. Reinicie o cadastro do cupom.",
        ];
    }
}
