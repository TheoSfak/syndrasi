<?php
/**
 * SynDrasi - Access control middleware helpers.
 */

function requireLogin()
{
    if (!is_logged_in() || !current_user()) {
        if (wants_json()) {
            json_out(['success' => false, 'message' => 'Απαιτείται σύνδεση.'], 401);
        }
        flash_set('warning', 'Παρακαλούμε συνδεθείτε για να συνεχίσετε.');
        redirect('/login');
    }
}

function requireRole(array $roles)
{
    requireLogin();
    if (!in_array(current_role(), $roles, true)) {
        if (wants_json()) {
            json_out(['success' => false, 'message' => 'Δεν έχετε δικαίωμα πρόσβασης.'], 403);
        }
        flash_set('danger', 'Δεν έχετε δικαίωμα πρόσβασης σε αυτή τη σελίδα.');
        redirect(role_home());
    }
}

/** Municipality data isolation. Super admin passes always. */
function requireMunicipalityAccess($municipalityId)
{
    requireLogin();
    if (current_role() === 'super_admin') {
        return;
    }
    if ((int) $municipalityId !== (int) current_municipality_id()) {
        if (wants_json()) {
            json_out(['success' => false, 'message' => 'Δεν έχετε πρόσβαση σε αυτόν τον δήμο.'], 403);
        }
        abort(403, 'Δεν έχετε πρόσβαση στα δεδομένα αυτού του δήμου.');
    }
}

/** Team data isolation for team admins. */
function requireTeamAccess($teamId)
{
    requireLogin();
    if (in_array(current_role(), ['super_admin', 'municipality_admin'], true)) {
        return;
    }
    if ((int) $teamId !== (int) current_team_id()) {
        if (wants_json()) {
            json_out(['success' => false, 'message' => 'Δεν έχετε πρόσβαση σε αυτή την ομάδα.'], 403);
        }
        abort(403, 'Δεν έχετε πρόσβαση στα δεδομένα αυτής της ομάδας.');
    }
}
