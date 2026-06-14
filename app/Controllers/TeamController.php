<?php
/**
 * SynDrasi - Volunteer team management (municipality admin).
 */
class TeamController
{
    public function index()
    {
        requireRole(['municipality_admin']);
        $teams = VolunteerTeam::forMunicipality(current_municipality_id());
        render('teams/index', ['pageTitle' => 'Εθελοντικές Ομάδες', 'teams' => $teams]);
    }

    public function create()
    {
        requireRole(['municipality_admin']);
        render('teams/form', ['pageTitle' => 'Νέα Ομάδα', 'team' => null]);
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
        render('teams/form', ['pageTitle' => 'Επεξεργασία Ομάδας', 'team' => $team]);
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
            'has_vehicle'             => post_bool('has_vehicle'),
            'has_medical_equipment'   => post_bool('has_medical_equipment'),
            'default_people_capacity' => post_int('default_people_capacity') ?: null,
            'notes'                   => post_str('notes') ?: null,
            'status'                  => post_str('status') === 'inactive' ? 'inactive' : 'active',
        ];
    }
}
