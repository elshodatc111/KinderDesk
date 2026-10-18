<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GuideVideo;
use Illuminate\Http\Request;

class VideoController extends Controller
{
    public function index()
    {
        return view('admin.videos', ['videos' => GuideVideo::orderBy('sort')->orderBy('id')->get()]);
    }

    private function data(Request $request): array
    {
        $d = $request->validate([
            'title_uz' => 'required|string|max:150', 'title_ru' => 'nullable|string|max:150',
            'url' => 'required|string|max:300', 'sort' => 'nullable|integer|min:0|max:9999',
        ]);
        $id = GuideVideo::extractId($d['url']);
        if (! $id) {
            throw \Illuminate\Validation\ValidationException::withMessages(['url' => "YouTube havolasi noto'g'ri"]);
        }

        return ['title_uz' => $d['title_uz'], 'title_ru' => $d['title_ru'] ?? null, 'youtube_id' => $id, 'sort' => $d['sort'] ?? 0, 'is_active' => $request->boolean('is_active', true)];
    }

    public function store(Request $request)
    {
        GuideVideo::create($this->data($request));

        return back()->with('success', "Video qo'shildi");
    }

    public function update(Request $request, GuideVideo $video)
    {
        $video->update($this->data($request));

        return back()->with('success', 'Saqlandi');
    }

    public function destroy(GuideVideo $video)
    {
        $video->delete();

        return back()->with('success', "Video o'chirildi");
    }
}
