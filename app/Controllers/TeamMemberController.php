<?php
/**
 * SynDrasi - Team member roster management (team_admin).
 * Routes:
 *   GET  /team/members              → index
 *   GET  /team/members/create       → create form
 *   POST /team/members              → store
 *   GET  /team/members/{id}/edit    → edit form
 *   POST /team/members/{id}         → update
 *   POST /team/members/{id}/toggle  → activate / deactivate
 *   POST /team/members/{id}/assistant/promote → make assistant chief
 *   POST /team/members/{id}/assistant/revoke  → remove assistant access
 */
class TeamMemberController
{
    // ------------------------------------------------------------------ Index

    public function index()
    {
        requireRole([Role::TEAM_ADMIN]);
        $tid = current_team_id();
        $mid = current_municipality_id();
        $members = TeamMember::allByTeam($tid);
        $fieldConfig = TeamMember::fieldConfig($mid);
        $team = VolunteerTeam::find($tid);

        render('team/members/index', [
            'pageTitle'           => 'Μέλη Ομάδας',
            'members'             => $members,
            'fieldConfig'         => $fieldConfig,
            'team'                => $team,
            'canManageAssistants' => $this->canCurrentUserManageAssistants(),
        ]);
    }

    // ----------------------------------------------------------------- Create

    public function create()
    {
        requireRole([Role::TEAM_ADMIN]);
        $mid = current_municipality_id();
        $fieldConfig = TeamMember::fieldConfig($mid);

        render('team/members/form', [
            'pageTitle'   => 'Νέο Μέλος',
            'member'      => null,
            'fieldConfig' => $fieldConfig,
            'isEdit'      => false,
        ]);
    }

    public function store()
    {
        requireRole([Role::TEAM_ADMIN]);
        $tid = current_team_id();
        $mid = current_municipality_id();
        $fieldConfig = TeamMember::fieldConfig($mid);

        $errors = $this->validate($fieldConfig);
        if ($errors) {
            flash_set('danger', implode('<br>', $errors));
            redirect('/team/members/create');
        }

        $data = $this->collectData($tid, $mid);
        $newId = TeamMember::create($data);
        audit('team_member_created', 'team_member', $newId, 'team: ' . $tid);

        flash_set('success', 'Το μέλος «' . htmlspecialchars($data['full_name']) . '» προστέθηκε.');
        redirect('/team/members');
    }

    // ------------------------------------------------------------------- Edit

    public function edit($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $member = $this->requireOwned($id);
        $fieldConfig = TeamMember::fieldConfig(current_municipality_id());

        render('team/members/form', [
            'pageTitle'   => 'Επεξεργασία Μέλους',
            'member'      => $member,
            'fieldConfig' => $fieldConfig,
            'isEdit'      => true,
        ]);
    }

    public function update($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $member = $this->requireOwned($id);
        $mid = current_municipality_id();
        $fieldConfig = TeamMember::fieldConfig($mid);

        $errors = $this->validate($fieldConfig);
        if ($errors) {
            flash_set('danger', implode('<br>', $errors));
            redirect('/team/members/' . $id . '/edit');
        }

        $data = $this->collectData($member['team_id'], $mid);
        unset($data['team_id'], $data['municipality_id'], $data['is_team_admin']); // cannot change via form
        TeamMember::update($id, $data);
        audit('team_member_updated', 'team_member', $id);

        flash_set('success', 'Τα στοιχεία του μέλους ενημερώθηκαν.');
        redirect('/team/members');
    }

    // --------------------------------------------------------------- Toggle

