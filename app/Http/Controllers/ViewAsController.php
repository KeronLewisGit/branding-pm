<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\ViewAs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Start and stop "view as" (routes `view-as.start`, `view-as.stop`).
 *
 * Both gates check `hasRole('admin')` — the **real** role, deliberately, not
 * `isActingAdmin()`. An administrator halfway through previewing an operator
 * is not "acting admin" by that definition, and gating the stop route on it
 * would trap them in the preview with no way back.
 *
 * POST for both: this changes session state, so it must not be reachable
 * from a link somebody was sent.
 */
class ViewAsController extends Controller
{
    public function start(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin') === true, 403);

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:'.implode(',', ViewAs::selectableRoles())],
        ]);

        ViewAs::start($validated['role']);

        activity('user')
            ->causedBy($request->user())
            ->withProperties(['role' => $validated['role'], 'ip' => $request->ip()])
            ->log('user.view_as_started');

        // Home, because the screen they were on may not exist for the role
        // they just became — landing on a 403 would look like a bug.
        return redirect()->route('dashboard');
    }

    public function stop(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('admin') === true, 403);

        $was = ViewAs::role();

        ViewAs::stop();

        if ($was !== null) {
            activity('user')
                ->causedBy($request->user())
                ->withProperties(['role' => $was, 'ip' => $request->ip()])
                ->log('user.view_as_stopped');
        }

        return redirect()->route('dashboard');
    }
}
