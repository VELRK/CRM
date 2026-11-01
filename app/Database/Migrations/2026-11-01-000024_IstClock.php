<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Automatic timestamps were written while the app clock was UTC.
 * Move those stored clocks forward to IST. A time a person typed,
 * such as a follow-up or visit schedule, stays as they entered it.
 */
class IstClock extends Migration
{
    /** @var list<string> */
    private array $clocks = [
        'created_at',
        'updated_at',
        'deleted_at',
        'read_at',
        'sent_at',
        'last_used_at',
        'revoked_at',
        'check_in',
        'check_out',
        'done_at',
        'attended_at',
        'approved_at',
    ];

    public function up(): void
    {
        $this->shift(330);
    }

    public function down(): void
    {
        $this->shift(-330);
    }

    private function shift(int $minutes): void
    {
        $marks = implode(',', array_fill(0, count($this->clocks), '?'));
        $rows = $this->db->query(
            "SELECT TABLE_NAME, COLUMN_NAME
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND DATA_TYPE = 'datetime'
               AND COLUMN_NAME IN ({$marks})",
            $this->clocks,
        )->getResultArray();

        foreach ($rows as $row) {
            $table = str_replace('`', '', (string) ($row['TABLE_NAME'] ?? $row['table_name'] ?? ''));
            $field = str_replace('`', '', (string) ($row['COLUMN_NAME'] ?? $row['column_name'] ?? ''));

            if ($table === '' || $field === '' || ! in_array($field, $this->clocks, true)) {
                continue;
            }

            $this->db->query(
                'UPDATE `' . $table . '` SET `' . $field . '` = DATE_ADD(`' . $field . '`, INTERVAL ' . $minutes . ' MINUTE) WHERE `' . $field . '` IS NOT NULL',
            );
        }
    }
}
