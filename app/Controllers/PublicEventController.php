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
    /** Resolve a Story token to its (closed/completed) event, or 404. */
    private function eventByStoryToken(string $token): array
    {
        $token = preg_replace('/[^a-f0-9]/', '', strtolower($token));
        if (strlen($token) < 16) { $this->notFound(); }
        $event = dbq(
            "SELECT * FROM events WHERE story_token = :t AND status IN ('closed','completed') LIMIT 1",
            ['t' => $token]
        )->fetch();
        if (!$event) { $this->notFound(); }
        return $event;
    }

    /** GET /public/story/{token} — public Story page (no login). */
    public function story($token)
    {
        $event = $this->eventByStoryToken((string) $token);
        $tok   = preg_replace('/[^a-f0-9]/', '', strtolower((string) $token));
        $munSettings = MunicipalitySetting::all((int) $event['municipality_id']);
        render('events/story', [
            'pageTitle'  => 'Απολογισμός: ' . $event['title'],
            'story'      => StoryService::build((int) $event['id']),
            'public'     => true,
            'publicMode' => true,
            'storyToken' => $tok,
            'logo'       => $munSettings['branding_logo_url'] ?? null,
            'orgLabel'   => MunicipalitySetting::orgLabelShort($munSettings),
        ], false);
    }

    /** GET /public/story/{token}/photo/{id} — serve a story photo (no login). */
    public function storyPhoto($token, $id)
    {
        $event = $this->eventByStoryToken((string) $token);
        $photo = EventPhoto::find((int) $id);
        if (!$photo || (int) $photo['event_id'] !== (int) $event['id']) { $this->notFound(); }
        $path = EventPhoto::path($photo);
        if ($path === null) { $this->notFound(); }
        $mime = function_exists('mime_content_type') ? (mime_content_type($path) ?: 'image/jpeg') : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }

    /** GET /public/story/{token}/video/{id} — serve a story video (no login, range). */
    public function storyVideo($token, $id)
    {
        $event = $this->eventByStoryToken((string) $token);
        $video = EventVideo::find((int) $id);
        if (!$video || (int) $video['event_id'] !== (int) $event['id']) { $this->notFound(); }
        $path = EventVideo::path($video);
        if ($path === null) { $this->notFound(); }
        $mime = (string) ($video['mime'] ?: 'video/mp4');
        if (!in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'], true)) { $mime = 'video/mp4'; }
        $size = filesize($path);
        $start = 0; $end = $size - 1;
        header('Content-Type: ' . $mime);
        header('Accept-Ranges: bytes');
        header('Cache-Control: public, max-age=86400');
        if (isset($_SERVER['HTTP_RANGE']) && preg_match('/bytes=(\d*)-(\d*)/', $_SERVER['HTTP_RANGE'], $m)) {
            if ($m[1] !== '') { $start = (int) $m[1]; }
            if ($m[2] !== '') { $end = (int) $m[2]; }
            if ($start > $end || $end >= $size) { $end = $size - 1; }
            header('HTTP/1.1 206 Partial Content');
            header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
        }
        header('Content-Length: ' . ($end - $start + 1));
        $fp = fopen($path, 'rb');
        if ($fp === false) { exit; }
        fseek($fp, $start);
        $remaining = $end - $start + 1;
        while ($remaining > 0 && !feof($fp)) {
            $chunk = fread($fp, (int) min(8192, $remaining));
            if ($chunk === false) { break; }
            echo $chunk; $remaining -= strlen($chunk); flush();
        }
        fclose($fp);
        exit;
    }
}