    public function toggle($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $member = $this->requireOwned($id);

        if (!empty($member['is_team_admin']) && empty($member['is_assistant_admin'])) {
            flash_set('warning', 'Ο Διαχειριστής Ομάδας δεν μπορεί να απενεργοποιηθεί εδώ.');
            redirect('/team/members');
        }

        if ($member['status'] === 'active' && !empty($member['is_assistant_admin'])) {
            TeamMember::revokeAssistant($id, true);
        }

        TeamMember::toggleStatus($id);
        $newStatus = $member['status'] === 'active' ? 'απενεργοποιήθηκε' : 'ενεργοποιήθηκε';
        audit('team_member_toggled', 'team_member', $id, 'status: ' . $newStatus);

        flash_set('success', 'Το μέλος «' . htmlspecialchars($member['full_name']) . '» ' . $newStatus . '.');
        redirect('/team/members');
    }

    // ---------------------------------------------------------- Assistant chief

    public function promoteAssistant($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $this->requireAssistantManager();
        $member = $this->requireOwned($id);

        if ($member['status'] !== 'active') {
            flash_set('danger', 'Μπορείτε να ορίσετε βοηθό μόνο ενεργό μέλος.');
            redirect('/team/members');
        }
        if (!empty($member['is_team_admin']) && empty($member['is_assistant_admin'])) {
            flash_set('warning', 'Αυτό το μέλος είναι ήδη ο αρχηγός/διαχειριστής της ομάδας.');
            redirect('/team/members');
        }

        $email = mb_strtolower(trim((string) ($member['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash_set('danger', 'Για να οριστεί Βοηθός Αρχηγού, το μέλος πρέπει να έχει έγκυρο email.');
            redirect('/team/members/' . $id . '/edit');
        }

        $existing = User::findByEmail($email);
        $linkedUser = !empty($member['user_id']) ? User::find((int) $member['user_id']) : null;

        if ($existing && (!$linkedUser || (int) $existing['id'] !== (int) $linkedUser['id'])) {
            flash_set('danger', 'Το email χρησιμοποιείται ήδη από άλλον λογαριασμό. Δεν δόθηκε πρόσβαση βοηθού.');
            redirect('/team/members');
        }

        if ($linkedUser) {
            $userId = (int) $linkedUser['id'];
            dbq(
                "UPDATE users
                 SET name = :name, email = :email, phone = :phone,
                     municipality_id = :mid, team_id = :tid, role = 'team_admin', status = 'active'
                 WHERE id = :id",
                [
                    'name' => $member['full_name'],
                    'email' => $email,
                    'phone' => $member['phone'] ?: null,
                    'mid' => $member['municipality_id'],
                    'tid' => $member['team_id'],
                    'id' => $userId,
                ]
            );
        } else {
            $randomPassword = bin2hex(random_bytes(24));
            dbq(
                "INSERT INTO users (municipality_id, team_id, name, email, phone, password_hash, role, status)
                 VALUES (:mid, :tid, :name, :email, :phone, :hash, 'team_admin', 'active')",
                [
                    'mid' => $member['municipality_id'],
                    'tid' => $member['team_id'],
                    'name' => $member['full_name'],
                    'email' => $email,
                    'phone' => $member['phone'] ?: null,
                    'hash' => password_hash($randomPassword, PASSWORD_DEFAULT),
                ]
            );
            $userId = (int) db()->lastInsertId();
        }

        TeamMember::promoteAssistant($id, $userId, current_user_id());
        $mailOk = $this->sendAssistantInvite($member, $email);

        audit('team_assistant_promoted', 'team_member', $id, [
            'team_id' => (int) $member['team_id'],
            'user_id' => $userId,
            'email' => $email,
        ]);

        if ($mailOk) {
            flash_set('success', 'Ο/Η «' . htmlspecialchars($member['full_name']) . '» ορίστηκε Βοηθός Αρχηγού και στάλθηκε πρόσκληση email.');
        } else {
            flash_set('warning', 'Ο βοηθός ενεργοποιήθηκε, αλλά το email πρόσκλησης δεν στάλθηκε: ' . htmlspecialchars(MailService::$lastError));
        }
        redirect('/team/members');
    }

    public function revokeAssistant($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $this->requireAssistantManager();
        $member = $this->requireOwned($id);
        $this->revokeAssistantMember($member);
        redirect('/team/members');
    }

    public static function revokeAssistantFromMunicipality($memberId): bool
    {
        $member = TeamMember::find($memberId);
        if (!$member || empty($member['is_assistant_admin'])) {
            return false;
        }
        TeamMember::revokeAssistant($memberId, true);
        audit('team_assistant_revoked_by_municipality', 'team_member', $memberId, [
            'team_id' => (int) $member['team_id'],
            'user_id' => !empty($member['user_id']) ? (int) $member['user_id'] : null,
        ]);
        return true;
    }

    // --------------------------------------------------------- Stats + Certificate

    /** GET /team/members/{id}/stats — participation history for one member */
    public function stats($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $member = $this->requireOwned($id);
        $team   = VolunteerTeam::find($member['team_id']);

        $stats          = VolunteerParticipation::statsForMember((int) $id);
        $participations = VolunteerParticipation::forMember((int) $id);

        render('team/member_stats', [
            'pageTitle'      => 'Στατιστικά: ' . $member['full_name'],
            'member'         => $member,
            'teamName'       => $team['name'] ?? '',
            'stats'          => $stats,
            'participations' => $participations,
        ]);
    }

    /** GET /team/members/{id}/certificate — printable volunteer certificate */
    public function certificate($id)
    {
        requireRole([Role::TEAM_ADMIN]);
        $member = $this->requireOwned($id);
        $team   = VolunteerTeam::find($member['team_id']);
        $mid    = current_municipality_id();
        $mun    = dbq('SELECT * FROM municipalities WHERE id = :id', ['id' => $mid])->fetch();
        $logo   = MunicipalitySetting::get($mid, 'logo_url', null);

        $stats          = VolunteerParticipation::statsForMember((int) $id);
        $participations = VolunteerParticipation::forMember((int) $id);

        // Standalone PDF view — no layout
        render('team/member_certificate', [
            'pageTitle'      => 'Πιστοποιητικό: ' . $member['full_name'],
            'member'         => $member,
            'teamName'       => $team['name'] ?? '',
            'mun'            => $mun,
            'logo'           => $logo,
            'stats'          => $stats,
            'participations' => $participations,
        ], false);
    }

    // --------------------------------------------------------------- Helpers

    private function requireOwned($id)
    {
        $member = TeamMember::find($id);
        if (!$member || (int) $member['team_id'] !== (int) current_team_id()) {
            abort(404, 'Το μέλος δεν βρέθηκε.');
        }
        return $member;
    }

    private function canCurrentUserManageAssistants(): bool
    {
        if (current_role() !== 'team_admin') {
            return false;
        }
        $member = TeamMember::findByUser(current_user_id());
        return !$member || empty($member['is_assistant_admin']);
    }

    private function requireAssistantManager(): void
    {
        if (!$this->canCurrentUserManageAssistants()) {
            flash_set('danger', 'Οι Βοηθοί Αρχηγού έχουν πλήρη πρόσβαση στην ομάδα, αλλά δεν μπορούν να ορίζουν ή να αφαιρούν άλλους βοηθούς.');
            redirect('/team/members');
        }
    }

    private function revokeAssistantMember(array $member): void
    {
        if (empty($member['is_assistant_admin'])) {
            flash_set('warning', 'Το μέλος δεν είναι Βοηθός Αρχηγού.');
            return;
        }
        TeamMember::revokeAssistant((int) $member['id'], true);
        audit('team_assistant_revoked', 'team_member', (int) $member['id'], [
            'team_id' => (int) $member['team_id'],
            'user_id' => !empty($member['user_id']) ? (int) $member['user_id'] : null,
        ]);
        flash_set('success', 'Η πρόσβαση Βοηθού Αρχηγού αφαιρέθηκε από τον/την «' . htmlspecialchars($member['full_name']) . '».');
    }

    private function sendAssistantInvite(array $member, string $email): bool
    {
        dbq("DELETE FROM password_resets WHERE email = :email", ['email' => $email]);
        $token = bin2hex(random_bytes(32));
        dbq(
            "INSERT INTO password_resets (email, token) VALUES (:email, :token)",
            ['email' => $email, 'token' => hash('sha256', $token)]
        );

        $resetUrl = $this->absoluteUrl('/reset-password') . '?token=' . urlencode($token);
        $teamName = $member['team_name'] ?? 'την ομάδα σας';
        $body = '<p>Έχετε οριστεί ως <strong>Βοηθός Αρχηγού</strong> στην ομάδα <strong>'
            . htmlspecialchars($teamName, ENT_QUOTES, 'UTF-8') . '</strong>.</p>'
            . '<p>Με αυτή την πρόσβαση μπορείτε να χρησιμοποιείτε το portal της ομάδας με τα ίδια επιχειρησιακά δικαιώματα του αρχηγού, εκτός από τον ορισμό άλλων βοηθών.</p>'
            . '<p>Πατήστε στον παρακάτω σύνδεσμο για να ορίσετε κωδικό πρόσβασης:</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p>Ο σύνδεσμος ισχύει για <strong>1 ώρα</strong>.</p>';

        return MailService::send(
            $email,
            $member['full_name'],
            'Πρόσκληση Βοηθού Αρχηγού — SynDrasi',
            $body,
            $member['municipality_id']
        );
    }

    private function absoluteUrl(string $path): string
    {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return ($https ? 'https' : 'http') . '://' . $host . url($path);
    }

    private function validate(array $fieldConfig): array
    {
        $errors = [];
        if (post_str('full_name') === '') {
            $errors[] = 'Το πεδίο «Ονοματεπώνυμο» είναι υποχρεωτικό.';
        }
        if (post_str('phone') === '') {
            $errors[] = 'Το πεδίο «Τηλέφωνο» είναι υποχρεωτικό.';
        }
        $email = post_str('email');
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Η διεύθυνση email δεν είναι έγκυρη.';
        }
        // Validate configurable required fields
        foreach (TeamMember::OPTIONAL_FIELDS as $f) {
            if (!empty($fieldConfig[$f]['visible']) && !empty($fieldConfig[$f]['required'])) {
                if (post_str($f) === '') {
                    $errors[] = 'Το πεδίο «' . field_label($f) . '» είναι υποχρεωτικό.';
                }
            }
        }
        return $errors;
    }

    private function collectData($teamId, $municipalityId): array
    {
        return [
            'team_id'                      => $teamId,
            'municipality_id'              => $municipalityId,
            'full_name'                    => post_str('full_name'),
            'phone'                        => post_str('phone'),
            'email'                        => post_str('email') ?: null,
            'date_of_birth'                => post_str('date_of_birth') ?: null,
            'address'                      => post_str('address') ?: null,
            'civil_protection_registry_no' => post_str('civil_protection_registry_no') ?: null,
            'role_in_team'                 => post_str('role_in_team') ?: null,
            'notes'                        => post_str('notes') ?: null,
            'blood_type'                   => post_str('blood_type') ?: null,
            'driving_license'              => post_str('driving_license') ?: null,
            'certifications'               => post_str('certifications') ?: null,
            'id_number'                    => post_str('id_number') ?: null,
            'amka'                         => post_str('amka') ?: null,
            'is_team_admin'                => 0,
        ];
    }
}

// Helper: human-readable field labels for error messages
if (!function_exists('field_label')) {
    function field_label(string $key): string
    {
        $map = [
            'blood_type'       => 'Ομάδα Αίματος',
            'driving_license'  => 'Δίπλωμα Οδήγησης',
            'certifications'   => 'Πιστοποιήσεις',
            'id_number'        => 'Αριθμός Ταυτότητας',
            'amka'             => 'ΑΜΚΑ',
        ];
        return $map[$key] ?? $key;
    }
}
