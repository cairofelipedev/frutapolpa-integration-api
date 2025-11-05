<?php

namespace App\Helpers;

class MessageHelper
{
    public static function greeting($name = null)
    {
        $greetings = [
            "Oi {$name}, tudo bem?",
            "Olá {$name}, espero que esteja bem.",
            "Oi {$name}, como vai?",
            "Oi {$name}, tudo certo por aí?",
            "Fala {$name}, tudo tranquilo?",
        ];

        return $greetings[array_rand($greetings)];
    }

    public static function registrationStart()
    {
        $variants = [
            "Ótimo, vamos começar seu cadastro. Qual o seu nome completo?",
            "Perfeito, bora iniciar seu cadastro. Pode me dizer seu nome completo?",
            "Legal, vamos seguir com seu cadastro. Qual é o seu nome completo?",
            "Show, vamos te cadastrar rapidinho. Qual o seu nome completo?",
        ];

        return $variants[array_rand($variants)];
    }

    public static function registrationLater()
    {
        $variants = [
            "Tudo bem. Caso queira participar depois, é só mandar uma mensagem por aqui.",
            "Sem problemas. Quando quiser participar, é só me chamar novamente.",
            "Beleza. Se mudar de ideia, é só enviar uma mensagem.",
        ];

        return $variants[array_rand($variants)];
    }

    public static function confirmNamePrompt()
    {
        $variants = [
            "Confirme, este é seu nome completo?",
            "Só pra confirmar, este é mesmo o seu nome completo?",
            "Pode confirmar pra mim se esse é o seu nome completo?",
        ];

        return $variants[array_rand($variants)];
    }

    public static function askForCep($name)
    {
        $variants = [
            "Perfeito, {$name}. Agora me informe o seu CEP.",
            "Certo, {$name}. Pode me passar o seu CEP?",
            "Show, {$name}. Qual é o seu CEP?",
        ];

        return $variants[array_rand($variants)];
    }

    public static function askForState($name)
    {
        $variants = [
            "Obrigado, {$name}. Qual é o seu Estado?",
            "Valeu, {$name}. Agora preciso saber o seu Estado.",
            "Perfeito, {$name}. Me diga seu Estado, por favor.",
        ];

        return $variants[array_rand($variants)];
    }

    public static function askForCity($name)
    {
        $variants = [
            "Beleza, {$name}. Agora digite a sua Cidade.",
            "Tudo certo, {$name}. Qual é a sua Cidade?",
            "Show, {$name}. Me diga qual é a sua Cidade.",
        ];

        return $variants[array_rand($variants)];
    }

    public static function askForNeighborhood($name)
    {
        $variants = [
            "Informe o Bairro, {$name}.",
            "Agora me diga o Bairro, {$name}.",
            "Certo, {$name}. Qual o seu Bairro?",
        ];

        return $variants[array_rand($variants)];
    }

    public static function askForCpf($name)
    {
        $variants = [
            "Certo, {$name}. Agora preciso do seu CPF (apenas números).",
            "Perfeito, {$name}. Me envie o seu CPF (somente números).",
            "Show, {$name}. Digite o seu CPF (só os números).",
        ];

        return $variants[array_rand($variants)];
    }

    public static function invalidCpf($name)
    {
        $variants = [
            "CPF inválido. Informe um CPF válido, {$name}.",
            "Ops, {$name}. Esse CPF parece inválido. Tente novamente.",
            "CPF incorreto, {$name}. Pode me enviar um válido?",
        ];

        return $variants[array_rand($variants)];
    }

    public static function askPrivacy($name)
    {
        $variants = [
            "{$name}, para finalizar seu cadastro, você autoriza o uso dos seus dados conforme a LGPD?",
            "{$name}, você aceita o uso dos seus dados de acordo com a LGPD?",
            "{$name}, confirma que está de acordo com o uso dos seus dados conforme a LGPD?",
        ];

        return $variants[array_rand($variants)];
    }

    public static function registrationComplete($name)
    {
        $variants = [
            "Cadastro concluído com sucesso, {$name}. Agora você já pode cadastrar seus cupons.",
            "Tudo certo, {$name}. Seu cadastro foi concluído. Já pode cadastrar seus cupons.",
            "Pronto, {$name}. Cadastro finalizado. Pode começar a cadastrar seus cupons.",
        ];

        return $variants[array_rand($variants)];
    }

    public static function registrationDenied($name)
    {
        $variants = [
            "Tudo bem, {$name}. Sem aceitar a política de privacidade não é possível participar da promoção.",
            "Entendido, {$name}. Você precisa aceitar a política de privacidade para participar.",
            "Tudo certo, {$name}. Quando quiser participar, é só aceitar a política de privacidade.",
        ];

        return $variants[array_rand($variants)];
    }

    public static function getNotRegisteredMessages($firstName)
    {
        return [
            "Olá {$firstName}, tudo bem?\n\nVocê está participando da *Polpa Premiada 2025* da *Fruta Polpa*.\nQuer iniciar seu cadastro para concorrer a uma *Moto 0 km*?",

            "Oi {$firstName}! Aqui é a equipe da *Fruta Polpa*.\nEstamos com a promoção *Polpa Premiada 2025* e você pode ganhar uma *Moto 0 km*.\nDeseja começar seu cadastro agora?",

            "Olá {$firstName}!\nA *Fruta Polpa* está realizando a *Polpa Premiada 2025*.\nQuer participar e ter a chance de ganhar uma *Moto 0 km*?",

            "Tudo bem, {$firstName}?\nVocê foi convidado para participar da *Polpa Premiada 2025* da *Fruta Polpa*.\nGostaria de iniciar seu cadastro para concorrer a uma *Moto 0 km*?",

            "Oi {$firstName}!\nA promoção *Polpa Premiada 2025* da *Fruta Polpa* já começou.\nQuer fazer seu cadastro e participar do sorteio de uma *Moto 0 km*?"
        ];
    }
}
