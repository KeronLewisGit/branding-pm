<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Dismissing and replaying the first-run walkthrough.
 *
 * POST for both: each writes to the signed-in user's record, so neither
 * belongs behind a link somebody could be sent or a crawler could follow.
 *
 * Dismissing is deliberately forgiving — "Skip" and "Start using it" do the
 * same thing. Somebody who skips has decided they do not need it, and making
 * them sit through five cards to make it stop would be the opposite of help.
 */
class WalkthroughController extends Controller
{
    public function complete(Request $request): RedirectResponse
    {
        $request->user()?->markWalkthroughSeen();

        return back();
    }

    /**
     * Show it again — from the sidebar, for somebody who dismissed it on
     * their first day and wants it back in week two.
     */
    public function replay(Request $request): RedirectResponse
    {
        $request->user()?->resetWalkthrough();

        return back()->with('status', __('app.walkthrough.replay_started'));
    }
}
