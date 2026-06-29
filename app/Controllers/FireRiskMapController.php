<?php
/**
 * Public access for locally stored Civil Protection fire-risk maps.
 */
class FireRiskMapController
{
    public function show($token)
    {
        $path = FireRiskMapService::localMapPathFromToken((string) $token);
        if (!$path) {
            abort(404, 'Ο χάρτης κινδύνου δεν βρέθηκε.');
        }

        while (ob_get_level() > 0) { ob_end_clean(); }
        $info = @getimagesize($path);
        $mime = is_array($info) && !empty($info['mime']) ? $info['mime'] : 'image/jpeg';
        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: public, max-age=86400');
        readfile($path);
        exit;
    }
}
