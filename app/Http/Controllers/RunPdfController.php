<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ChecklistRun;
use App\Support\RunVerification;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

/**
 * The per-run PDF (milestone 7, SPEC §"PDF Export") — a facsimile of the
 * paper work order, because auditors and ISO reviewers expect the familiar
 * sheet, not a redesign of it.
 *
 * Gated on the run's own view policy rather than `export.data`: printing the
 * sheet for a run you may already read on screen is the same disclosure, and
 * a supervisor signing off needs to be able to file it.
 */
class RunPdfController extends Controller
{
    // Laravel 11 dropped AuthorizesRequests from the base controller, and
    // this project's base class is empty — pull it in explicitly.
    use AuthorizesRequests;

    public function __invoke(Request $request, ChecklistRun $run): Response
    {
        $this->authorize('view', $run);

        $run->load([
            'template',
            'machine.location.site',
            'items',
            'operator',
            'supervisor',
        ]);

        $pdf = Pdf::loadView('pdf.run', [
            'run' => $run,
            'verification' => RunVerification::hash($run),
            'generatedAt' => now(),
            'displayTz' => (string) config('app.display_timezone', 'UTC'),
            // Embedded rather than linked: dompdf would otherwise have to
            // fetch them over HTTP (disabled) or reach outside its chroot.
            'operatorSignature' => $this->embed($run->operator_signature_path),
            'supervisorSignature' => $this->embed($run->supervisor_signature_path),
        ])->setPaper('a4', 'portrait');

        return $pdf->download(sprintf(
            'run-%d-%s-%s.pdf',
            $run->id,
            $run->machine->code,
            $run->scheduled_for->toDateString(),
        ));
    }

    /**
     * A stored signature as a data URI, or null when it was never signed or
     * the file has gone missing — a missing image must not break the sheet.
     */
    private function embed(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        $disk = Storage::disk((string) config('checklists.signature_disk', 'public'));

        if (! $disk->exists($path)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode((string) $disk->get($path));
    }
}
