<?php
/**
 * SynDrasi - Public read-only event page.
 * No authentication required. Token-gated.
 */
class PublicEventController
{
    /**
     * GET /public/events/{token}
     * Shows event info to anyone with the link — no login needed.
     */
    public function show($token)
    {
        // Sanitise token — must be exactly 32 lowercase hex chars
        $token = preg_replace('/[^a-f0-9]/', '', strtolower((string) $token));
        if (strlen($token) !== 32) {
            $this->notFound();
        }

        $event = dbq(
            "SELECT e.*, ec.name AS category_name,
                    m.name AS municipality_name
             FROM events e
             LEFT JOIN event_categories ec ON ec.id = e.category_id
             JOIN municipalities m ON m.id = e.municipality_id
             WHERE e.public_token = :token
               AND e.status != 'draft'
             LIMIT 1",
            ['token' => $token]
        )->fetch();

        if (!$event) {
            $this->notFound();
        }

        // Approved team count (no names — just the number, for scale awareness)
        $approvedTeams = (int) dbq(
            "SELECT COUNT(*) FROM event_applications
             WHERE event_id = :eid AND status = 'approved'",
            ['eid' => $event['id']]
        )->fetchColumn();

        // Municipality branding
        $mid  = (int) $event['municipality_id'];
        $logo = dbq(
            "SELECT setting_value FROM municipality_settings
             WHERE municipality_id = :mid AND setting_key = 'branding_logo_url' LIMIT 1",
            ['mid' => $mid]
        )->fetchColumn() ?: null;

        render('public/event', [
            'pageTitle'     => $event['title'] . ' — ' . $event['municipality_name'],
            'event'         => $event,
            'approvedTeams' => $approvedTeams,
            'logo'          => $logo,
        ], false); // standalone — no app layout
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo '<!DOCTYPE html><html lang="el"><head><meta charset="UTF-8">
        <title>Δεν βρέθηκε</title>
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head><body class="bg-light d-flex align-items-center justify-content-center" style="min-height:100vh">
        <div class="text-center p-5">
          <div class="fs-1 mb-3">🔍</div>
          <h1 class="h3">Η δράση δεν βρέθηκε</h1>
          <p class="text-muted">Ο σύνδεσμος μπορεί να έχει λήξει ή να είναι λανθασμένος.</p>
        </div></body></html>';
        exit;
    }
}
