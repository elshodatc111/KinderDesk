<?php

namespace App\Services\Telegram;

use App\Models\GroupKid;
use App\Models\Kid;
use App\Models\TelegramBot;
use App\Models\TgContact;
use App\Models\TgOutbox;
use App\Models\TgPost;

/** Post: qabul qiluvchilarni aniqlash, yuborish (copyMessage), 30 daqiqa ichida o'chirish. Fayllar serverda saqlanmaydi. */
class TgPostService
{
    public function __construct(private TelegramOutbox $outbox)
    {
    }

    /** Rozilik bergan, bloklanmagan ota-onalar (bitta chat — bitta nusxa). Guruh bo'lmasa — hamma faol bolalar. */
    public function recipients(TgPost $post, TelegramBot $bot)
    {
        $kidIds = ($post->kind === 'report' || $post->group_id)
            ? GroupKid::where('group_id', $post->group_id)->whereNull('ended_on')->pluck('kid_id')
            : Kid::active()->pluck('id');
        if ($post->kind === 'report' && ! $post->group_id) {
            return collect();
        }

        return TgContact::where('bot_id', $bot->id)->where('type', 'guardian')->where('consented', true)->where('blocked', false)
            ->whereIn('kid_id', $kidIds)->get()->unique('chat_id')->values();
    }

    public function send(TgPost $post, TelegramBot $bot): int
    {
        $contacts = $this->recipients($post, $bot);
        $ids = collect($post->source_message_ids ?? [])->map(fn ($i) => (int) $i)->sort()->values()->all();
        foreach ($contacts as $c) {
            if ($ids) {
                $common = ['from_chat_id' => $post->source_chat_id, 'protect_content' => $bot->protect_content];
                if (count($ids) === 1) {
                    $this->outbox->queue($bot, $c->chat_id, 'copyMessage', $common + ['message_id' => $ids[0]], $post->id, $c->id, $post->tenant_id);
                } else {
                    $this->outbox->queue($bot, $c->chat_id, 'copyMessages', $common + ['message_ids' => $ids], $post->id, $c->id, $post->tenant_id);
                }
            } else {
                $head = $post->kind === 'announce' ? "📢 " : '';
                $this->outbox->queue($bot, $c->chat_id, 'sendMessage', ['text' => $head.$post->text, 'protect_content' => $bot->protect_content], $post->id, $c->id, $post->tenant_id);
            }
        }
        $post->update(['state' => 'sent', 'sent_at' => now(), 'recipients' => $contacts->count()]);
        $this->outbox->kick();

        return $contacts->count();
    }

    /** Yuborilgan xabarlarni ota-onalardan o'chiradi (faqat 30 daqiqa ichida). */
    public function delete(TgPost $post, TelegramBot $bot): bool
    {
        if (! $post->canDelete()) {
            return false;
        }
        // Hali yuborilmaganlarini bekor qilamiz, yuborilganlarini o'chiramiz
        TgOutbox::where('post_id', $post->id)->where('status', 'pending')->update(['status' => 'failed', 'error' => "Post o'chirildi"]);
        foreach (TgOutbox::where('post_id', $post->id)->where('status', 'sent')->get() as $o) {
            if ($ids = $o->message_ids) {
                $this->outbox->queue($bot, $o->chat_id, 'deleteMessages', ['message_ids' => array_values($ids)], $post->id, $o->contact_id, $post->tenant_id);
            }
        }
        $post->update(['state' => 'deleted', 'deleted_at' => now()]);
        $this->outbox->kick();

        return true;
    }
}
