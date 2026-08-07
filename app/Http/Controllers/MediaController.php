<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\ChecklistRun;
use App\Models\ChecklistRunItem;
use App\Models\Issue;
use App\Support\SignatureImage;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Authorised access to run photos and signature images.
 *
 * Both used to be served straight off the `public` disk as plain URLs
 * (seed-notes §D11). That means anybody who can reach the web server — no
 * login, no session — can fetch a fault photo or an operator's signature if
 * they know or guess the path, and directory paths on the public disk are
 * predictable by design.
 *
 * A signature is the closest thing this system has to a biometric, and a
 * fault photo is a picture of the inside of the plant. Neither belongs on an
 * unauthenticated URL.
 *
 * Every file now goes through here, and every request is checked against the
 * SAME policy that guards the screen the file appears on:
 *
 *   - a run photo → `ChecklistRunPolicy::view` on its run
 *   - an issue photo → `IssuePolicy::view` on its issue
 *   - a signature → `ChecklistRunPolicy::view` on its run
 *
 * Streamed rather than redirected: a redirect to a public URL would just be
 * the old hole with an extra step.
 */
class MediaController extends Controller
{
    // Laravel 11 dropped this from the base controller, so it has to be
    // pulled in explicitly — without it `$this->authorize()` does not exist
    // and every request 500s instead of being checked.
    use AuthorizesRequests;

    public function attachment(Request $request, Attachment $attachment): StreamedResponse
    {
        $this->authorizeAttachment($attachment);

        $disk = Storage::disk($attachment->disk);

        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response(
            $attachment->path,
            $attachment->original_name,
            [
                // Never let a browser render an uploaded file as a document.
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
                // Private: authorised per user, so no shared cache may keep it.
                'Cache-Control' => 'private, max-age=3600, no-transform',
            ],
            'inline',
        );
    }

    /**
     * A run's operator or supervisor signature.
     *
     * `$role` is constrained to the two known values by the route, so it can
     * never be used to read an arbitrary column.
     */
    public function signature(Request $request, ChecklistRun $run, string $role): StreamedResponse
    {
        $this->authorize('view', $run);

        $path = $role === 'operator'
            ? $run->operator_signature_path
            : $run->supervisor_signature_path;

        abort_if($path === null, 404);

        $disk = Storage::disk(SignatureImage::diskName());

        abort_unless($disk->exists($path), 404);

        return $disk->response($path, basename($path), [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'Cache-Control' => 'private, max-age=3600, no-transform',
        ], 'inline');
    }

    /**
     * Authorise a photo through whatever it hangs off.
     *
     * Attachments are polymorphic (a run, a run item, or an issue), and each
     * of those already has a policy deciding who may look at it. Rather than
     * invent a fourth rule, resolve the parent and reuse it — a photo is
     * exactly as confidential as the record it belongs to.
     */
    private function authorizeAttachment(Attachment $attachment): void
    {
        $subject = $attachment->attachable;

        abort_if($subject === null, 404);

        match (true) {
            $subject instanceof ChecklistRun => $this->authorize('view', $subject),
            $subject instanceof ChecklistRunItem => $this->authorize('view', $subject->run),
            $subject instanceof Issue => $this->authorize('view', $subject),
            // An attachable type nobody has written a rule for is refused,
            // not waved through.
            default => abort(403),
        };
    }
}
