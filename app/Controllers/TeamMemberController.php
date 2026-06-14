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
 */
class TeamMemberController
{
    // ------------------------------------------------------------------ Index

    public function index()
    {
        requireRole(['team_admin']);
        $tid = current_team_id();
        $mid = current_municipality_id();
        $members = TeamMember::allByTeam($tid);
        $fieldConfig = TeamMember::fieldConfig($mid);
        $team = VolunteerTeam::find($tid);

        render('team/members/index', [
            'pageTitle'   => 'Μέλη Ομάδας',
            'members'     => $members,
            'fieldConfig' => $fieldConfig,
            'team'        => $team,
        ]);
    }

    // ----------------------------------------------------------------- Create

    public function create()
    {
        requireRole(['team_admin']);
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
        requireRole(['team_admin']);
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
        requireRole(['team_admin']);
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
        requireRole(['team_admin']);
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
        requireRole(['team_admin']);
        $member = $this->requireOwned($id);

        if ($member['is_team_admin']) {
            flash_set('warning', 'Ο Διαχειριστής Ομάδας δεν μπορεί να απενεργοποιηθεί εδώ.');
            redirect('/team/members');
        }

        TeamMember::toggleStatus($id);
        $newStatus = $member['status'] === 'active' ? 'απενεργοποιήθηκε' : 'ενεργοποιήθηκε';
        audit('team_member_toggled', 'team_member', $id, 'status: ' . $newStatus);

        flash_set('success', 'Το μέλος «' . htmlspecialchars($member['full_name']) . '» ' . $newStatus . '.');
        redirect('/team/members');
    }

    // --------------------------------------------------------- Stats + Certificate

    /** GET /team/members/{id}/stats — participation history for one member */
    public function stats($id)
    {
        requireRole(['team_admin']);
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
        requireRole(['team_admin']);
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
                    $errors[] = 'Το πεδίο «' . field_label($f) . '» είναι υποχρε