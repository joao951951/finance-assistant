<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessRawImport;
use App\Models\RawImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function index(Request $request): Response
    {
        $imports = $request->user()
            ->rawImports()
            ->latest()
            ->get(['id', 'filename', 'type', 'bank', 'status', 'transactions_count', 'error_message', 'created_at']);

        return Inertia::render('imports/index', [
            'imports' => $imports,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['required', 'file', 'mimes:csv,txt,pdf', 'max:20480'],
        ]);

        foreach ($request->file('files') as $file) {
            $extension = strtolower($file->getClientOriginalExtension());
            $type = $extension === 'pdf' ? 'pdf' : 'csv';
            $path = $file->store('imports/'.$request->user()->id, 'local');

            $rawImport = RawImport::create([
                'user_id' => $request->user()->id,
                'filename' => $file->getClientOriginalName(),
                'type' => $type,
                'path' => $path,
                'status' => 'pending',
            ]);

            ProcessRawImport::dispatch($rawImport);
        }

        $count = count($request->file('files'));
        $msg = $count === 1 ? 'Arquivo enviado!' : "{$count} arquivos enviados!";

        return back()->with('success', $msg.' O processamento iniciará em instantes.');
    }

    public function destroy(Request $request, RawImport $rawImport): RedirectResponse
    {
        $this->authorize('delete', $rawImport);

        Storage::disk('local')->delete($rawImport->path);
        $rawImport->delete();

        return back()->with('success', 'Importação removida.');
    }
}
