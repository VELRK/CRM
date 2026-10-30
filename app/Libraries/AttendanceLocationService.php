<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class AttendanceLocationService
{
    private bool $ready = false;

    public function __construct(private BaseConnection $db)
    {
    }

    /**
     * Location check-in owns the mark when a place is chosen, or when this workspace has active places.
     * Leave, absence, and a workspace with no places stay on the daily attendance row.
     */
    public function handleMark(int $tenantId, int $userId, array $input, bool $correcting, int $actorId, string $date, string $status): bool
    {
        $locationId = (int) ($input['location_id'] ?? 0);
        $timed = in_array($status, ['present', 'late'], true);

        if ($locationId > 0 && $timed) {
            $this->markAtLocation($tenantId, $userId, $locationId, $date, $status, $input, $correcting, $actorId);

            return true;
        }

        if (! $correcting && $timed && $this->hasActiveLocations($tenantId)) {
            throw new RuntimeException('Choose an attendance location.');
        }

        return false;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function attachSessionCounts(int $tenantId, string $month, array $rows): array
    {
        $this->ensure();

        if ($rows === []) {
            return $rows;
        }

        $counts = $this->db->table('attendance_sessions')
            ->select('user_id, work_date, COUNT(*) AS session_count', false)
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->like('work_date', $month, 'after')
            ->groupBy(['user_id', 'work_date'])
            ->get()
            ->getResultArray();
        $byDay = [];

        foreach ($counts as $count) {
            $byDay[(int) $count['user_id'] . '|' . substr((string) $count['work_date'], 0, 10)] = (int) $count['session_count'];
        }

        foreach ($rows as &$row) {
            $key = (int) ($row['user_id'] ?? 0) . '|' . substr((string) ($row['work_date'] ?? ''), 0, 10);
            $row['session_count'] = $byDay[$key] ?? 0;
        }
        unset($row);

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function categories(int $tenantId): array
    {
        $this->ensure();

        return $this->db->table('attendance_location_categories')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveCategory(int $tenantId, ?int $id, array $input): int
    {
        $this->ensure();
        $name = trim((string) ($input['name'] ?? ''));
        $status = (string) ($input['status'] ?? 'active');

        if ($name === '' || strlen($name) > 80) {
            throw new RuntimeException('A location category needs a name.');
        }

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Choose a category status.');
        }

        $dupe = $this->db->table('attendance_location_categories')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('name', $name);

        if ($id !== null) {
            $dupe->where('id !=', $id);
        }

        if ($dupe->countAllResults() > 0) {
            throw new RuntimeException('That location category already exists.');
        }

        $now = date('Y-m-d H:i:s');
        $data = ['name' => $name, 'status' => $status, 'updated_at' => $now];

        if ($id === null) {
            $data['tenant_id'] = $tenantId;
            $data['created_at'] = $now;
            $this->db->table('attendance_location_categories')->insert($data);

            return (int) $this->db->insertID();
        }

        if ($this->category($tenantId, $id) === null) {
            throw new RuntimeException('That location category is not in this workspace.');
        }

        $this->db->table('attendance_location_categories')->where('id', $id)->update($data);

        return $id;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function locations(int $tenantId, bool $withUsers): array
    {
        $this->ensure();
        $rows = $this->db->table('attendance_locations l')
            ->select('l.*, c.name AS category_name, c.status AS category_status')
            ->join('attendance_location_categories c', 'c.id = l.category_id', 'left')
            ->where('l.tenant_id', $tenantId)
            ->where('l.deleted_at', null)
            ->orderBy('l.name', 'ASC')
            ->get()
            ->getResultArray();
        $assignments = $withUsers ? $this->assignments($tenantId) : [];
        $projects = $this->projectNames($tenantId);

        foreach ($rows as &$row) {
            $row = $this->presentLocation($row, $projects, $withUsers ? ($assignments[(int) $row['id']] ?? []) : null);
        }
        unset($row);

        return $rows;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveLocation(int $tenantId, ?int $id, array $input): int
    {
        $this->ensure();
        $data = $this->locationData($tenantId, $id, $input);
        $now = date('Y-m-d H:i:s');
        $data['updated_at'] = $now;

        if ($id === null) {
            $data['tenant_id'] = $tenantId;
            $data['created_at'] = $now;
            $this->db->table('attendance_locations')->insert($data);
            $id = (int) $this->db->insertID();
        } else {
            if ($this->location($tenantId, $id) === null) {
                throw new RuntimeException('That attendance location is not in this workspace.');
            }

            $this->db->table('attendance_locations')->where('id', $id)->update($data);
        }

        if (array_key_exists('user_ids', $input)) {
            $this->syncUsers($tenantId, $id, is_array($input['user_ids']) ? $input['user_ids'] : []);
        }

        return $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function saveProjectLocation(int $tenantId, int $projectId, array $input): void
    {
        if (! array_key_exists('attendance_location_id', $input) && ! array_key_exists('attendance_location', $input)) {
            return;
        }

        $this->ensure();
        $linked = $this->locationForEntity($tenantId, 'project', $projectId);

        if (isset($input['attendance_location']) && is_array($input['attendance_location'])) {
            $payload = $input['attendance_location'];
            $payload['entity_type'] = 'project';
            $payload['entity_id'] = $projectId;
            $payload['status'] = $payload['status'] ?? 'active';
            $this->saveLocation($tenantId, $linked === null ? null : (int) $linked['id'], $payload);

            return;
        }

        $picked = (int) ($input['attendance_location_id'] ?? 0);

        if ($picked < 1) {
            $this->unlinkEntity($tenantId, 'project', $projectId);

            return;
        }

        $location = $this->location($tenantId, $picked);

        if ($location === null) {
            throw new RuntimeException('That attendance location is not in this workspace.');
        }

        $otherType = (string) ($location['entity_type'] ?? '');
        $otherId = (int) ($location['entity_id'] ?? 0);

        if ($otherType === 'project' && $otherId > 0 && $otherId !== $projectId) {
            throw new RuntimeException('That attendance location is already linked to another project.');
        }

        if ($linked !== null && (int) $linked['id'] !== $picked) {
            $this->unlinkEntity($tenantId, 'project', $projectId);
        }

        $this->db->table('attendance_locations')->where('id', $picked)->update([
            'entity_type' => 'project',
            'entity_id'   => $projectId,
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param list<array<string, mixed>> $projects
     * @return list<array<string, mixed>>
     */
    public function withProjectLocations(int $tenantId, array $projects): array
    {
        if ($projects === []) {
            return $projects;
        }

        $this->ensure();
        $ids = array_map(static fn (array $row): int => (int) $row['id'], $projects);
        $links = $this->db->table('attendance_locations')
            ->select('id, name, entity_id')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', 'project')
            ->where('deleted_at', null)
            ->whereIn('entity_id', $ids)
            ->get()
            ->getResultArray();
        $byProject = [];

        foreach ($links as $link) {
            $byProject[(int) $link['entity_id']] = $link;
        }

        foreach ($projects as &$project) {
            $link = $byProject[(int) $project['id']] ?? null;
            $project['attendance_location_id'] = $link === null ? null : (int) $link['id'];
            $project['attendance_location_name'] = $link['name'] ?? null;
        }
        unset($project);

        return $projects;
    }

    /**
     * Active places in this workspace. Any person who can mark attendance may use them.
     *
     * @return list<array<string, mixed>>
     */
    public function places(int $tenantId, int $userId, ?string $lat, ?string $lng): array
    {
        $today = $this->todaySessions($tenantId, $userId);
        $cards = [];

        foreach ($this->locations($tenantId, false) as $row) {
            if ((string) $row['status'] !== 'active' || (string) ($row['category_status'] ?? '') !== 'active') {
                continue;
            }

            $cards[] = $this->placeCard($row, $today[(int) $row['id']] ?? [], $lat, $lng);
        }

        usort($cards, static function (array $a, array $b): int {
            $left = $a['distance_metres'];
            $right = $b['distance_metres'];

            if ($left === null && $right === null) {
                return strcmp((string) $a['name'], (string) $b['name']);
            }

            if ($left === null) {
                return 1;
            }

            if ($right === null) {
                return -1;
            }

            return $left <=> $right;
        });

        return $cards;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function sessions(int $tenantId, string $month, string $date): array
    {
        $this->ensure();
        $builder = $this->db->table('attendance_sessions s')
            ->select('s.*, u.name AS user_name, l.name AS location_name, c.name AS category_name, l.entity_type, l.entity_id')
            ->join('users u', 'u.id = s.user_id', 'left')
            ->join('attendance_locations l', 'l.id = s.location_id', 'left')
            ->join('attendance_location_categories c', 'c.id = l.category_id', 'left')
            ->where('s.tenant_id', $tenantId)
            ->where('s.deleted_at', null);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $builder->where('s.work_date', $date);
        } elseif (preg_match('/^\d{4}-\d{2}$/', $month)) {
            $builder->like('s.work_date', $month, 'after');
        } else {
            $builder->where('s.work_date', date('Y-m-d'));
        }

        $rows = $builder->orderBy('s.id', 'DESC')->get()->getResultArray();
        $projects = $this->projectNames($tenantId);

        foreach ($rows as &$row) {
            $row['entity_name'] = (string) ($row['entity_type'] ?? '') === 'project' ? ($projects[(int) ($row['entity_id'] ?? 0)] ?? null) : null;
            $row['check_status'] = empty($row['check_out']) ? 'open' : 'done';
        }
        unset($row);

        return $rows;
    }

    /**
     * Active places anyone in the workspace can use for check-in.
     *
     * @return list<array<string, mixed>>
     */
    public function markChoices(int $tenantId, int $userId, bool $all): array
    {
        if ($all) {
            return array_values(array_filter(
                $this->locations($tenantId, false),
                static fn (array $row): bool => (string) $row['status'] === 'active' && (string) ($row['category_status'] ?? '') === 'active',
            ));
        }

        return $this->places($tenantId, $userId, null, null);
    }

    /**
     * @param array<string, mixed> $input
     */
    private function markAtLocation(int $tenantId, int $userId, int $locationId, string $date, string $status, array $input, bool $correcting, int $actorId): void
    {
        $location = $this->location($tenantId, $locationId);

        if ($location === null) {
            throw new RuntimeException('That attendance location is not in this workspace.');
        }

        $action = (string) ($input['action'] ?? 'in') === 'out' ? 'out' : 'in';
        $note = trim((string) ($input['note'] ?? ''));

        if (! $correcting) {
            if ((string) $location['status'] !== 'active' || (string) ($location['category_status'] ?? '') !== 'active') {
                throw new RuntimeException('This attendance location is not active.');
            }

            $this->assertInside($location, $input['lat'] ?? null, $input['lng'] ?? null, $input['accuracy'] ?? null);

            if ($action === 'in') {
                $status = $this->statusForHours($location, $status);
            }
        }

        $now = date('Y-m-d H:i:s');

        if ($action === 'out') {
            $open = $this->openSession($tenantId, $userId, $date, $locationId);

            if ($open === null) {
                throw new RuntimeException('Check in at ' . $location['name'] . ' before checking out.');
            }

            $data = [
                'check_out'           => $now,
                'check_out_lat'       => $this->coord($input['lat'] ?? null),
                'check_out_lng'       => $this->coord($input['lng'] ?? null),
                'check_out_accuracy'  => $this->accuracy($input['accuracy'] ?? null),
                'updated_at'          => $now,
            ];

            if ($note !== '') {
                $data['note'] = $note;
            }

            if ($correcting) {
                $data['status'] = $status;
                $data['corrected_by'] = $actorId;
            }

            $this->db->table('attendance_sessions')->where('id', (int) $open['id'])->update($data);
        } else {
            $open = $this->openSession($tenantId, $userId, $date, null);

            if ($open !== null) {
                throw new RuntimeException('Check out of ' . $this->locationLabel($tenantId, (int) $open['location_id']) . ' before checking in again.');
            }

            $this->db->table('attendance_sessions')->insert([
                'tenant_id'         => $tenantId,
                'user_id'           => $userId,
                'location_id'       => $locationId,
                'work_date'         => $date,
                'check_in'          => $now,
                'check_in_lat'      => $this->coord($input['lat'] ?? null),
                'check_in_lng'      => $this->coord($input['lng'] ?? null),
                'check_in_accuracy' => $this->accuracy($input['accuracy'] ?? null),
                'status'            => $status,
                'note'              => $note !== '' ? $note : null,
                'corrected_by'      => $correcting ? $actorId : null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        }

        $this->syncDaily($tenantId, $userId, $date, $note, $correcting, $actorId);
    }

    private function hasActiveLocations(int $tenantId): bool
    {
        $this->ensure();

        return $this->db->table('attendance_locations l')
            ->join('attendance_location_categories c', 'c.id = l.category_id')
            ->where('l.tenant_id', $tenantId)
            ->where('l.deleted_at', null)
            ->where('l.status', 'active')
            ->where('c.deleted_at', null)
            ->where('c.status', 'active')
            ->countAllResults() > 0;
    }

    /**
     * @param array<string, mixed> $location
     */
    private function assertInside(array $location, mixed $lat, mixed $lng, mixed $accuracy): void
    {
        $hereLat = $this->coord($lat);
        $hereLng = $this->coord($lng);

        if ($hereLat === null || $hereLng === null) {
            throw new RuntimeException('Allow location access. Check-in and check-out are accepted only inside an assigned location.');
        }

        $limit = (int) ($location['accuracy_metres'] ?? 0);
        $reading = $this->accuracy($accuracy);

        if ($limit > 0 && ($reading === null || (float) $reading > $limit)) {
            throw new RuntimeException('GPS is not accurate enough for this location. Accuracy must be within ' . $limit . ' metres.');
        }

        $distance = $this->metresBetween((float) $location['latitude'], (float) $location['longitude'], (float) $hereLat, (float) $hereLng);
        $radius = (int) $location['radius_metres'];

        if ($distance > $radius) {
            throw new RuntimeException('You are outside ' . $location['name'] . '. Check-in and check-out are allowed only within ' . $radius . ' metres.');
        }
    }

    /**
     * @param array<string, mixed> $location
     */
    private function statusForHours(array $location, string $status): string
    {
        $start = $this->minutes($location['work_start'] ?? null);
        $end = $this->minutes($location['work_end'] ?? null);

        if ($start === null || $end === null) {
            return $status;
        }

        $now = ((int) date('G')) * 60 + (int) date('i');

        if ($now > $end) {
            throw new RuntimeException('Check-in at ' . $location['name'] . ' is allowed from ' . $this->shortTime($location['work_start']) . ' to ' . $this->shortTime($location['work_end']) . '.');
        }

        return $now > $start ? 'late' : 'present';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function openSession(int $tenantId, int $userId, string $date, ?int $locationId): ?array
    {
        $builder = $this->db->table('attendance_sessions')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('work_date', $date)
            ->where('deleted_at', null)
            ->where('check_out', null);

        if ($locationId !== null) {
            $builder->where('location_id', $locationId);
        }

        $row = $builder->orderBy('id', 'DESC')->get()->getRowArray();

        return $row ?: null;
    }

    private function syncDaily(int $tenantId, int $userId, string $date, string $note, bool $correcting, int $actorId): void
    {
        $sessions = $this->db->table('attendance_sessions')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('work_date', $date)
            ->where('deleted_at', null)
            ->orderBy('check_in', 'ASC')
            ->get()
            ->getResultArray();

        if ($sessions === []) {
            return;
        }

        $status = 'present';
        $open = false;
        $lastOut = null;

        foreach ($sessions as $session) {
            if ((string) $session['status'] === 'late') {
                $status = 'late';
            }

            if (empty($session['check_out'])) {
                $open = true;
            } elseif ($lastOut === null || (string) $session['check_out'] > (string) $lastOut) {
                $lastOut = $session['check_out'];
            }
        }

        $first = $sessions[0];
        $last = $sessions[count($sessions) - 1];
        $now = date('Y-m-d H:i:s');
        $data = [
            'status'         => $status,
            'check_in'       => $first['check_in'],
            'check_out'      => $open ? null : $lastOut,
            'check_in_lat'   => $first['check_in_lat'],
            'check_in_lng'   => $first['check_in_lng'],
            'check_out_lat'  => $open ? null : $last['check_out_lat'],
            'check_out_lng'  => $open ? null : $last['check_out_lng'],
            'updated_at'     => $now,
        ];

        if ($note !== '') {
            $data['note'] = $note;
        }

        if ($correcting) {
            $data['corrected_by'] = $actorId;
        }

        $existing = $this->db->table('attendance')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('work_date', $date)
            ->get()
            ->getRowArray();

        if ($existing === null) {
            $data['tenant_id'] = $tenantId;
            $data['user_id'] = $userId;
            $data['work_date'] = $date;
            $data['created_at'] = $now;
            $this->db->table('attendance')->insert($data);

            return;
        }

        $this->db->table('attendance')->where('id', (int) $existing['id'])->update($data);
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function locationData(int $tenantId, ?int $id, array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));

        if ($name === '' || strlen($name) > 120) {
            throw new RuntimeException('An attendance location needs a name.');
        }

        $dupe = $this->db->table('attendance_locations')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('name', $name);

        if ($id !== null) {
            $dupe->where('id !=', $id);
        }

        if ($dupe->countAllResults() > 0) {
            throw new RuntimeException('An attendance location with this name already exists.');
        }

        $lat = $this->coord($input['latitude'] ?? $input['lat'] ?? null);
        $lng = $this->coord($input['longitude'] ?? $input['lng'] ?? null);

        if ($lat === null || (float) $lat < -90 || (float) $lat > 90 || $lng === null || (float) $lng < -180 || (float) $lng > 180) {
            throw new RuntimeException('Enter the latitude and longitude for this location.');
        }

        $radius = trim((string) ($input['radius_metres'] ?? $input['radius'] ?? ''));

        if ($radius === '' || ! ctype_digit($radius) || (int) $radius < 1 || (int) $radius > 100000) {
            throw new RuntimeException('Enter the geofence radius in metres, up to 100000.');
        }

        $start = $this->timeOrNull($input['work_start'] ?? null);
        $end = $this->timeOrNull($input['work_end'] ?? null);

        if (($start === null) !== ($end === null)) {
            throw new RuntimeException('Enter both the working start and end, or leave both empty.');
        }

        if ($start !== null && $this->minutes($start) !== null && $this->minutes($end) !== null && $this->minutes($end) <= $this->minutes($start)) {
            throw new RuntimeException('Working end must be after the start.');
        }

        $accuracyRaw = trim((string) ($input['accuracy_metres'] ?? ''));
        $accuracy = null;

        if ($accuracyRaw !== '') {
            if (! ctype_digit($accuracyRaw) || (int) $accuracyRaw > 5000) {
                throw new RuntimeException('Enter a GPS accuracy threshold in metres, up to 5000.');
            }

            if ((int) $accuracyRaw > 0) {
                $accuracy = (int) $accuracyRaw;
            }
        }

        $status = (string) ($input['status'] ?? 'active');

        if (! in_array($status, ['active', 'inactive'], true)) {
            throw new RuntimeException('Choose a location status.');
        }

        $address = trim((string) ($input['address'] ?? ''));

        if (strlen($address) > 255) {
            throw new RuntimeException('Use a shorter address.');
        }

        $entity = $this->entity($tenantId, $id, $input);

        return [
            'category_id'     => $this->categoryId($tenantId, $input),
            'name'            => $name,
            'address'         => $address !== '' ? $address : null,
            'latitude'        => $lat,
            'longitude'       => $lng,
            'radius_metres'   => (int) $radius,
            'work_start'      => $start,
            'work_end'        => $end,
            'accuracy_metres' => $accuracy,
            'status'          => $status,
            'entity_type'     => $entity['type'],
            'entity_id'       => $entity['id'],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{type: ?string, id: ?int}
     */
    private function entity(int $tenantId, ?int $id, array $input): array
    {
        $type = strtolower(trim((string) ($input['entity_type'] ?? '')));
        $entityId = (int) ($input['entity_id'] ?? 0);

        if ($type === '' || $entityId < 1) {
            return ['type' => null, 'id' => null];
        }

        if ($type !== 'project') {
            throw new RuntimeException('Link a project in this workspace, or leave the linked record empty.');
        }

        $project = $this->db->table('projects')
            ->where('tenant_id', $tenantId)
            ->where('id', $entityId)
            ->where('deleted_at', null)
            ->countAllResults();

        if ($project < 1) {
            throw new RuntimeException('That project is not in this workspace.');
        }

        $dupe = $this->db->table('attendance_locations')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('entity_type', 'project')
            ->where('entity_id', $entityId);

        if ($id !== null) {
            $dupe->where('id !=', $id);
        }

        if ($dupe->countAllResults() > 0) {
            throw new RuntimeException('That project already has an attendance location.');
        }

        return ['type' => 'project', 'id' => $entityId];
    }

    /**
     * @param array<string, mixed> $input
     */
    private function categoryId(int $tenantId, array $input): int
    {
        $id = (int) ($input['category_id'] ?? 0);
        $name = trim((string) ($input['category_name'] ?? ''));

        if ($id > 0) {
            if ($this->category($tenantId, $id) === null) {
                throw new RuntimeException('Choose a location category.');
            }

            return $id;
        }

        if ($name === '') {
            throw new RuntimeException('Choose a location category.');
        }

        $existing = $this->db->table('attendance_location_categories')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('name', $name)
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        return $this->saveCategory($tenantId, null, ['name' => $name, 'status' => 'active']);
    }

    /**
     * @param list<mixed> $userIds
     */
    private function syncUsers(int $tenantId, int $locationId, array $userIds): void
    {
        $clean = [];

        foreach ($userIds as $userId) {
            $userId = (int) $userId;

            if ($userId > 0) {
                $clean[$userId] = $userId;
            }
        }

        if ($clean !== []) {
            $found = $this->db->table('tenant_users')
                ->select('user_id')
                ->where('tenant_id', $tenantId)
                ->whereIn('user_id', array_values($clean))
                ->get()
                ->getResultArray();
            $allowed = array_map(static fn (array $row): int => (int) $row['user_id'], $found);

            if (count($allowed) !== count($clean)) {
                throw new RuntimeException('Assign people from this workspace only.');
            }

            $clean = $allowed;
        }

        $this->db->table('attendance_location_users')->where('tenant_id', $tenantId)->where('location_id', $locationId)->delete();

        foreach ($clean as $userId) {
            $this->db->table('attendance_location_users')->insert([
                'tenant_id'   => $tenantId,
                'location_id' => $locationId,
                'user_id'     => $userId,
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function category(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('attendance_location_categories')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function location(int $tenantId, int $id): ?array
    {
        $this->ensure();
        $row = $this->db->table('attendance_locations l')
            ->select('l.*, c.name AS category_name, c.status AS category_status')
            ->join('attendance_location_categories c', 'c.id = l.category_id', 'left')
            ->where('l.tenant_id', $tenantId)
            ->where('l.id', $id)
            ->where('l.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function locationForEntity(int $tenantId, string $type, int $entityId): ?array
    {
        $row = $this->db->table('attendance_locations')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $type)
            ->where('entity_id', $entityId)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function unlinkEntity(int $tenantId, string $type, int $entityId): void
    {
        $this->db->table('attendance_locations')
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $type)
            ->where('entity_id', $entityId)
            ->where('deleted_at', null)
            ->update([
                'entity_type' => null,
                'entity_id'   => null,
                'updated_at'  => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * @return array<int, list<int>>
     */
    private function assignments(int $tenantId): array
    {
        $this->ensure();
        $rows = $this->db->table('attendance_location_users')->where('tenant_id', $tenantId)->get()->getResultArray();
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['location_id']][] = (int) $row['user_id'];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function projectNames(int $tenantId): array
    {
        if (! $this->db->tableExists('projects')) {
            return [];
        }

        $rows = $this->db->table('projects')->select('id, name')->where('tenant_id', $tenantId)->where('deleted_at', null)->get()->getResultArray();
        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row['id']] = (string) $row['name'];
        }

        return $names;
    }

    /**
     * @param array<string, mixed> $row
     * @param array<int, string> $projects
     * @param list<int>|null $userIds
     * @return array<string, mixed>
     */
    private function presentLocation(array $row, array $projects, ?array $userIds): array
    {
        $row['radius_metres'] = (int) $row['radius_metres'];
        $row['accuracy_metres'] = $row['accuracy_metres'] === null || $row['accuracy_metres'] === '' ? null : (int) $row['accuracy_metres'];
        $row['work_start'] = $this->shortTime($row['work_start']);
        $row['work_end'] = $this->shortTime($row['work_end']);
        $row['entity_name'] = (string) ($row['entity_type'] ?? '') === 'project' ? ($projects[(int) ($row['entity_id'] ?? 0)] ?? null) : null;

        if ($userIds !== null) {
            $row['user_ids'] = $userIds;
        }

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @param list<array<string, mixed>> $sessions
     * @return array<string, mixed>
     */
    private function placeCard(array $row, array $sessions, ?string $lat, ?string $lng): array
    {
        $open = false;
        $done = false;

        foreach ($sessions as $session) {
            if (empty($session['check_out'])) {
                $open = true;
            } else {
                $done = true;
            }
        }

        $hereLat = $this->coord($lat);
        $hereLng = $this->coord($lng);
        $distance = null;
        $inside = null;

        if ($hereLat !== null && $hereLng !== null) {
            $distance = (int) round($this->metresBetween((float) $row['latitude'], (float) $row['longitude'], (float) $hereLat, (float) $hereLng));
            $inside = $distance <= (int) $row['radius_metres'];
        }

        return [
            'id'               => (int) $row['id'],
            'name'             => $row['name'],
            'category_name'    => $row['category_name'] ?? null,
            'address'          => $row['address'],
            'latitude'         => $row['latitude'],
            'longitude'        => $row['longitude'],
            'radius_metres'    => (int) $row['radius_metres'],
            'accuracy_metres'  => $row['accuracy_metres'],
            'work_start'       => $row['work_start'],
            'work_end'         => $row['work_end'],
            'status'           => $row['status'],
            'entity_type'      => $row['entity_type'],
            'entity_id'        => $row['entity_id'] === null ? null : (int) $row['entity_id'],
            'entity_name'      => $row['entity_name'],
            'distance_metres'  => $distance,
            'inside'           => $inside,
            'check_status'     => $open ? 'open' : ($done ? 'done' : 'none'),
        ];
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    private function todaySessions(int $tenantId, int $userId): array
    {
        $this->ensure();
        $rows = $this->db->table('attendance_sessions')
            ->select('location_id, check_out')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('work_date', date('Y-m-d'))
            ->where('deleted_at', null)
            ->get()
            ->getResultArray();
        $byLocation = [];

        foreach ($rows as $row) {
            $byLocation[(int) $row['location_id']][] = $row;
        }

        return $byLocation;
    }

    private function locationLabel(int $tenantId, int $locationId): string
    {
        $row = $this->location($tenantId, $locationId);

        return $row === null ? 'the open location' : (string) $row['name'];
    }

    private function timeOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $value, $match)) {
            throw new RuntimeException('Enter working times as hours and minutes.');
        }

        $hour = (int) $match[1];
        $minute = (int) $match[2];

        if ($hour > 23 || $minute > 59) {
            throw new RuntimeException('Enter working times as hours and minutes.');
        }

        return sprintf('%02d:%02d:00', $hour, $minute);
    }

    private function minutes(mixed $value): ?int
    {
        $value = trim((string) $value);

        if (! preg_match('/^(\d{2}):(\d{2})/', $value, $match)) {
            return null;
        }

        return ((int) $match[1]) * 60 + (int) $match[2];
    }

    private function shortTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return substr($value, 0, 5);
    }

    private function coord(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || ! is_numeric($value)) {
            return null;
        }

        return number_format((float) $value, 7, '.', '');
    }

    private function accuracy(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < 0) {
            return null;
        }

        return number_format($number, 2, '.', '');
    }

    private function metresBetween(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earth = 6371000.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earth * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function ensure(): void
    {
        if ($this->ready) {
            return;
        }

        $forge = \Config\Database::forge();

        if (! $this->db->tableExists('attendance_location_categories')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 80],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
                'deleted_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->addKey(['tenant_id', 'status']);
            $forge->createTable('attendance_location_categories');
            $this->db->resetDataCache();
        }

        if (! $this->db->tableExists('attendance_locations')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'       => ['type' => 'INT', 'unsigned' => true],
                'category_id'     => ['type' => 'INT', 'unsigned' => true],
                'name'            => ['type' => 'VARCHAR', 'constraint' => 120],
                'address'         => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'latitude'        => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'longitude'       => ['type' => 'DECIMAL', 'constraint' => '10,7'],
                'radius_metres'   => ['type' => 'INT', 'unsigned' => true],
                'work_start'      => ['type' => 'TIME', 'null' => true],
                'work_end'        => ['type' => 'TIME', 'null' => true],
                'accuracy_metres' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'status'          => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'active'],
                'entity_type'     => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true],
                'entity_id'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'updated_at'      => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->addKey(['tenant_id', 'status']);
            $forge->addKey(['tenant_id', 'entity_type', 'entity_id']);
            $forge->createTable('attendance_locations');
            $this->db->resetDataCache();
        }

        if (! $this->db->tableExists('attendance_location_users')) {
            $forge->addField([
                'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
                'location_id' => ['type' => 'INT', 'unsigned' => true],
                'user_id'     => ['type' => 'INT', 'unsigned' => true],
            ]);
            $forge->addKey(['tenant_id', 'location_id', 'user_id'], true);
            $forge->createTable('attendance_location_users');
            $this->db->resetDataCache();
        }

        if (! $this->db->tableExists('attendance_sessions')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'          => ['type' => 'INT', 'unsigned' => true],
                'user_id'            => ['type' => 'INT', 'unsigned' => true],
                'location_id'        => ['type' => 'INT', 'unsigned' => true],
                'work_date'          => ['type' => 'DATE'],
                'check_in'           => ['type' => 'DATETIME', 'null' => true],
                'check_out'          => ['type' => 'DATETIME', 'null' => true],
                'check_in_lat'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_in_lng'       => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_in_accuracy'  => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'check_out_lat'      => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_out_lng'      => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true],
                'check_out_accuracy' => ['type' => 'DECIMAL', 'constraint' => '8,2', 'null' => true],
                'status'             => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'present'],
                'note'               => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'corrected_by'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
                'deleted_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->addKey(['tenant_id', 'user_id', 'work_date']);
            $forge->createTable('attendance_sessions');
            $this->db->resetDataCache();
        }

        $this->ready = true;
    }
}
