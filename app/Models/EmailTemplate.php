<?php
/**
 * SynDrasi - Email template resolver.
 *
 * Stores custom per-municipality templates in municipality_settings
 * (key: email_tpl_{type}, value: JSON {subject, body}).
 * Falls back to built-in Greek defaults when no custom template is saved.
 *
 * Placeholder syntax inside subject/body: {variable_name}
 */
class EmailTemplate
{
    /**
     * All template definitions.
     * Each entry has: label, icon, subject (default), body (default),
     * vars (key => human label), recipient (description).
     */
    public static function definitions(): array
    {
        return [
            'event_published' => [
                'label'     => 'Νέα αποστολή/δράση δημοσιεύτηκε',
                'icon'      => 'bi-megaphone',
                'subject'   => 'Νέα {event_label_lc}: {event_title}',
                'body'      =>
                    "Δημοσιεύθηκε νέα {event_label_lc} από {org_short}.\n\n" .
                    "{event_label}:      {event_title}\n" .
                    "Κατηγορία:  {event_category}\n" .
                    "Ημερομηνία: {event_date}\n" .
                    "Τοποθεσία:  {event_location}\n\n" .
                    "Συνδεθείτε στην πλατφόρμα SynDrasi για να δηλώσετε συμμετοχή.",
                'vars'      => [
                    'event_title'    => 'Τίτλος αποστολής/δράσης',
                    'event_label'    => 'Όρος αποστολής/δράσης',
                    'event_label_lc' => 'Όρος με μικρά',
                    'org_short'      => 'Σύντομο όνομα φορέα',
                    'event_category' => 'Κατηγορία',
                    'event_date'     => 'Ημερομηνία & ώρα',
                    'event_location' => 'Τοποθεσία',
                ],
                'recipient' => 'Διαχειριστές όλων των ενεργών ομάδων',
            ],

            'application_submitted' => [
                'label'     => 'Νέα δήλωση συμμετοχής',
                'icon'      => 'bi-inbox-fill',
                'subject'   => 'Νέα δήλωση συμμετοχής: {event_title}',
                'body'      =>
                    "Η ομάδα \"{team_name}\" δήλωσε συμμετοχή στη {event_label_lc} \"{event_title}\" με {offered_people} άτομα.\n\n" .
                    "Συνδεθείτε στην πλατφόρμα SynDrasi για Εγκρίσεις.",
                'vars'      => [
                    'event_title'    => 'Τίτλος αποστολής/δράσης',
                    'event_label'    => 'Όρος αποστολής/δράσης',
                    'event_label_lc' => 'Όρος με μικρά',
                    'team_name'      => 'Όνομα ομάδας',
                    'offered_people' => 'Αριθμός ατόμων που δηλώθηκαν',
                ],
                'recipient' => 'Διαχειριστές φορέα',
            ],

            'application_approved' => [
                'label'     => 'Έγκριση συμμετοχής',
                'icon'      => 'bi-check-circle-fill',
                'subject'   => 'Εγκρίθηκε η συμμετοχή σας στη {event_label_lc}',
                'body'      =>
                    "Η συμμετοχή της ομάδας σας εγκρίθηκε για τη {event_label_lc}:\n{event_title}\n\n" .
                    "Ημερομηνία:         {event_date}\n" .
                    "Ώρα προσέλευσης:    {event_time}\n" .
                    "Εγκεκριμένα άτομα: {approved_people}\n" .
                    "Τοποθεσία:          {event_location}\n\n" .
                    "Παρακαλούμε συνδεθείτε στην πλατφόρμα SynDrasi για περισσότερες πληροφορίες.",
                'vars'      => [
                    'event_title'     => 'Τίτλος αποστολής/δράσης',
                    'event_label'     => 'Όρος αποστολής/δράσης',
                    'event_label_lc'  => 'Όρος με μικρά',
                    'event_date'      => 'Ημερομηνία',
                    'event_time'      => 'Ώρα έναρξης',
                    'event_location'  => 'Τοποθεσία',
                    'approved_people' => 'Εγκεκριμένα άτομα',
                ],
                'recipient' => 'Διαχειριστής ομάδας',
            ],

            'application_rejected' => [
                'label'     => 'Απόρριψη συμμετοχής',
                'icon'      => 'bi-x-circle',
                'subject'   => 'Απάντηση στη δήλωση συμμετοχής σας',
                'body'      =>
                    "Η δήλωση συμμετοχής της ομάδας σας για τη {event_label_lc} \"{event_title}\" δεν εγκρίθηκε.\n" .
                    "{rejection_reason}\n" .
                    "Ευχαριστούμε για τη διαθεσιμότητά σας.",
                'vars'      => [
                    'event_title'      => 'Τίτλος αποστολής/δράσης',
                    'event_label'      => 'Όρος αποστολής/δράσης',
                    'event_label_lc'   => 'Όρος με μικρά',
                    'rejection_reason' => 'Αιτιολογία απόρριψης (κενό αν δεν δόθηκε)',
                ],
                'recipient' => 'Διαχειριστής ομάδας',
            ],

            'shortage_reported' => [
                'label'     => 'Αναφορά έλλειψης',
                'icon'      => 'bi-exclamation-triangle',
                'subject'   => 'Αναφορά έλλειψης: {event_title}',
                'body'      =>
                    "Η ομάδα \"{team_name}\" ανέφερε έλλειψη στη {event_label_lc} \"{event_title}\".\n\n" .
                    "Τύπος:      {shortage_type}\n" .
                    "Σοβαρότητα: {shortage_severity}\n" .
                    "Τίτλος:     {shortage_title}\n\n" .
                    "Δείτε την Επιχειρησιακή Σελίδα για περισσότερα.",
                'vars'      => [
                    'event_title'       => 'Τίτλος αποστολής/δράσης',
                    'event_label'       => 'Όρος αποστολής/δράσης',
                    'event_label_lc'    => 'Όρος με μικρά',
                    'team_name'         => 'Όνομα ομάδας',
                    'shortage_type'     => 'Τύπος έλλειψης',
                    'shortage_severity' => 'Σοβαρότητα',
                    'shortage_title'    => 'Τίτλος αναφοράς',
                ],
                'recipient' => 'Διαχειριστές φορέα',
            ],

            'event_reminder' => [
                'label'     => 'Υπενθύμιση αποστολής/δράσης',
                'icon'      => 'bi-alarm',
                'subject'   => 'Υπενθύμιση {event_label_lc}: {event_title}',
                'body'      =>
                    "Υπενθύμιση για την επερχόμενη {event_label_lc}:\n{event_title}\n\n" .
                    "Ημερομηνία:         {event_date}\n" .
                    "Ώρα προσέλευσης:    {event_time}\n" .
                    "Εγκεκριμένα άτομα: {approved_people}\n" .
                    "Τοποθεσία:          {event_location}\n\n" .
                    "Παρακαλούμε συνδεθείτε στην πλατφόρμα SynDrasi για περισσότερες πληροφορίες.",
                'vars'      => [
                    'event_title'     => 'Τίτλος αποστολής/δράσης',
                    'event_label'     => 'Όρος αποστολής/δράσης',
                    'event_label_lc'  => 'Όρος με μικρά',
                    'event_date'      => 'Ημερομηνία',
                    'event_time'      => 'Ώρα έναρξης',
                    'event_location'  => 'Τοποθεσία',
                    'approved_people' => 'Εγκεκριμένα άτομα',
                ],
                'recipient' => 'Διαχειριστής ομάδας',
            ],

            'event_completed' => [
                'label'     => 'Κλείσιμο αποστολής/δράσης — υποβολή αναφοράς',
                'icon'      => 'bi-flag',
                'subject'   => 'Ολοκληρώθηκε η {event_label_lc}: {event_title}',
                'body'      =>
                    "Η {event_label_lc} \"{event_title}\" ολοκληρώθηκε.\n\n" .
                    "Παρακαλούμε συνδεθείτε στην πλατφόρμα SynDrasi και υποβάλετε τη σύντομη αναφορά της ομάδας σας.",
                'vars'      => [
                    'event_title' => 'Τίτλος αποστολής/δράσης',
                    'event_label' => 'Όρος αποστολής/δράσης',
                    'event_label_lc' => 'Όρος με μικρά',
                ],
                'recipient' => 'Διαχειριστής ομάδας',
            ],

            'event_closed' => [
                'label'     => 'Debrief αποστολής/δράσης',
                'icon'      => 'bi-clipboard-check',
                'subject'   => 'Debrief {event_label_lc}: {event_title}',
                'body'      =>
                    "Η {event_label_lc} \"{event_title}\" έκλεισε επιχειρησιακά.\n\n" .
                    "Παρακαλούμε συμπληρώστε το Post-Event Debrief της ομάδας σας.",
                'vars'      => [
                    'event_title' => 'Τίτλος αποστολής/δράσης',
                    'event_label' => 'Όρος αποστολής/δράσης',
                    'event_label_lc' => 'Όρος με μικρά',
                ],
                'recipient' => 'Διαχειριστής ομάδας',
            ],

            'member_assigned' => [
                'label'     => 'Ορισμός μέλους σε αποστολή/δράση',
                'icon'      => 'bi-person-check',
                'subject'   => 'Ορίστηκες για {event_label_lc}: {event_title}',
                'body'      =>
                    "Αγαπητέ/ή {member_name},\n\n" .
                    "Ο/Η διαχειριστής της ομάδας σας σε έχει συμπεριλάβει στη δήλωση συμμετοχής " .
                    "για την παρακάτω {event_label_lc}:\n\n" .
                    "{event_label}:      {event_title}\n" .
                    "Ημερομηνία: {event_start}\n" .
                    "Λήξη:       {event_end}\n" .
                    "Τοποθεσία:  {event_location}\n" .
                    "Ρόλος σου:  {member_role}\n\n" .
                    "{commander_note}" .
                    "Για απορίες επικοινώνησε με τον διαχειριστή της ομάδας σου.\n\n" .
                    "— SynDrasi",
                'vars'      => [
                    'member_name'    => 'Όνομα μέλους',
                    'event_title'    => 'Τίτλος αποστολής/δράσης',
                    'event_label'    => 'Όρος αποστολής/δράσης',
                    'event_label_lc' => 'Όρος με μικρά',
                    'event_start'    => 'Ημερομηνία & ώρα έναρξης',
                    'event_end'      => 'Ημερομηνία & ώρα λήξης',
                    'event_location' => 'Τοποθεσία',
                    'member_role'    => 'Ρόλος (Μέλος ή Mission Υπεύθυνος)',
                    'commander_note' => 'Σημείωση για Mission Υπεύθυνο (αυτόματα κενό για κανονικά μέλη)',
                ],
                'recipient' => 'Κάθε μέλος ομάδας που ορίζεται',
            ],
        ];
    }

