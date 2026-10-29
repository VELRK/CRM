<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

class BookService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function rupeesToMinor(string $raw): int
    {
        $raw = str_replace([',', '₹', ' '], '', trim($raw));

        if ($raw === '') {
            return 0;
        }

        if (! is_numeric($raw)) {
            throw new RuntimeException('Enter the amount in rupees.');
        }

        return (int) round(((float) $raw) * 100);
    }

    public static function minorToRupees(int $minor): string
    {
        $decimals = $minor % 100 === 0 ? 0 : 2;

        return '₹' . number_format($minor / 100, $decimals);
    }

    public function ensurePipeline(int $tenantId): int
    {
        $existing = $this->db->table('pipelines')
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('id', 'ASC')
            ->get()
            ->getRowArray();

        if ($existing !== null) {
            return (int) $existing['id'];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('pipelines')->insert([
            'tenant_id'  => $tenantId,
            'name'       => 'Sales',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $pipelineId = (int) $this->db->insertID();

        $stages = [
            ['New', 1, 0, 0],
            ['Qualified', 2, 0, 0],
            ['Proposal', 3, 0, 0],
            ['Won', 4, 1, 0],
            ['Lost', 5, 0, 1],
        ];

        foreach ($stages as [$name, $position, $won, $lost]) {
            $this->db->table('stages')->insert([
                'tenant_id'   => $tenantId,
                'pipeline_id' => $pipelineId,
                'name'        => $name,
                'position'    => $position,
                'is_won'      => $won,
                'is_lost'     => $lost,
                'created_at'  => $now,
            ]);
        }

        return $pipelineId;
    }

    /**
     * @return array{open_count: int, open_minor: int, recent: list<array<string, mixed>>}
     */
    public function home(int $tenantId): array
    {
        if (! $this->tableReady('deals')) {
            return ['open_count' => 0, 'open_minor' => 0, 'recent' => []];
        }

        $open = $this->db->query(
            'SELECT COUNT(*) AS open_count, COALESCE(SUM(deals.amount_minor), 0) AS open_minor
             FROM deals
             JOIN stages ON stages.id = deals.stage_id
             WHERE deals.tenant_id = ? AND deals.deleted_at IS NULL
               AND stages.is_won = 0 AND stages.is_lost = 0',
            [$tenantId],
        )->getRowArray();

        $recent = $this->db->query(
            'SELECT deals.id, deals.title, deals.amount_minor, stages.name AS stage_name, deals.updated_at
             FROM deals
             JOIN stages ON stages.id = deals.stage_id
             WHERE deals.tenant_id = ? AND deals.deleted_at IS NULL
               AND stages.is_won = 0 AND stages.is_lost = 0
             ORDER BY deals.updated_at DESC
             LIMIT 8',
            [$tenantId],
        )->getResultArray();

        return [
            'open_count' => (int) ($open['open_count'] ?? 0),
            'open_minor' => (int) ($open['open_minor'] ?? 0),
            'recent'     => $recent,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contacts(int $tenantId, string $query): array
    {
        $builder = $this->db->table('contacts')
            ->select('contacts.*, users.name AS owner_name')
            ->join('users', 'users.id = contacts.owner_id', 'left')
            ->where('contacts.tenant_id', $tenantId)
            ->where('contacts.deleted_at', null);

        $this->applySearch($builder, $query, ['contacts.name', 'contacts.email', 'contacts.phone', 'contacts.tags']);

        return $builder->orderBy('contacts.name', 'ASC')->get()->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function contact(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('contacts')
            ->select('contacts.*, users.name AS owner_name')
            ->join('users', 'users.id = contacts.owner_id', 'left')
            ->where('contacts.tenant_id', $tenantId)
            ->where('contacts.id', $id)
            ->where('contacts.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @param list<int> $companyIds
     */
    public function saveContact(int $tenantId, ?int $id, array $fields, array $companyIds, string $planCode): int
    {
        $now = date('Y-m-d H:i:s');

        if ($id === null) {
            $cap   = AccessCatalog::CONTACT_CAPS[$planCode] ?? AccessCatalog::CONTACT_CAPS['starter'];
            $count = $this->db->table('contacts')->where('tenant_id', $tenantId)->where('deleted_at', null)->countAllResults();

            if ($count >= $cap) {
                throw new RuntimeException('This plan allows ' . number_format($cap) . ' contacts.');
            }

            $fields['tenant_id']  = $tenantId;
            $fields['created_at'] = $now;
            $fields['updated_at'] = $now;
            $this->db->table('contacts')->insert($fields);
            $id = (int) $this->db->insertID();
        } else {
            if ($this->contact($tenantId, $id) === null) {
                throw new RuntimeException('That contact is not in this workspace.');
            }

            $fields['updated_at'] = $now;
            $this->db->table('contacts')->where('id', $id)->where('tenant_id', $tenantId)->update($fields);
        }

        $this->db->table('contact_company')->where('contact_id', $id)->delete();
        $rows = [];

        foreach (array_unique($companyIds) as $companyId) {
            $companyId = (int) $companyId;

            if ($companyId < 1 || $this->company($tenantId, $companyId) === null) {
                continue;
            }

            $rows[] = [
                'contact_id' => $id,
                'company_id' => $companyId,
                'tenant_id'  => $tenantId,
            ];
        }

        if ($rows !== []) {
            $this->db->table('contact_company')->insertBatch($rows);
        }

        return $id;
    }

    public function deleteContact(int $tenantId, int $id): void
    {
        if ($this->contact($tenantId, $id) === null) {
            throw new RuntimeException('That contact is not in this workspace.');
        }

        $this->db->table('contacts')->where('id', $id)->update([
            'deleted_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return array<int, list<int>>
     */
    public function companyMap(int $tenantId): array
    {
        $rows = $this->db->table('contact_company')
            ->where('tenant_id', $tenantId)
            ->get()
            ->getResultArray();
        $map = [];

        foreach ($rows as $row) {
            $map[(int) $row['contact_id']][] = (int) $row['company_id'];
        }

        return $map;
    }

    public function companyIdsForContact(int $tenantId, int $contactId): array
    {
        $rows = $this->db->table('contact_company')
            ->select('company_id')
            ->where('tenant_id', $tenantId)
            ->where('contact_id', $contactId)
            ->get()
            ->getResultArray();

        return array_map('intval', array_column($rows, 'company_id'));
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companiesForContact(int $tenantId, int $contactId): array
    {
        return $this->db->table('contact_company')
            ->select('companies.id, companies.name')
            ->join('companies', 'companies.id = contact_company.company_id')
            ->where('contact_company.tenant_id', $tenantId)
            ->where('contact_company.contact_id', $contactId)
            ->where('companies.deleted_at', null)
            ->orderBy('companies.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function companies(int $tenantId, string $query): array
    {
        $builder = $this->db->table('companies')
            ->select('companies.*, users.name AS owner_name')
            ->join('users', 'users.id = companies.owner_id', 'left')
            ->where('companies.tenant_id', $tenantId)
            ->where('companies.deleted_at', null);

        $this->applySearch($builder, $query, ['companies.name', 'companies.phone', 'companies.website']);

        return $builder->orderBy('companies.name', 'ASC')->get()->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function company(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('companies')
            ->select('companies.*, users.name AS owner_name')
            ->join('users', 'users.id = companies.owner_id', 'left')
            ->where('companies.tenant_id', $tenantId)
            ->where('companies.id', $id)
            ->where('companies.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    public function saveCompany(int $tenantId, ?int $id, array $fields): int
    {
        $now = date('Y-m-d H:i:s');

        if ($id === null) {
            $fields['tenant_id']  = $tenantId;
            $fields['created_at'] = $now;
            $fields['updated_at'] = $now;
            $this->db->table('companies')->insert($fields);

            return (int) $this->db->insertID();
        }

        if ($this->company($tenantId, $id) === null) {
            throw new RuntimeException('That company is not in this workspace.');
        }

        $fields['updated_at'] = $now;
        $this->db->table('companies')->where('id', $id)->where('tenant_id', $tenantId)->update($fields);

        return $id;
    }

    public function deleteCompany(int $tenantId, int $id): void
    {
        if ($this->company($tenantId, $id) === null) {
            throw new RuntimeException('That company is not in this workspace.');
        }

        $this->db->table('companies')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
        $this->db->table('contact_company')->where('company_id', $id)->delete();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function contactsForCompany(int $tenantId, int $companyId): array
    {
        return $this->db->table('contact_company')
            ->select('contacts.id, contacts.name, contacts.email, contacts.phone')
            ->join('contacts', 'contacts.id = contact_company.contact_id')
            ->where('contact_company.tenant_id', $tenantId)
            ->where('contact_company.company_id', $companyId)
            ->where('contacts.deleted_at', null)
            ->orderBy('contacts.name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{pipeline: array<string, mixed>, stages: list<array<string, mixed>>, deals: array<int, list<array<string, mixed>>>}
     */
    public function board(int $tenantId, string $query): array
    {
        $pipelineId = $this->ensurePipeline($tenantId);
        $pipeline   = $this->db->table('pipelines')->where('id', $pipelineId)->get()->getRowArray();
        $stages     = $this->db->table('stages')
            ->where('tenant_id', $tenantId)
            ->where('pipeline_id', $pipelineId)
            ->orderBy('position', 'ASC')
            ->get()
            ->getResultArray();

        $builder = $this->db->table('deals')
            ->select('deals.*, companies.name AS company_name, users.name AS owner_name')
            ->join('companies', 'companies.id = deals.company_id', 'left')
            ->join('users', 'users.id = deals.owner_id', 'left')
            ->where('deals.tenant_id', $tenantId)
            ->where('deals.pipeline_id', $pipelineId)
            ->where('deals.deleted_at', null);

        $this->applySearch($builder, $query, ['deals.title', 'companies.name']);

        $grouped = [];

        foreach ($stages as $stage) {
            $grouped[(int) $stage['id']] = [];
        }

        foreach ($builder->orderBy('deals.updated_at', 'DESC')->get()->getResultArray() as $deal) {
            $grouped[(int) $deal['stage_id']][] = $deal;
        }

        return [
            'pipeline' => $pipeline,
            'stages'   => $stages,
            'deals'    => $grouped,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function stages(int $tenantId, int $pipelineId): array
    {
        return $this->db->table('stages')
            ->where('tenant_id', $tenantId)
            ->where('pipeline_id', $pipelineId)
            ->orderBy('position', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function deal(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('deals')
            ->select('deals.*, stages.name AS stage_name, companies.name AS company_name, contacts.name AS contact_name, users.name AS owner_name')
            ->join('stages', 'stages.id = deals.stage_id')
            ->join('companies', 'companies.id = deals.company_id', 'left')
            ->join('contacts', 'contacts.id = deals.contact_id', 'left')
            ->join('users', 'users.id = deals.owner_id', 'left')
            ->where('deals.tenant_id', $tenantId)
            ->where('deals.id', $id)
            ->where('deals.deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dealsFor(int $tenantId, string $column, int $id): array
    {
        if (! in_array($column, ['contact_id', 'company_id'], true)) {
            return [];
        }

        return $this->db->table('deals')
            ->select('deals.id, deals.title, deals.amount_minor, stages.name AS stage_name')
            ->join('stages', 'stages.id = deals.stage_id')
            ->where('deals.tenant_id', $tenantId)
            ->where('deals.' . $column, $id)
            ->where('deals.deleted_at', null)
            ->orderBy('deals.updated_at', 'DESC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveDeal(int $tenantId, ?int $id, array $fields): int
    {
        $pipelineId = $this->ensurePipeline($tenantId);
        $stageId    = (int) ($fields['stage_id'] ?? 0);
        $stage      = $this->stage($tenantId, $pipelineId, $stageId);

        if ($stage === null) {
            $first = $this->stages($tenantId, $pipelineId)[0] ?? null;

            if ($first === null) {
                throw new RuntimeException('This pipeline has no stages.');
            }

            $stageId = (int) $first['id'];
        }

        $fields['stage_id']    = $stageId;
        $fields['pipeline_id'] = $pipelineId;
        $fields['company_id']  = $this->nullableId($tenantId, 'companies', $fields['company_id'] ?? null);
        $fields['contact_id']  = $this->nullableId($tenantId, 'contacts', $fields['contact_id'] ?? null);
        $now                    = date('Y-m-d H:i:s');

        if ($id === null) {
            $fields['tenant_id']  = $tenantId;
            $fields['created_at'] = $now;
            $fields['updated_at'] = $now;
            $this->db->table('deals')->insert($fields);

            return (int) $this->db->insertID();
        }

        if ($this->deal($tenantId, $id) === null) {
            throw new RuntimeException('That deal is not in this workspace.');
        }

        $fields['updated_at'] = $now;
        $this->db->table('deals')->where('id', $id)->where('tenant_id', $tenantId)->update($fields);

        return $id;
    }

    public function moveDeal(int $tenantId, int $dealId, int $stageId): void
    {
        $deal = $this->deal($tenantId, $dealId);

        if ($deal === null) {
            throw new RuntimeException('That deal is not in this workspace.');
        }

        $stage = $this->stage($tenantId, (int) $deal['pipeline_id'], $stageId);

        if ($stage === null) {
            throw new RuntimeException('That stage is not on this pipeline.');
        }

        $this->db->table('deals')->where('id', $dealId)->update([
            'stage_id'   => $stageId,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteDeal(int $tenantId, int $id): void
    {
        if ($this->deal($tenantId, $id) === null) {
            throw new RuntimeException('That deal is not in this workspace.');
        }

        $this->db->table('deals')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param list<string> $columns
     */
    private function applySearch($builder, string $query, array $columns): void
    {
        $query = trim($query);

        if ($query === '') {
            return;
        }

        $builder->groupStart();

        foreach ($columns as $index => $column) {
            if ($index === 0) {
                $builder->like($column, $query);
            } else {
                $builder->orLike($column, $query);
            }
        }

        $builder->groupEnd();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function stage(int $tenantId, int $pipelineId, int $stageId): ?array
    {
        $row = $this->db->table('stages')
            ->where('tenant_id', $tenantId)
            ->where('pipeline_id', $pipelineId)
            ->where('id', $stageId)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    private function nullableId(int $tenantId, string $table, mixed $id): ?int
    {
        $id = (int) $id;

        if ($id < 1) {
            return null;
        }

        $row = $this->db->table($table)
            ->select('id')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ? $id : null;
    }

    private function tableReady(string $table): bool
    {
        return $this->db->tableExists($table);
    }
}
