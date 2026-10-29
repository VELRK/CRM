<?php

namespace App\Libraries;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use RuntimeException;

class DeskService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public static function money(int $minor, string $currency = 'INR'): string
    {
        $decimals  = $minor % 100 === 0 ? 0 : 2;
        $formatted = number_format($minor / 100, $decimals);

        return ($currency === 'USD' ? '$' : '₹') . $formatted;
    }

    /**
     * @param list<array<string, mixed>> $lines
     * @return array{net: int, tax: int, total: int}
     */
    public static function summarize(array $lines): array
    {
        $net = 0;
        $tax = 0;

        foreach ($lines as $line) {
            $rowNet = (int) round(((int) $line['price_minor']) * (float) $line['qty']);
            $net   += $rowNet;
            $tax   += (int) round($rowNet * ((float) $line['tax_rate']) / 100);
        }

        return ['net' => $net, 'tax' => $tax, 'total' => $net + $tax];
    }

    public function currency(int $tenantId): string
    {
        if (! $this->db->fieldExists('currency', 'tenants')) {
            return 'INR';
        }

        $row  = $this->db->table('tenants')->select('currency')->where('id', $tenantId)->get()->getRowArray();
        $code = strtoupper((string) ($row['currency'] ?? 'INR'));

        return $code === 'USD' ? 'USD' : 'INR';
    }

    public function sweepReminders(int $tenantId, int $userId): void
    {
        if (! $this->db->tableExists('tasks') || ! $this->db->tableExists('notifications')) {
            return;
        }

        $now   = date('Y-m-d H:i:s');
        $tasks = $this->db->query(
            'SELECT tasks.id, tasks.title
             FROM tasks
             WHERE tasks.tenant_id = ? AND tasks.owner_id = ? AND tasks.deleted_at IS NULL
               AND tasks.done_at IS NULL AND tasks.remind_at IS NOT NULL AND tasks.remind_at <= ?
               AND NOT EXISTS (
                   SELECT 1 FROM notifications
                   WHERE notifications.task_id = tasks.id AND notifications.user_id = ?
               )',
            [$tenantId, $userId, $now, $userId],
        )->getResultArray();

        foreach ($tasks as $task) {
            $this->notify($tenantId, $userId, 'Reminder', (string) $task['title'], '/app/work', (int) $task['id']);
        }
    }

    public function notify(int $tenantId, int $userId, string $title, ?string $body, ?string $href, ?int $taskId): void
    {
        if (! $this->db->tableExists('notifications') || $userId < 1) {
            return;
        }

        $this->db->table('notifications')->insert([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'title'      => mb_substr($title, 0, 190),
            'body'       => $body !== null ? mb_substr($body, 0, 255) : null,
            'href'       => $href,
            'task_id'    => $taskId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function unreadCount(int $tenantId, int $userId): int
    {
        if (! $this->db->tableExists('notifications')) {
            return 0;
        }

        return $this->db->table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('read_at', null)
            ->countAllResults();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function notices(int $tenantId, int $userId): array
    {
        if (! $this->db->tableExists('notifications')) {
            return [];
        }

        return $this->db->table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->limit(50)
            ->get()
            ->getResultArray();
    }

    public function markNotice(int $tenantId, int $userId, int $id): void
    {
        $this->db->table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('id', $id)
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function markAllNotices(int $tenantId, int $userId): void
    {
        $this->db->table('notifications')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where('read_at', null)
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    public function audit(int $tenantId, ?int $userId, string $plan, string $action, string $type, ?int $subjectId, string $detail): void
    {
        unset($plan);

        if (! $this->db->tableExists('audit_logs')) {
            return;
        }

        $this->db->table('audit_logs')->insert([
            'tenant_id'    => $tenantId,
            'user_id'      => $userId,
            'action'       => $action,
            'subject_type' => $type,
            'subject_id'   => $subjectId,
            'detail'       => mb_substr($detail, 0, 255),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function auditLog(int $tenantId): array
    {
        if (! $this->db->tableExists('audit_logs')) {
            return [];
        }

        return $this->db->table('audit_logs')
            ->select('audit_logs.*, users.name AS user_name')
            ->join('users', 'users.id = audit_logs.user_id', 'left')
            ->where('audit_logs.tenant_id', $tenantId)
            ->orderBy('audit_logs.id', 'DESC')
            ->limit(100)
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{due_today: int, quotes_waiting: int, unpaid_minor: int, tasks_today: list<array<string, mixed>>}
     */
    public function homeFigures(int $tenantId): array
    {
        $empty = ['due_today' => 0, 'quotes_waiting' => 0, 'unpaid_minor' => 0, 'tasks_today' => []];

        if (! $this->db->tableExists('tasks')) {
            return $empty;
        }

        $start = date('Y-m-d 00:00:00');
        $end   = date('Y-m-d 23:59:59');
        $tasks = $this->db->table('tasks')
            ->select('id, title, due_at, owner_id')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('done_at', null)
            ->where('due_at >=', $start)
            ->where('due_at <=', $end)
            ->orderBy('due_at', 'ASC')
            ->limit(8)
            ->get()
            ->getResultArray();

        $due = $this->db->table('tasks')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('done_at', null)
            ->where('due_at >=', $start)
            ->where('due_at <=', $end)
            ->countAllResults();

        $waiting = 0;
        $unpaid  = 0;

        if ($this->db->tableExists('quotes')) {
            $waiting = $this->db->table('quotes')
                ->where('tenant_id', $tenantId)
                ->where('deleted_at', null)
                ->where('status', 'sent')
                ->countAllResults();
        }

        if ($this->db->tableExists('invoices')) {
            foreach ($this->invoices($tenantId) as $invoice) {
                if ((int) $invoice['balance_minor'] > 0) {
                    $unpaid += (int) $invoice['balance_minor'];
                }
            }
        }

        return [
            'due_today'      => $due,
            'quotes_waiting' => $waiting,
            'unpaid_minor'   => $unpaid,
            'tasks_today'    => $tasks,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasks(int $tenantId): array
    {
        return $this->db->table('tasks')
            ->select('tasks.*, users.name AS owner_name')
            ->join('users', 'users.id = tasks.owner_id', 'left')
            ->where('tasks.tenant_id', $tenantId)
            ->where('tasks.deleted_at', null)
            ->orderBy('CASE WHEN tasks.done_at IS NULL THEN 0 ELSE 1 END', 'ASC', false)
            ->orderBy('tasks.due_at', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function tasksFor(int $tenantId, string $type, int $id): array
    {
        $column = $this->linkColumn($type);

        if ($column === null) {
            return [];
        }

        return $this->db->table('tasks')
            ->select('id, title, due_at, done_at')
            ->where('tenant_id', $tenantId)
            ->where($column, $id)
            ->where('deleted_at', null)
            ->orderBy('id', 'DESC')
            ->limit(12)
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveTask(int $tenantId, ?int $id, array $fields, int $actorId): int
    {
        $title = trim((string) ($fields['title'] ?? ''));

        if ($title === '') {
            throw new RuntimeException('A task needs a title.');
        }

        $now  = date('Y-m-d H:i:s');
        $row  = [
            'title'      => mb_substr($title, 0, 190),
            'notes'      => trim((string) ($fields['notes'] ?? '')) ?: null,
            'due_at'     => $this->when($fields['due_at'] ?? null),
            'remind_at'  => $this->when($fields['remind_at'] ?? null),
            'owner_id'   => $this->memberId($tenantId, (int) ($fields['owner_id'] ?? $actorId)) ?? $actorId,
            'contact_id' => $this->nullableId($tenantId, 'contacts', $fields['contact_id'] ?? null),
            'company_id' => $this->nullableId($tenantId, 'companies', $fields['company_id'] ?? null),
            'deal_id'    => $this->nullableId($tenantId, 'deals', $fields['deal_id'] ?? null),
            'updated_at' => $now,
        ];

        if ($row['deal_id'] !== null) {
            $deal = $this->db->table('deals')->select('contact_id, company_id')->where('id', $row['deal_id'])->get()->getRowArray();
            $row['contact_id'] = $row['contact_id'] ?? ($deal['contact_id'] ?? null);
            $row['company_id'] = $row['company_id'] ?? ($deal['company_id'] ?? null);
        }

        if ($id === null) {
            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $this->db->table('tasks')->insert($row);
            $id = (int) $this->db->insertID();
        } else {
            if ($this->task($tenantId, $id) === null) {
                throw new RuntimeException('That task is not in this workspace.');
            }

            $this->db->table('tasks')->where('id', $id)->where('tenant_id', $tenantId)->update($row);
        }

        if ((int) $row['owner_id'] !== $actorId) {
            $this->notify($tenantId, (int) $row['owner_id'], 'Task assigned', $row['title'], '/app/work', null);
        }

        return $id;
    }

    public function toggleTask(int $tenantId, int $id): void
    {
        $task = $this->task($tenantId, $id);

        if ($task === null) {
            throw new RuntimeException('That task is not in this workspace.');
        }

        $this->db->table('tasks')->where('id', $id)->update([
            'done_at'    => $task['done_at'] === null ? date('Y-m-d H:i:s') : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function deleteTask(int $tenantId, int $id): void
    {
        if ($this->task($tenantId, $id) === null) {
            throw new RuntimeException('That task is not in this workspace.');
        }

        $this->db->table('tasks')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return array{tasks: list<array<string, mixed>>, meetings: list<array<string, mixed>>, month: string}
     */
    public function calendar(int $tenantId, string $month): array
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $month) || ! checkdate((int) substr($month, 5, 2), 1, (int) substr($month, 0, 4))) {
            $month = date('Y-m');
        }

        $start = $month . '-01 00:00:00';
        $end   = date('Y-m-t 23:59:59', strtotime($start));

        $tasks = $this->db->table('tasks')
            ->select('id, title, due_at, done_at')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('due_at >=', $start)
            ->where('due_at <=', $end)
            ->get()
            ->getResultArray();

        $meetings = $this->db->table('activities')
            ->select('id, body, happened_at')
            ->where('tenant_id', $tenantId)
            ->where('kind', 'meeting')
            ->where('deleted_at', null)
            ->where('happened_at >=', $start)
            ->where('happened_at <=', $end)
            ->get()
            ->getResultArray();

        return ['month' => $month, 'tasks' => $tasks, 'meetings' => $meetings];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activitiesFor(int $tenantId, string $type, int $id): array
    {
        $column = $this->linkColumn($type);

        if ($column === null || ! $this->db->tableExists('activities')) {
            return [];
        }

        return $this->db->table('activities')
            ->select('activities.*, users.name AS user_name')
            ->join('users', 'users.id = activities.user_id', 'left')
            ->where('activities.tenant_id', $tenantId)
            ->where('activities.' . $column, $id)
            ->where('activities.deleted_at', null)
            ->orderBy('activities.happened_at', 'DESC')
            ->limit(30)
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveActivity(int $tenantId, int $userId, array $fields): int
    {
        $kind = (string) ($fields['kind'] ?? 'note');

        if (! in_array($kind, ['note', 'call', 'meeting'], true)) {
            $kind = 'note';
        }

        $body = trim((string) ($fields['body'] ?? ''));

        if ($body === '') {
            throw new RuntimeException('Write what happened.');
        }

        $row = [
            'tenant_id'   => $tenantId,
            'kind'        => $kind,
            'body'        => $body,
            'user_id'     => $userId,
            'happened_at' => $this->when($fields['happened_at'] ?? null) ?? date('Y-m-d H:i:s'),
            'contact_id'  => $this->nullableId($tenantId, 'contacts', $fields['contact_id'] ?? null),
            'company_id'  => $this->nullableId($tenantId, 'companies', $fields['company_id'] ?? null),
            'deal_id'     => $this->nullableId($tenantId, 'deals', $fields['deal_id'] ?? null),
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        if ($row['deal_id'] !== null) {
            $deal = $this->db->table('deals')->select('contact_id, company_id')->where('id', $row['deal_id'])->get()->getRowArray();
            $row['contact_id'] = $row['contact_id'] ?? ($deal['contact_id'] ?? null);
            $row['company_id'] = $row['company_id'] ?? ($deal['company_id'] ?? null);
        }

        $this->db->table('activities')->insert($row);

        return (int) $this->db->insertID();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function products(int $tenantId): array
    {
        return $this->db->table('products')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('name', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function saveProduct(int $tenantId, ?int $id, array $fields): int
    {
        $name = trim((string) ($fields['name'] ?? ''));

        if ($name === '') {
            throw new RuntimeException('A product needs a name.');
        }

        $price = BookService::rupeesToMinor((string) ($fields['price'] ?? '0'));

        if ($price < 0) {
            throw new RuntimeException('Enter a price that is zero or more.');
        }

        $tax = (float) ($fields['tax_rate'] ?? 18);

        if ($tax < 0 || $tax > 100) {
            throw new RuntimeException('Tax rate must be between 0 and 100.');
        }

        $now = date('Y-m-d H:i:s');
        $row = [
            'name'        => mb_substr($name, 0, 160),
            'price_minor' => $price,
            'tax_rate'    => number_format($tax, 2, '.', ''),
            'currency'    => $this->currency($tenantId),
            'updated_at'  => $now,
        ];

        if ($id === null) {
            $row['tenant_id']  = $tenantId;
            $row['created_at'] = $now;
            $this->db->table('products')->insert($row);

            return (int) $this->db->insertID();
        }

        $exists = $this->db->table('products')->where('tenant_id', $tenantId)->where('id', $id)->where('deleted_at', null)->countAllResults();

        if ($exists < 1) {
            throw new RuntimeException('That product is not in this workspace.');
        }

        $this->db->table('products')->where('id', $id)->where('tenant_id', $tenantId)->update($row);

        return $id;
    }

    public function deleteProduct(int $tenantId, int $id): void
    {
        $this->db->table('products')->where('tenant_id', $tenantId)->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @param list<array<string, mixed>> $rawLines
     * @return list<array<string, mixed>>
     */
    public function parseLines(array $rawLines): array
    {
        $clean = [];

        foreach ($rawLines as $row) {
            if (! is_array($row)) {
                continue;
            }

            $description = trim((string) ($row['description'] ?? ''));

            if ($description === '') {
                continue;
            }

            $qty = (float) ($row['qty'] ?? 1);

            if ($qty <= 0) {
                throw new RuntimeException('Quantity must be greater than zero.');
            }

            $price = BookService::rupeesToMinor((string) ($row['price'] ?? '0'));

            if ($price < 0) {
                throw new RuntimeException('Enter a price that is zero or more.');
            }

            $tax = (float) ($row['tax'] ?? 18);

            if ($tax < 0 || $tax > 100) {
                throw new RuntimeException('Tax rate must be between 0 and 100.');
            }

            $clean[] = [
                'product_id'  => (int) ($row['product_id'] ?? 0),
                'description' => mb_substr($description, 0, 190),
                'qty'         => number_format($qty, 2, '.', ''),
                'price_minor' => $price,
                'tax_rate'    => number_format($tax, 2, '.', ''),
            ];
        }

        if ($clean === []) {
            throw new RuntimeException('Add at least one line with a description.');
        }

        return $clean;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function quotes(int $tenantId): array
    {
        $rows = $this->db->table('quotes')
            ->select('quotes.*, contacts.name AS contact_name, companies.name AS company_name')
            ->join('contacts', 'contacts.id = quotes.contact_id', 'left')
            ->join('companies', 'companies.id = quotes.company_id', 'left')
            ->where('quotes.tenant_id', $tenantId)
            ->where('quotes.deleted_at', null)
            ->orderBy('quotes.id', 'DESC')
            ->get()
            ->getResultArray();

        return $this->withTotals($rows, 'quote_items', 'quote_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quote(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('quotes')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $lines
     */
    public function saveQuote(int $tenantId, int $userId, ?int $id, array $header, array $lines): int
    {
        return $this->saveDocument('quotes', 'quote_items', 'quote_id', 'Q-', $tenantId, $userId, $id, $header, $lines);
    }

    public function sendQuote(int $tenantId, int $id): array
    {
        $quote = $this->quote($tenantId, $id);

        if ($quote === null) {
            throw new RuntimeException('That quote is not in this workspace.');
        }

        if ($quote['sent_at'] !== null) {
            throw new RuntimeException('This quote was already sent.');
        }

        $this->db->table('quotes')->where('id', $id)->update([
            'status'     => 'sent',
            'sent_at'    => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $quote;
    }

    public function deleteQuote(int $tenantId, int $id): void
    {
        if ($this->quote($tenantId, $id) === null) {
            throw new RuntimeException('That quote is not in this workspace.');
        }

        $this->db->table('quotes')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    public function invoiceFromQuote(int $tenantId, int $userId, int $quoteId): int
    {
        $quote = $this->quote($tenantId, $quoteId);

        if ($quote === null) {
            throw new RuntimeException('That quote is not in this workspace.');
        }

        $lines = $this->items('quote_items', 'quote_id', $quoteId);
        $now   = date('Y-m-d H:i:s');
        $this->db->table('invoices')->insert([
            'tenant_id'  => $tenantId,
            'number'     => $this->nextNumber($tenantId, 'invoices', 'INV-'),
            'user_id'    => $userId,
            'quote_id'   => $quoteId,
            'contact_id' => $quote['contact_id'],
            'company_id' => $quote['company_id'],
            'deal_id'    => $quote['deal_id'],
            'status'     => 'draft',
            'currency'   => $quote['currency'],
            'notes'      => $quote['notes'],
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $invoiceId = (int) $this->db->insertID();
        $this->replaceItems('invoice_items', 'invoice_id', $tenantId, $invoiceId, $lines);

        return $invoiceId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function invoices(int $tenantId): array
    {
        if (! $this->db->tableExists('invoices')) {
            return [];
        }

        $rows = $this->db->table('invoices')
            ->select('invoices.*, contacts.name AS contact_name, companies.name AS company_name')
            ->join('contacts', 'contacts.id = invoices.contact_id', 'left')
            ->join('companies', 'companies.id = invoices.company_id', 'left')
            ->where('invoices.tenant_id', $tenantId)
            ->where('invoices.deleted_at', null)
            ->orderBy('invoices.id', 'DESC')
            ->get()
            ->getResultArray();

        $rows = $this->withTotals($rows, 'invoice_items', 'invoice_id');

        foreach ($rows as &$row) {
            $paid = $this->paidSum((int) $row['id']);
            $row['paid_minor']    = $paid;
            $row['balance_minor'] = max(0, (int) $row['total_minor'] - $paid);
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function invoice(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('invoices')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $lines
     */
    public function saveInvoice(int $tenantId, int $userId, ?int $id, array $header, array $lines): int
    {
        $saved = $this->saveDocument('invoices', 'invoice_items', 'invoice_id', 'INV-', $tenantId, $userId, $id, $header, $lines);
        $this->refreshInvoice($tenantId, $saved);

        return $saved;
    }

    public function sendInvoice(int $tenantId, int $id): array
    {
        $invoice = $this->invoice($tenantId, $id);

        if ($invoice === null) {
            throw new RuntimeException('That invoice is not in this workspace.');
        }

        if ($invoice['sent_at'] === null) {
            $this->db->table('invoices')->where('id', $id)->update([
                'sent_at'    => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        $this->refreshInvoice($tenantId, $id);

        return $invoice;
    }

    public function recordPayment(int $tenantId, int $userId, int $invoiceId, int $amountMinor, string $paidOn, ?string $note): string
    {
        $invoice = $this->invoice($tenantId, $invoiceId);

        if ($invoice === null) {
            throw new RuntimeException('That invoice is not in this workspace.');
        }

        $lines   = $this->items('invoice_items', 'invoice_id', $invoiceId);
        $total   = self::summarize($lines)['total'];
        $balance = $total - $this->paidSum($invoiceId);

        if ($balance <= 0) {
            throw new RuntimeException('This invoice is already paid.');
        }

        if ($amountMinor < 1) {
            throw new RuntimeException('Enter the amount received.');
        }

        if ($amountMinor > $balance) {
            throw new RuntimeException('That is more than the balance.');
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidOn)) {
            throw new RuntimeException('Enter the date the payment arrived.');
        }

        $before = (string) $invoice['status'];
        $this->db->table('payments')->insert([
            'tenant_id'    => $tenantId,
            'invoice_id'   => $invoiceId,
            'amount_minor' => $amountMinor,
            'paid_on'      => $paidOn,
            'note'         => $note !== null && $note !== '' ? mb_substr($note, 0, 190) : null,
            'user_id'      => $userId,
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
        $status = $this->refreshInvoice($tenantId, $invoiceId);

        if ($status === 'paid' && $before !== 'paid') {
            $this->notify($tenantId, (int) $invoice['user_id'], 'Invoice paid', (string) $invoice['number'], '/app/invoices?edit=' . $invoiceId, null);
        }

        return $status;
    }

    public function deleteInvoice(int $tenantId, int $id): void
    {
        if ($this->invoice($tenantId, $id) === null) {
            throw new RuntimeException('That invoice is not in this workspace.');
        }

        if ($this->paidSum($id) > 0) {
            throw new RuntimeException('This invoice has a recorded payment, so it stays.');
        }

        $this->db->table('invoices')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function items(string $table, string $column, int $parentId): array
    {
        if (! in_array($table, ['quote_items', 'invoice_items'], true) || ! in_array($column, ['quote_id', 'invoice_id'], true)) {
            return [];
        }

        return $this->db->table($table)->where($column, $parentId)->orderBy('id', 'ASC')->get()->getResultArray();
    }

    /**
     * @return array<int, list<array<string, mixed>>>
     */
    public function groupedItems(int $tenantId, string $table, string $column): array
    {
        if (! in_array($table, ['quote_items', 'invoice_items'], true) || ! in_array($column, ['quote_id', 'invoice_id'], true)) {
            return [];
        }

        $parent = $table === 'quote_items' ? 'quotes' : 'invoices';
        $rows   = $this->db->table($table . ' i')
            ->select('i.*')
            ->join($parent . ' p', 'p.id = i.' . $column)
            ->where('p.tenant_id', $tenantId)
            ->where('p.deleted_at', null)
            ->orderBy('i.id', 'ASC')
            ->get()
            ->getResultArray();
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[(int) $row[$column]][] = $row;
        }

        return $grouped;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function dealOptions(int $tenantId): array
    {
        return $this->db->table('deals')
            ->select('id, title')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->orderBy('title', 'ASC')
            ->get()
            ->getResultArray();
    }

    /**
     * @return array{contacts: list<array<string, mixed>>, companies: list<array<string, mixed>>, deals: list<array<string, mixed>>, invoices: list<array<string, mixed>>}
     */
    public function search(int $tenantId, string $query, bool $money): array
    {
        $query = trim($query);
        $empty = ['contacts' => [], 'companies' => [], 'deals' => [], 'invoices' => []];

        if ($query === '') {
            return $empty;
        }

        $like = static function ($builder, array $columns, string $query): void {
            $builder->groupStart();
            foreach ($columns as $index => $column) {
                if ($index === 0) {
                    $builder->like($column, $query);
                } else {
                    $builder->orLike($column, $query);
                }
            }
            $builder->groupEnd();
        };

        $contacts = $this->db->table('contacts')->select('id, name, phone, email')->where('tenant_id', $tenantId)->where('deleted_at', null);
        $like($contacts, ['name', 'phone', 'email'], $query);

        $companies = $this->db->table('companies')->select('id, name, phone')->where('tenant_id', $tenantId)->where('deleted_at', null);
        $like($companies, ['name', 'phone'], $query);

        $deals = $this->db->table('deals')->select('id, title')->where('tenant_id', $tenantId)->where('deleted_at', null);
        $like($deals, ['title'], $query);

        $invoices = [];

        if ($money && $this->db->tableExists('invoices')) {
            $builder = $this->db->table('invoices')->select('id, number, status')->where('tenant_id', $tenantId)->where('deleted_at', null);
            $like($builder, ['number'], $query);
            $invoices = $builder->limit(8)->get()->getResultArray();
        }

        return [
            'contacts'  => $contacts->limit(8)->get()->getResultArray(),
            'companies' => $companies->limit(8)->get()->getResultArray(),
            'deals'     => $deals->limit(8)->get()->getResultArray(),
            'invoices'  => $invoices,
        ];
    }

    /**
     * @return array{open_minor: int, won_minor: int, won_count: int, overdue: list<array<string, mixed>>, unpaid: list<array<string, mixed>>}
     */
    public function report(int $tenantId, string $from, string $to): array
    {
        $open = $this->db->query(
            'SELECT COALESCE(SUM(deals.amount_minor), 0) AS open_minor
             FROM deals JOIN stages ON stages.id = deals.stage_id
             WHERE deals.tenant_id = ? AND deals.deleted_at IS NULL AND stages.is_won = 0 AND stages.is_lost = 0',
            [$tenantId],
        )->getRowArray();

        $won = $this->db->query(
            'SELECT COUNT(*) AS won_count, COALESCE(SUM(deals.amount_minor), 0) AS won_minor
             FROM deals JOIN stages ON stages.id = deals.stage_id
             WHERE deals.tenant_id = ? AND deals.deleted_at IS NULL AND stages.is_won = 1
               AND deals.updated_at >= ? AND deals.updated_at <= ?',
            [$tenantId, $from . ' 00:00:00', $to . ' 23:59:59'],
        )->getRowArray();

        $overdue = $this->db->table('tasks')
            ->select('id, title, due_at')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at', null)
            ->where('done_at', null)
            ->where('due_at <', date('Y-m-d H:i:s'))
            ->orderBy('due_at', 'ASC')
            ->limit(20)
            ->get()
            ->getResultArray();

        $unpaid = [];

        foreach ($this->invoices($tenantId) as $invoice) {
            if ((int) $invoice['balance_minor'] > 0) {
                $unpaid[] = $invoice;
            }
        }

        return [
            'open_minor' => (int) ($open['open_minor'] ?? 0),
            'won_minor'  => (int) ($won['won_minor'] ?? 0),
            'won_count'  => (int) ($won['won_count'] ?? 0),
            'overdue'    => $overdue,
            'unpaid'     => $unpaid,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function filesFor(int $tenantId, string $type, int $id): array
    {
        if (! $this->db->tableExists('files') || $this->fileTable($type) === null) {
            return [];
        }

        return $this->db->table('files')
            ->where('tenant_id', $tenantId)
            ->where('subject_type', $type)
            ->where('subject_id', $id)
            ->where('deleted_at', null)
            ->orderBy('id', 'DESC')
            ->get()
            ->getResultArray();
    }

    public function saveFile(int $tenantId, int $userId, string $plan, string $type, int $subjectId, UploadedFile $file): int
    {
        $table = $this->fileTable($type);

        if ($table === null || $this->nullableId($tenantId, $table, $subjectId) === null) {
            throw new RuntimeException('That record is not in this workspace.');
        }

        if (! $file->isValid()) {
            throw new RuntimeException('Choose a file to attach.');
        }

        $ext = strtolower($file->getExtension());

        if (! in_array($ext, ['pdf', 'png', 'jpg', 'jpeg', 'webp', 'txt', 'csv', 'doc', 'docx', 'xls', 'xlsx'], true)) {
            throw new RuntimeException('Attach a PDF, image, spreadsheet, or text file.');
        }

        $size     = (int) $file->getSize();
        $mime     = $file->getMimeType() ?: 'application/octet-stream';
        $original = mb_substr(basename(str_replace('\\', '/', $file->getClientName())), 0, 190);

        if ($size < 1 || $size > 8 * 1024 * 1024) {
            throw new RuntimeException('Attach a file up to 8 MB.');
        }

        $used = (int) ($this->db->query(
            'SELECT COALESCE(SUM(bytes), 0) AS used FROM files WHERE tenant_id = ? AND deleted_at IS NULL',
            [$tenantId],
        )->getRowArray()['used'] ?? 0);
        $cap = AccessCatalog::STORAGE_CAPS[$plan] ?? AccessCatalog::STORAGE_CAPS['starter'];

        if ($used + $size > $cap) {
            throw new RuntimeException('This plan’s file storage is full.');
        }

        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $tenantId;

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException('The file store is not available.');
        }

        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        $file->move($dir, $stored, true);

        $this->db->table('files')->insert([
            'tenant_id'     => $tenantId,
            'stored_name'   => $stored,
            'original_name' => $original !== '' ? $original : $stored,
            'mime'          => $mime,
            'bytes'         => $size,
            'subject_type'  => $type,
            'subject_id'    => $subjectId,
            'user_id'       => $userId,
            'created_at'    => date('Y-m-d H:i:s'),
        ]);

        return (int) $this->db->insertID();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function file(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('files')
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('deleted_at', null)
            ->get()
            ->getRowArray();

        if ($row === null) {
            return null;
        }

        if (! preg_match('/^[a-f0-9]{32}\.[a-z0-9]+$/', (string) $row['stored_name'])) {
            return null;
        }

        $base = realpath(WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . $tenantId);
        $path = $base === false ? false : realpath($base . DIRECTORY_SEPARATOR . $row['stored_name']);

        if ($base === false || $path === false || ! str_starts_with($path, $base)) {
            return null;
        }

        $row['path'] = $path;

        return $row;
    }

    public function deleteFile(int $tenantId, int $id): void
    {
        if ($this->file($tenantId, $id) === null) {
            throw new RuntimeException('That file is not in this workspace.');
        }

        $this->db->table('files')->where('id', $id)->update(['deleted_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function task(int $tenantId, int $id): ?array
    {
        $row = $this->db->table('tasks')->where('tenant_id', $tenantId)->where('id', $id)->where('deleted_at', null)->get()->getRowArray();

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $header
     * @param list<array<string, mixed>> $lines
     */
    private function saveDocument(string $table, string $itemTable, string $parentColumn, string $prefix, int $tenantId, int $userId, ?int $id, array $header, array $lines): int
    {
        $now = date('Y-m-d H:i:s');
        $row = [
            'contact_id' => $this->nullableId($tenantId, 'contacts', $header['contact_id'] ?? null),
            'company_id' => $this->nullableId($tenantId, 'companies', $header['company_id'] ?? null),
            'deal_id'    => $this->nullableId($tenantId, 'deals', $header['deal_id'] ?? null),
            'notes'      => trim((string) ($header['notes'] ?? '')) ?: null,
            'currency'   => $this->currency($tenantId),
            'updated_at' => $now,
        ];

        if ($table === 'invoices') {
            $due = trim((string) ($header['due_on'] ?? ''));
            $row['due_on'] = preg_match('/^\d{4}-\d{2}-\d{2}$/', $due) ? $due : null;
        }

        if ($id === null) {
            $row['tenant_id']  = $tenantId;
            $row['number']     = $this->nextNumber($tenantId, $table, $prefix);
            $row['user_id']    = $userId;
            $row['status']     = 'draft';
            $row['created_at'] = $now;
            $this->db->table($table)->insert($row);
            $id = (int) $this->db->insertID();
        } else {
            $exists = $this->db->table($table)->where('tenant_id', $tenantId)->where('id', $id)->where('deleted_at', null)->countAllResults();

            if ($exists < 1) {
                throw new RuntimeException('That record is not in this workspace.');
            }

            unset($row['currency']);
            $this->db->table($table)->where('id', $id)->where('tenant_id', $tenantId)->update($row);
        }

        foreach ($lines as &$line) {
            $line['product_id'] = $this->nullableId($tenantId, 'products', $line['product_id']);
        }

        $this->replaceItems($itemTable, $parentColumn, $tenantId, $id, $lines);

        return $id;
    }

    /**
     * @param list<array<string, mixed>> $lines
     */
    private function replaceItems(string $table, string $column, int $tenantId, int $parentId, array $lines): void
    {
        $this->db->table($table)->where($column, $parentId)->where('tenant_id', $tenantId)->delete();

        foreach ($lines as $line) {
            $this->db->table($table)->insert([
                'tenant_id'   => $tenantId,
                $column       => $parentId,
                'product_id'  => $line['product_id'],
                'description' => $line['description'],
                'qty'         => $line['qty'],
                'price_minor' => $line['price_minor'],
                'tax_rate'    => $line['tax_rate'],
            ]);
        }
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function withTotals(array $rows, string $itemTable, string $column): array
    {
        if ($rows === []) {
            return [];
        }

        $ids   = array_map(static fn (array $row): int => (int) $row['id'], $rows);
        $items = $this->db->table($itemTable)->whereIn($column, $ids)->get()->getResultArray();
        $grouped = [];

        foreach ($items as $item) {
            $grouped[(int) $item[$column]][] = $item;
        }

        foreach ($rows as &$row) {
            $row['total_minor'] = self::summarize($grouped[(int) $row['id']] ?? [])['total'];
        }

        return $rows;
    }

    private function refreshInvoice(int $tenantId, int $id): string
    {
        $invoice = $this->invoice($tenantId, $id);

        if ($invoice === null) {
            return 'draft';
        }

        $total   = self::summarize($this->items('invoice_items', 'invoice_id', $id))['total'];
        $paid    = $this->paidSum($id);
        $status  = $invoice['sent_at'] !== null ? 'sent' : 'draft';

        if ($paid > 0 && ($total < 1 || $paid < $total)) {
            $status = 'partial';
        }

        if ($total > 0 && $paid >= $total) {
            $status = 'paid';
        }

        $this->db->table('invoices')->where('id', $id)->update([
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        return $status;
    }

    private function paidSum(int $invoiceId): int
    {
        $row = $this->db->query(
            'SELECT COALESCE(SUM(amount_minor), 0) AS paid FROM payments WHERE invoice_id = ?',
            [$invoiceId],
        )->getRowArray();

        return (int) ($row['paid'] ?? 0);
    }

    private function nextNumber(int $tenantId, string $table, string $prefix): string
    {
        $count = $this->db->table($table)->where('tenant_id', $tenantId)->countAllResults();

        return $prefix . str_pad((string) ($count + 1), 4, '0', STR_PAD_LEFT);
    }

    private function nullableId(int $tenantId, string $table, mixed $id): ?int
    {
        $id = (int) $id;

        if ($id < 1 || ! in_array($table, ['contacts', 'companies', 'deals', 'products', 'quotes', 'invoices', 'projects', 'plots', 'leads'], true)) {
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

    private function memberId(int $tenantId, int $userId): ?int
    {
        if ($userId < 1) {
            return null;
        }

        $row = $this->db->table('tenant_users')->select('user_id')->where('tenant_id', $tenantId)->where('user_id', $userId)->get()->getRowArray();

        return $row ? $userId : null;
    }

    private function linkColumn(string $type): ?string
    {
        return match ($type) {
            'contact' => 'contact_id',
            'company' => 'company_id',
            'deal'    => 'deal_id',
            default   => null,
        };
    }

    private function fileTable(string $type): ?string
    {
        return match ($type) {
            'contact' => 'contacts',
            'company' => 'companies',
            'deal'    => 'deals',
            'quote'   => 'quotes',
            'invoice' => 'invoices',
            'project' => 'projects',
            'plot'    => 'plots',
            'lead'    => 'leads',
            default   => null,
        };
    }

    private function when(mixed $raw): ?string
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return null;
        }

        $raw = str_replace('T', ' ', $raw);

        if (strlen($raw) === 16) {
            $raw .= ':00';
        }

        $stamp = strtotime($raw);

        if ($stamp === false) {
            throw new RuntimeException('Enter a valid date and time.');
        }

        return date('Y-m-d H:i:s', $stamp);
    }
}
