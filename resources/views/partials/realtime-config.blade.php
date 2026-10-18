@php
    $user = auth()->user();
    $reverbKey = config('broadcasting.connections.reverb.key');
    $reverbOn = config('broadcasting.default') === 'reverb' && filled($reverbKey);
    $cfg = $user ? [
        'userId' => $user->id,
        'tenantId' => $user->tenant_id,
        'platform' => (bool) $user->is_super_admin,
        'csrf' => csrf_token(),
        'pollUrl' => $user->is_super_admin ? route('admin.poll') : route('app.poll'),
        'unread' => $user->unreadVisibleNotificationCount(),
        'reverb' => $reverbOn ? [
            'key' => $reverbKey,
            'host' => config('broadcasting.public_host') ?: request()->getHost(),
            'port' => (int) config('broadcasting.connections.reverb.options.port', 8080),
            'scheme' => config('broadcasting.connections.reverb.options.scheme', 'http'),
        ] : null,
    ] : null;
@endphp
<script>window.KD = @json($cfg);</script>
