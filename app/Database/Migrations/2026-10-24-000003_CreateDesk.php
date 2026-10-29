<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDesk extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('tenants', [
            'currency'     => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR', 'after' => 'plan_code'],
            'fiscal_month' => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 4, 'after' => 'currency'],
        ]);

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'kind'        => ['type' => 'VARCHAR', 'constraint' => 16],
            'body'        => ['type' => 'TEXT'],
            'contact_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'company_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'deal_id'     => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'user_id'     => ['type' => 'INT', 'unsigned' => true],
            'happened_at' => ['type' => 'DATETIME'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('activities');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'due_at'     => ['type' => 'DATETIME', 'null' => true],
            'remind_at'  => ['type' => 'DATETIME', 'null' => true],
            'owner_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'contact_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'deal_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'done_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'due_at']);
        $this->forge->createTable('tasks');

        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            'name'        => ['type' => 'VARCHAR', 'constraint' => 160],
            'price_minor' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'tax_rate'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '18.00'],
            'currency'    => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('products');

        $this->moneyTable('quotes');
        $this->itemTable('quote_items', 'quote_id');
        $this->moneyTable('invoices', true);
        $this->itemTable('invoice_items', 'invoice_id');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
            'invoice_id'   => ['type' => 'INT', 'unsigned' => true],
            'amount_minor' => ['type' => 'BIGINT', 'unsigned' => true],
            'paid_on'      => ['type' => 'DATE'],
            'note'         => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('invoice_id');
        $this->forge->createTable('payments');

        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'INT', 'unsigned' => true],
            'stored_name'   => ['type' => 'VARCHAR', 'constraint' => 80],
            'original_name' => ['type' => 'VARCHAR', 'constraint' => 190],
            'mime'          => ['type' => 'VARCHAR', 'constraint' => 120],
            'bytes'         => ['type' => 'INT', 'unsigned' => true],
            'subject_type'  => ['type' => 'VARCHAR', 'constraint' => 16],
            'subject_id'    => ['type' => 'INT', 'unsigned' => true],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'subject_type', 'subject_id']);
        $this->forge->createTable('files');

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 190],
            'body'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'href'       => ['type' => 'VARCHAR', 'constraint' => 190, 'null' => true],
            'task_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'read_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'read_at']);
        $this->forge->createTable('notifications');

        $this->forge->addField([
            'id'           => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'INT', 'unsigned' => true],
            'user_id'      => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'action'       => ['type' => 'VARCHAR', 'constraint' => 64],
            'subject_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'subject_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'detail'       => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('audit_logs');
    }

    public function down(): void
    {
        foreach (['audit_logs', 'notifications', 'files', 'payments', 'invoice_items', 'invoices', 'quote_items', 'quotes', 'products', 'tasks', 'activities'] as $table) {
            $this->forge->dropTable($table, true);
        }

        $this->forge->dropColumn('tenants', ['fiscal_month', 'currency']);
    }

    private function moneyTable(string $table, bool $invoice = false): void
    {
        $fields = [
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'INT', 'unsigned' => true],
            'number'     => ['type' => 'VARCHAR', 'constraint' => 32],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'contact_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'company_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'deal_id'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'status'     => ['type' => 'VARCHAR', 'constraint' => 16, 'default' => 'draft'],
            'currency'   => ['type' => 'CHAR', 'constraint' => 3, 'default' => 'INR'],
            'notes'      => ['type' => 'TEXT', 'null' => true],
            'sent_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ];

        if ($invoice) {
            $fields['quote_id'] = ['type' => 'INT', 'unsigned' => true, 'null' => true];
            $fields['due_on']   = ['type' => 'DATE', 'null' => true];
        }

        $this->forge->addField($fields);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable($table);
    }

    private function itemTable(string $table, string $parent): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'INT', 'unsigned' => true],
            $parent       => ['type' => 'INT', 'unsigned' => true],
            'product_id'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'description' => ['type' => 'VARCHAR', 'constraint' => 190],
            'qty'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => '1.00'],
            'price_minor' => ['type' => 'BIGINT', 'unsigned' => true, 'default' => 0],
            'tax_rate'    => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => '18.00'],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey($parent);
        $this->forge->createTable($table);
    }
}