    /**
     * Resolve subject + body for a given type and municipality.
     * Custom template (if saved) overrides the defaults.
     * Then {placeholder} tokens are substituted with values from $vars.
     *
     * @param  int|null $municipalityId
     * @param  string   $type            e.g. 'event_published'
     * @param  array    $vars            ['event_title' => 'Γιορτή Κρήτης', ...]
     * @return array    ['subject' => string, 'body' => string]
     */
    public static function resolve($municipalityId, string $type, array $vars = []): array
    {
        $defs = self::definitions();
        if (!isset($defs[$type])) {
            return ['subject' => '', 'body' => ''];
        }

        $subject = $defs[$type]['subject'];
        $body    = $defs[$type]['body'];

        // Override with municipality's custom template if saved
        if ($municipalityId) {
            $raw = MunicipalitySetting::get($municipalityId, 'email_tpl_' . $type, '');
            if ($raw !== '') {
                $custom = json_decode($raw, true);
                if (is_array($custom)) {
                    if (!empty($custom['subject'])) { $subject = $custom['subject']; }
                    if (isset($custom['body']) && $custom['body'] !== '') { $body = $custom['body']; }
                }
            }
        }

        // Substitute {placeholder} tokens
        foreach ($vars as $key => $value) {
            $subject = str_replace('{' . $key . '}', (string) $value, $subject);
            $body    = str_replace('{' . $key . '}', (string) $value, $body);
        }

        return ['subject' => $subject, 'body' => $body];
    }

    /**
     * Return the stored (or default) subject/body for the settings UI.
     * Does NOT perform placeholder substitution.
     *
     * @return array ['subject' => string, 'body' => string, 'is_custom' => bool]
     */
    public static function getStored($municipalityId, string $type): array
    {
        $defs = self::definitions();
        if (!isset($defs[$type])) {
            return ['subject' => '', 'body' => '', 'is_custom' => false];
        }

        $subject  = $defs[$type]['subject'];
        $body     = $defs[$type]['body'];
        $isCustom = false;

        if ($municipalityId) {
            $raw = MunicipalitySetting::get($municipalityId, 'email_tpl_' . $type, '');
            if ($raw !== '') {
                $custom = json_decode($raw, true);
                if (is_array($custom) && (!empty($custom['subject']) || isset($custom['body']))) {
                    if (!empty($custom['subject'])) { $subject = $custom['subject']; }
                    if (isset($custom['body']) && $custom['body'] !== '') { $body = $custom['body']; }
                    $isCustom = true;
                }
            }
        }

        return ['subject' => $subject, 'body' => $body, 'is_custom' => $isCustom];
    }
}
