<?php

namespace App\Services\Ai;

use App\Models\AiChat;
use App\Models\User;

class ChatService
{
    public function __construct(private AiClient $client, private AiGate $gate, private PiiScanner $pii, private Knowledge $knowledge)
    {
    }

    public function systemPrompt(User $user, string $lang): string
    {
        $langName = $lang === 'ru' ? 'русском' : "o'zbek";
        $sections = implode(', ', $this->knowledge->sections($user));
        $features = implode("\n- ", $this->knowledge->features());
        $guide = $this->knowledge->guide($lang === 'ru' ? 'ru' : 'uz');

        $prompt = <<<TXT
Sen KinderDesk (bog'cha boshqaruv tizimi) ichidagi yordamchisan. Faqat tizimdan qanday foydalanish haqidagi savollarga javob ber.
Qoidalar:
1. Javobni foydalanuvchi tilida ber ({$langName} tilida). Qisqa, aniq, bosqichma-bosqich yoz.
2. Bog'cha ma'lumotlariga (bolalar, xodimlar, pul, maosh, qarzdorlar) KIRISHING YO'Q. Bunday savolga: "Men bog'cha ma'lumotlarini ko'rmayman; tegishli bo'limni oching" deb javob ber va qaysi bo'limni ochishni ayt.
3. Faqat foydalanuvchiga ochiq bo'limlarni tushuntir: {$sections}. Boshqa bo'limni so'rasa, uning ruxsati yo'qligini ayt va direktorga murojaat qilishni maslahat ber.
4. Tizimdan tashqari savolga javob berma; «Yo'riqnoma» bo'limiga yo'nalt.
5. Bilmasang, o'ylab topma — Yo'riqnomaga yo'nalt.
6. Telefon, passport, karta raqami yozishni so'rama.

Bog'chada yoqilgan imkoniyatlar:
- {$features}

Yo'riqnoma:
{$guide}
TXT;

        // Yo'riqnoma matnida namunaviy telefon/karta bo'lsa ham, tashqariga shaxsiy ko'rinishdagi ma'lumot chiqmasin
        return $this->pii->mask($prompt)[0];
    }

    /** @return array{0: array, 1: string, 2: bool} xabarlar, tozalangan savol, maskalanganmi */
    private function prepare(User $user, string $question): array
    {
        $tenant = tenant();
        if ($err = $this->gate->tenantReady($tenant, 'ai_chat')) {
            throw new AiException($err, 'off');
        }
        $this->gate->assertQuestion($user, $tenant);

        [$clean, $masked] = $this->pii->mask(trim($question));
        $history = AiChat::where('user_id', $user->id)->whereNotNull('answer')->latest('id')->limit(3)->get()->reverse();
        $messages = [];
        foreach ($history as $h) {
            $messages[] = ['role' => 'user', 'content' => $h->question];
            $messages[] = ['role' => 'assistant', 'content' => $h->answer];
        }
        $messages[] = ['role' => 'user', 'content' => $clean];

        return [$messages, $clean, $masked];
    }

    /** @throws AiException */
    public function ask(User $user, string $question, string $lang): AiChat
    {
        [$messages, $clean, $masked] = $this->prepare($user, $question);
        $res = $this->client->complete($this->systemPrompt($user, $lang), $messages, 'chat', tenant(), $user, 900);

        return AiChat::create(['tenant_id' => tenant()->id, 'user_id' => $user->id, 'lang' => $lang, 'question' => $clean, 'answer' => trim($res['text']), 'masked' => $masked]);
    }

    /**
     * Jonli javob: matn bo'laklab $onDelta ga uzatiladi, oxirida to'liq javob saqlanadi.
     *
     * @param  callable(string): void  $onDelta
     * @throws AiException
     */
    public function askStream(User $user, string $question, string $lang, callable $onDelta): AiChat
    {
        [$messages, $clean, $masked] = $this->prepare($user, $question);
        $res = $this->client->stream($this->systemPrompt($user, $lang), $messages, 'chat', $onDelta, tenant(), $user, 900);

        return AiChat::create(['tenant_id' => tenant()->id, 'user_id' => $user->id, 'lang' => $lang, 'question' => $clean, 'answer' => trim($res['text']), 'masked' => $masked]);
    }
}
