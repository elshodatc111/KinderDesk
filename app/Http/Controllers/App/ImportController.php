<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\ImportRun;
use App\Services\Import\ImportService;
use Illuminate\Http\Request;

/** Import faqat direktorga (owner). Boshqa lavozimga berib bo'lmaydi. */
class ImportController extends Controller
{
    private function guard(Request $request): void
    {
        abort_unless($request->user()->is_owner, 403);
    }

    public function index(Request $request)
    {
        $this->guard($request);

        return view('app.import.index', ['runs' => ImportRun::latest('id')->limit(10)->get()]);
    }

    public function template(Request $request, ImportService $import, string $type)
    {
        $this->guard($request);
        abort_unless(in_array($type, ['kids', 'employees'], true), 404);

        return response($import->template($type), 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.($type === 'kids' ? 'bolalar' : 'xodimlar').'-shablon.csv"']);
    }

    public function upload(Request $request, ImportService $import)
    {
        $this->guard($request);
        $d = $request->validate(['type' => 'required|in:kids,employees', 'source' => 'required|in:template,other', 'file' => 'required|file|max:5120|mimes:csv,txt']);
        try {
            $rows = $import->read($request->file('file'));
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        }
        if (! $rows) {
            return back()->with('error', "Faylda qator topilmadi yoki ustun nomlari tanilmadi. Shablondan foydalaning.");
        }
        $run = $import->preview($d['type'], $rows, $request->user(), $d['source'], $request->file('file')->getClientOriginalName());

        return redirect()->route('app.import.show', $run);
    }

    public function show(Request $request, ImportRun $run, ImportService $import)
    {
        $this->guard($request);

        return view('app.import.show', ['run' => $run, 'items' => $run->status === 'preview' ? $import->items($run) : []]);
    }

    public function commit(Request $request, ImportRun $run, ImportService $import)
    {
        $this->guard($request);
        $r = $import->commit($run, $request->user());

        return redirect()->route('app.import.index')->with('success', "Import tugadi: {$r['made']} ta yozuv qo'shildi".($r['groups_made'] ? ", {$r['groups_made']} ta yangi guruh (narxini sozlang)" : ''));
    }

    public function discard(Request $request, ImportRun $run)
    {
        $this->guard($request);
        $run->delete();

        return redirect()->route('app.import.index')->with('success', 'Bekor qilindi');
    }
}
