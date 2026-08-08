<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Walkthrough;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dismissing, replaying and previewing the first-run walkthrough.
 *
 * Every action here is POST: each writes to the signed-in user's record or
 * their session, so none belongs behind a link somebody could be sent or a
 * crawler could follow.
 *
 * Dismissing is deliberately forgiving — "Skip" and "Start using it" do the
 * same thing. Somebody who skips has decided they do not need it, and making
 * them sit through five cards to make it stop would be the opposite of help.
 */
class WalkthroughController extends Controller
{
    /**
     * Close the walkthrough.
     *
     * While previewing, this only ends the preview. Marking the
     * administrator "onboarded" because they looked at an operator's cards
     * would consume their own introduction for a thing they were inspecting.
     */
    public function complete(Request $request): RedirectResponse
    {
        if (Walkthrough::isPreviewing()) {
            Walkthrough::stopPreview();

            return back();
        }

        $request->user()?->markWalkthroughSeen();

        return back();
    }

    /**
     * Show it again — for somebody who dismissed it on their first day and
     * wants it back in week two.
     */
    public function replay(Request $request): RedirectResponse
    {
        $request->user()?->resetWalkthrough();

        return back()->with('status', __('app.walkthrough.replay_started'));
    }

    /**
     * Preview any role's walkthrough.
     *
     * Administrators only: the point is answering "what is an operator
     * actually told on their first day?" without creating a test account and
     * signing in as it. It changes nothing about the viewer's own record.
     */
    public function preview(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin') === true, 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', Walkthrough::availableRoles())],
        ]);

        Walkthrough::startPreview($validated['role']);

        return back();
    }
}
