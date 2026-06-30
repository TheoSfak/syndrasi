<?php
/**
 * SynDrasi - Volunteer team management (municipality admin).
 */
class TeamController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        $terms = authority_context($mid);
        $teams = VolunteerTeam::forMunicipality($mid);
        render('teams/index', ['pageTitle' => $terms['team_plural'], 'teams' => $teams, 'terms' => $terms]);
    }

    public function create()
    {
        requireRole(['municipality_admin']);
        $mid = current_municipality_id();
        render('teams/form', [
            'pageTitle'        => 'Νέα Ομάδα',
            'team'             => null,
            'readinessOptions' => VolunteerTeam::readinessOptionsForMunicipality($mid),
        ]);
    }

    public function store()
    {
        requireRole(['municipality_admin']);
        $data = $this->validated();
        if ($data === null) {
            redirect('/teams/create');
        }
        $data['municipality_id'] = current_municipality_id();
        $id = VolunteerTeam::create($data);
        audit('team_created', 'volunteer_team', $id, $data['name']);

        // Optionally create a team admin account
        $adminEmail = post_str('admin_email');
        if ($adminEmail !== '' && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            if (User::findByEmail($adminEmail)) {
                flash_set('warning', 'Η ομάδα δημιουργήθηκε, αλλά υπάρχει ήδη χρήστης με αυτό το email — δεν δημιουργήθηκε λογαριασμός.');
            } else {
                $password = bin2hex(random_bytes(4)); // 8 hex chars
                dbq(
                    'INSERT INTO users (municipality_id, team_id, name, email, phone, password_hash, role)
                     VALUES (:mid, :tid, :name, :email, :phone, :hash, :role)',
                    [
                        'mid'   => current_municipality_id(),
                        'tid'   => $id,
                        'name'  => $data['contact_person'] ?: $data['name'],
                        'email' => $adminEmail,
                        'phone' => $data['phone'],
                        'hash'  => password_hash($password, PASSWORD_DEFAULT),
                        'role'  => 'team_admin',
                    ]
                );
                MailService::send(
                    $adminEmail,
                    $data['contact_person'] ?: $data['name'],
                    'Λογαριασμός SynDrasi',
                    "Δημιουργήθηκε λογαριασμός για την ομάδα σας στην πλατφόρμα SynDrasi.\n\n"
                    . "Email: " . $adminEmail . "\nΠροσωρινός κωδικός: " . $password . "\n\n"
                    . "Παρακαλούμε συνδεθείτε και αλλάξτε τον κωδικό σας.",
                    current_municipality_id()
                );
                flash_set('info', 'Δημιουργήθηκε λογαριασμός υπευθύνου ομάδας. Ο προσωρινός κωδικός στάλθηκε με email.');
            }
        }

        flash_set('success', 'Η ομάδα δημιουργήθηκε.');
        redirect('/teams');
    }

    public function edit($id)
    {
        requireRole(['municipality_admin']);
        $team = $this->findOwn($id);
        render('teams/form', [
            'pageTitle'        => 'Επεξεργασία Ομάδας',
            'team'             => $team,
            'readinessOptions' => VolunteerTeam::readinessOptionsForMunicipality((int) $team['municipality_id']),
        ]);
    }

    public function assistants($id)
    {
        requireRole(['municipality_admin']);
        $team = $this->findOwn($id);
        $assistants = TeamMember::assistantsByTeam($team['id']);
        render('teams/assistants', [
            'pageTitle'  => 'Βοηθοί Αρχηγού',
            'team'       => $team,
            'assistants' => $assistants,
        ]);
    }

    public function update($id)
    {
        requireRole(['municipality_admin']);
        $team = $this->findOwn($id);
        $data = $this->validated();
        if ($data === null) {
            redirect('/teams/' . $team['id'] . '/edit');
        }
        VolunteerTeam::update($team['id'], $data);
        audit('team_updated', 'volunteer_team', $team['id'], $data['name']);
        flash_set('success', 'Η ομάδα ενημερώθηκε.');
        redirect('/teams');
    }

    public function toggleStatus($id)
    {
        requireRole(['municipality_admin']);
        $team = $this->findOwn($id);
        VolunteerTeam::toggleStatus($team['id']);
        audit('team_status_toggled', 'volunteer_team', $team['id']);
        flash_set('success', 'Η κατάσταση της ομάδας άλλαξε.');
        redirect('/teams');
    }

    public function revokeAssistant($id, $memberId)
    {
        requireRole(['municipality_admin']);
        $team = $this->findOwn($id);
        $member = TeamMember::find($memberId);
        if (!$member || (int) $member['team_id'] !== (int) $team['id']) {
            abort(404, 'Το μέλος δεν βρέθηκε.');
        }

        if (TeamMemberController::revokeAssistantFromMunicipality((int) $member['id'])) {
            flash_set('success', 'Η πρόσβαση Βοηθού Αρχηγού αφαιρέθηκε.');
        } else {
            flash_set('warning', 'Το μέλος δεν είναι Βοηθός Αρχηγού.');
        }
        redirect('/teams/' . $team['id'] . '/assistants');
    }

    private function findOwn($id)
    {
        $team = VolunteerTeam::find($id);
        if (!$team) {
            abort(404, 'Η ομάδα δεν βρέθηκε.');
        }
        requireMunicipalityAccess($team['municipality_id']);
        return $team;
    }

    private function validated()
    {
        $name = post_str('name');
        if ($name === '') {
            remember_old();
            flash_set('danger', 'Το όνομα της ομάδας είναι υποχρεωτικό.');
            return null;
        }
        return [
            'name'                    => $name,
            'type'                    => post_str('type') ?: null,
            'contact_person'          => post_str('contact_person') ?: null,
            'email'                   => post_str('email') ?: null,
            'phone'                   => post_str('phone') ?: null,
            'address'                 => post_str('address') ?: null,
            'telegram_chat_id'        => post_str('telegram_chat_id') ?: null,
            'has_vehicle'             => post_bool('has_vehicle'),
            'has_medical_equipment'   => post_bool('has_medical_equipment'),
            'default_people_capacity' => post_int('default_people_capacity') ?: null,
            'readiness_items_json'    => $this->readinessItemsJson(),
            'notes'                   => post_str('notes') ?: null,
            'status'                  => post_str('status') === 'inactive' ? 'inactive' : 'active',
        ];
    }

    private function readinessItemsJson(): ?string
    {
        $items = [];
        $posted = $_POST['readiness_items'] ?? [];
        if (is_array($posted)) {
            foreach ($posted as $item) {
                $items[] = trim((string) $item);
            }
        }

        $extra = preg_split('/\r\n|\r|\n/', (string) ($_POST['readiness_items_extra'] ?? ''));
        foreach ($extra ?: [] as $item) {
            $items[] = trim((string) $item);
        }

        $clean = [];
        $seen = [];
        foreach ($items as $item) {
            $item = preg_replace('/\s+/', ' ', $item);
            if ($item === '') {
                continue;
            }
            $key = mb_strtolower($item, 'UTF-8');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = mb_substr($item, 0, 120, 'UTF-8');
        }

        return $clean ? json_encode($clean, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;
    }
}
