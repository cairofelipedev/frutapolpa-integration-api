<?php

namespace App\Jobs;

use App\Services\WhatsAppService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWhatsAppMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $phoneNumber;
    protected $messageBody;

    public function __construct($phoneNumber, $messageBody)
    {
        $this->phoneNumber = $phoneNumber;
        $this->messageBody = $messageBody;
    }

    public function handle(WhatsAppService $whatsAppService)
    {
        $whatsAppService->sendTextMessage($this->phoneNumber, $this->messageBody);
    }
}
