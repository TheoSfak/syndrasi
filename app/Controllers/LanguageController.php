<?php
/**
 * SynDrasi - Language & translation catalog management (super admin).
 * Backs the "Γλώσσες" tab in Platform Settings.
 */
class LanguageController
{
    public function search()
    {
        requireRole([Role::SUPER_ADMIN]);
        $page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
        $status = in_array($_GET['status'] ?? '', ['missing', 'translated'], true) ? $_GET['status'] : 'all';
        $refLang = self::validLangOr((string) ($_GET['refLang'] ?? ''), 'el');
        $targetLang = self::validLangOr((string) ($_GET['targetLang'] ?? ''), 'en');

        $result = TranslationString::search([
            'q'          => (string) ($_GET['q'] ?? ''),
            'status'     => $status,
            'group'      => (string) ($_GET['group'] ?? ''),
            'refLang'    => $refLang,
            'targetLang' => $targetLang,
        ], $page);

        json_out(['success' => true] + $result);
    }

    public function save()
    {
        requireRole([Role::SUPER_ADMIN]);
        $body = json_input();
        $languageCode = self::validLangOr((string) ($body['languageCode'] ?? ''), '');
        $rows = is_array($body['rows'] ?? null) ? $body['rows'] : [];

        if ($languageCode === '' || !$rows) {
            flash_set('danger', 'Δεν επιλέχθηκαν αλλαγές για αποθήκευση.');
            redirect('/admin/settings#languages');
        }

        $clean = [];
        foreach ($rows as $row) {
            if (!isset($row['key_id'], $row['value'])) {
                continue;
            }
            $clean[] = ['key_id' => (int) $row['key_id'], 'value' => (string) $row['value']];
        }

        TranslationString::saveMany($clean, $languageCode);
        audit('translation_strings_updated', 'languages', null, ['language' => $languageCode, 'count' => count($clean)]);
        flash_set('success', 'Αποθηκεύτηκαν ' . count($clean) . ' μεταφράσεις.');
        redirect('/admin/settings#languages');
    }

    public function add()
    {
        requireRole([Role::SUPER_ADMIN]);
        $code = strtolower(trim(post_str('code')));
        $name = trim(post_str('name'));

        if (!preg_match('/^[a-z]{2,10}$/', $code) || $name === '') {
            flash_set('danger', 'Μη έγκυρος κωδικός ή όνομα γλώσσας.');
            redirect('/admin/settings#languages');
        }
        if (Language::find($code)) {
            flash_set('danger', 'Η γλώσσα υπάρχει ήδη.');
            redirect('/admin/settings#languages');
        }

        Language::create($code, $name);
        audit('language_added', 'languages', null, ['code' => $code]);
        flash_set('success', 'Η γλώσσα «' . $name . '» προστέθηκε.');
        redirect('/admin/settings#languages');
    }

    public function toggle()
    {
        requireRole([Role::SUPER_ADMIN]);
        $code = post_str('code');
        $active = (bool) post_bool('active');

        if (!Language::setActive($code, $active)) {
            flash_set('danger', 'Δεν είναι δυνατή η απενεργοποίηση της γλώσσας πηγής.');
            redirect('/admin/settings#languages');
        }

        flash_set('success', 'Η κατάσταση της γλώσσας ενημερώθηκε.');
        redirect('/admin/settings#languages');
    }

    private static function validLangOr(string $code, string $default): string
    {
        return ($code !== '' && Language::find($code)) ? $code : $default;
    }
}
