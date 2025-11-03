<?php

namespace App\Console\Commands;

use App\Models\Participant;
use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendConversationClosureMessage extends Command
{
    protected $signature = 'conversation:check-inactivity';
    protected $description = 'Envia mensagem de encerramento para participantes inativos há mais de 1 minuto';

    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        parent::__construct();
        $this->whatsAppService = $whatsAppService;
    }

    public function handle()
    {
        $inactiveParticipants = Participant::whereNotNull('last_message_at')
            ->where('is_active', true) // apenas fluxos ativos
            ->where('last_message_at', '<', now()->subMinutes(30))
            ->get();

        foreach ($inactiveParticipants as $participant) {
            $this->whatsAppService->sendTextMessage(
                $participant->phone,
                "🍓 Vamos encerrar nossa conversa por enquanto. Caso queira continuar, é só mandar uma nova mensagem!"
            );

            $participant->is_active = false; // marca como encerrado
            $participant->step_register = 0;
            $participant->save();
        }

        $this->info('Mensagens de encerramento enviadas com sucesso.');
    }
}
