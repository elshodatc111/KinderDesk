<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Edition;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function index()
    {
        $editions = Edition::where('is_active', true)->orderBy('sort')
            ->with(['plans' => fn ($q) => $q->where('is_active', true)->orderBy('months')])->get();

        // Migratsiya hali ishlamagan bo'lsa ham bosh sahifa ochilsin
        try {
            $latestPosts = \App\Models\Post::visible()->orderByDesc('is_pinned')->orderByDesc('published_at')->orderByDesc('id')
                ->withCount(['comments as approved_count' => fn ($q) => $q->where('status', 'approved')])->limit(3)->get();
        } catch (\Throwable) {
            $latestPosts = collect();
        }

        return view('public.home', [
            'latestPosts' => $latestPosts,
            'editions' => $editions,
            'features' => config('kinderdesk.features'),
            'trialDays' => config('kinderdesk.trial_days'),
            'faq' => \App\Support\Site\Faq::all(),
        ]);
    }

    public function legal(string $page)
    {
        abort_unless(in_array($page, ['oferta', 'privacy', 'terms'], true), 404);

        return view('public.legal', ['page' => $page]);
    }

    public function locale(Request $request, string $locale)
    {
        abort_unless(in_array($locale, ['uz', 'ru'], true), 404);
        session(['locale' => $locale]);
        if ($user = $request->user()) {
            $user->forceFill(['locale' => $locale])->saveQuietly();
        }

        return back();
    }
}
