<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\GuideVideo;

class VideoController extends Controller
{
    public function index()
    {
        return view('app.videos', ['videos' => GuideVideo::where('is_active', true)->orderBy('sort')->orderBy('id')->get()]);
    }
}
