<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Security\TwoFactor;
use Illuminate\Console\Command;

class TwoFactorReset extends Command
{
    protected $signature = 'kd:2fa-reset {phone : Foydalanuvchi telefoni (masalan 998901112233)}';

    protected $description = "Foydalanuvchining Telegram-kod (2FA) ulanishini tiklaydi — super admin Telegramini yo'qotganda server orqali";

    public function handle(TwoFactor $tf): int
    {
        $phone = normalize_phone($this->argument('phone'));
        $user = $phone ? User::where('phone', $phone)->first() : null;
        if (! $user) {
            $this->error('Foydalanuvchi topilmadi');

            return self::FAILURE;
        }
        $tf->unlink($user);
        $this->info("Tiklandi: {$user->name}. Keyingi kirishda Telegram qayta ulanadi.");

        return self::SUCCESS;
    }
}
