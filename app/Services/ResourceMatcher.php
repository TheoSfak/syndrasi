<?php
/**
 * SynDrasi - Smart Resource Dispatch: προτείνει ποια ομάδα του δήμου διαθέτει
 * τον πόρο που λείπει, με βάση τα ΥΠΑΡΧΟΝΤΑ δεδομένα ετοιμότητας
 * (volunteer_teams.readiness_items_json / has_vehicle / has_medical_equipment).
 * Δεν εισάγει νέο μητρώο εξοπλισμού — βλ. docs/RESOURCE_DISPATCH_SPEC.md.
 */
class ResourceMatcher
{
    private const MAX_SUGGESTIONS = 4;

    /**
     * Συνώνυμα: token του κειμένου της έλλειψης → tokens που ψάχνουμε στα
     * readiness items. Όλα ατονικά/πεζά (βλ. normalize()).
     */
    private const SYNONYMS = [
        'ρευμα'      => ['γεννητρια'],
        'γεννητρια'  => ['γεννητρια'],
        'φως'        => ['προβολεας', 'φακος'],
        'φωτισμος'   => ['προβολεας', 'φακος'],
        'νερο'       => ['βυτιο', 'δεξαμενη', 'αντλια'],
        'αντληση'    => ['αντλια'],
        'πλημμυρα'   => ['αντλια'],
        'δεντρο'     => ['αλυσοπριονο'],
        'κλαδια'     => ['αλυσοπριονο'],
        'κοπη'       => ['αλυσοπριονο'],
        'επικοινωνια'=> ['ασυρματος'],
        'ασυρματος'  => ['ασυρματος'],
        'μεταφορα'   => ['οχημα', 'φορτηγο', 'βαν'],
        'τραυμας'    => ['πρωτες βοηθειες', 'φαρμακειο'],
        'φαρμακα'    => ['φαρμακειο'],
        'drone'      => ['drone'],
        'εναεριος'   => ['drone'],
    ];

    /**
     * Προτάσεις για μια έλλειψη: [ [team_id, team_name, items[], in_event], … ].
     * Δεν προτείνει την ομάδα που ανέφερε την έλλειψη, ανενεργές ομάδες, ή
     * ομάδες με ήδη ανοιχτό αίτημα για την ίδια έλλειψη ($excludeTeamIds).
     */
    public static function suggestForShortage(array $shortage, array $excludeTeamIds = []): array
    {
        $mid = (int) ($shortage['municipality_id'] ?? 0);
        $eid = (int) ($shortage['event_id'] ?? 0);
        $reporterTid = (int) ($shortage['team_id'] ?? 0);
        if ($mid <= 0 || $eid <= 0) {
            return [];
        }
        $type = (string) ($shortage['shortage_type'] ?? 'other');
        if ($type === 'people') {
            return [];   // καλύπτεται από mobilizations, όχι από πόρους
        }

        $teams = dbq(
            "SELECT id, name, has_vehicle, has_medical_equipment, readiness_items_json
             FROM volunteer_teams
             WHERE municipality_id = :mid AND status = 'active' AND id != :tid",
            ['mid' => $mid, 'tid' => $reporterTid]
        )->fetchAll();
        if (!$teams) {
            return [];
        }

        $inEvent = array_flip(array_map('intval', dbq(
            "SELECT DISTINCT team_id FROM event_applications
             WHERE event_id = :eid AND status = 'approved'",
            ['eid' => $eid]
        )->fetchAll(PDO::FETCH_COLUMN) ?: []));

        $needles = self::needles($type, (string) ($shortage['title'] ?? ''), (string) ($shortage['description'] ?? ''));

        $out = [];
        foreach ($teams as $team) {
            $tid = (int) $team['id'];
            if (isset($excludeTeamIds[$tid]) || in_array($tid, $excludeTeamIds, true)) {
                continue;
            }
            $items = self::matchTeam($team, $type, $needles);
            if (!$items) {
                continue;
            }
            $out[] = [
                'team_id'   => $tid,
                'team_name' => $team['name'],
                'items'     => $items,
                'in_event'  => isset($inEvent[$tid]),
            ];
        }

        usort($out, function ($a, $b) {
            return ($b['in_event'] <=> $a['in_event'])
                ?: (count($b['items']) <=> count($a['items']))
                ?: strcmp((string) $a['team_name'], (string) $b['team_name']);
        });
        return array_slice($out, 0, self::MAX_SUGGESTIONS);
    }

    /** Ποια items της ομάδας ταιριάζουν στην έλλειψη. Κενό array = καμία πρόταση. */
    private static function matchTeam(array $team, string $type, array $needles): array
    {
        $items = [];

        if ($type === 'vehicle') {
            if (!empty($team['has_vehicle'])) {
                $items[] = 'Όχημα';
            }
        } elseif ($type === 'medical_supplies') {
            if (!empty($team['has_medical_equipment'])) {
                $items[] = 'Υγειονομικός εξοπλισμός';
            }
        }

        // equipment / other (και συμπληρωματικά σε vehicle/medical): keyword match
        // του κειμένου της έλλειψης με τα readiness items της ομάδας.
        if ($needles) {
            foreach (VolunteerTeam::readinessItems($team) as $item) {
                $norm = self::normalize($item);
                foreach ($needles as $needle) {
                    if ($needle !== '' && mb_strpos($norm, $needle) !== false) {
                        $items[] = $item;
                        break;
                    }
                }
            }
        }
        return array_values(array_unique($items));
    }

    /** Tokens αναζήτησης από τύπο + τίτλο + περιγραφή έλλειψης (ατονικά, πεζά). */
    private static function needles(string $type, string $title, string $description): array
    {
        $text   = self::normalize($title . ' ' . $description);
        $tokens = preg_split('/[^a-zα-ω0-9]+/u', $text) ?: [];
        $needles = [];
        foreach ($tokens as $tok) {
            if (mb_strlen($tok, 'UTF-8') < 4) {
                continue;
            }
            $stem = self::stem($tok);
            $needles[$stem] = true;
            foreach (self::SYNONYMS as $key => $targets) {
                if (mb_strpos($stem, self::stem($key)) === 0 || mb_strpos(self::stem($key), $stem) === 0) {
                    foreach ($targets as $t) {
                        $needles[self::stem(self::normalize($t))] = true;
                    }
                }
            }
        }
        return array_keys($needles);
    }

    /** Πρόχειρο ελληνικό stem: κόβει συνηθισμένες καταλήξεις για ευρύτερο substring match. */
    private static function stem(string $word): string
    {
        return (string) preg_replace('/(ες|ων|ους|ου|ας|ια|εις|η|α|ο|ς)$/u', '', $word);
    }

    /** Πεζά + αφαίρεση ελληνικών τόνων/διαλυτικών. */
    private static function normalize(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        return strtr($s, [
            'ά' => 'α', 'έ' => 'ε', 'ή' => 'η', 'ί' => 'ι', 'ό' => 'ο', 'ύ' => 'υ', 'ώ' => 'ω',
            'ϊ' => 'ι', 'ϋ' => 'υ', 'ΐ' => 'ι', 'ΰ' => 'υ',
        ]);
    }
}
