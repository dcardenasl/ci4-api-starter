<?php

declare(strict_types=1);

namespace App\Support\Scaffolding\Generators;

use App\Support\Scaffolding\ResourceSchema;
use App\Support\Scaffolding\TypeMapper;

/**
 * MigrationGenerator
 * Generates CodeIgniter 4 migration files with automatic type mapping and FK support.
 */
class MigrationGenerator
{
    public function generate(ResourceSchema $schema): array
    {
        $timestamp = date('Y-m-d-His');
        $resourcePlural = $schema->getResourcePlural();
        $fileName = "{$timestamp}_Create{$resourcePlural}Table.php";

        return [
            APPPATH . "Database/Migrations/{$fileName}" => $this->template($schema),
        ];
    }

    private function template(ResourceSchema $schema): string
    {
        $resourcePlural = $schema->getResourcePlural();
        $table = $schema->getResourcePluralLower();
        $fieldsContent = $this->generateFields($schema);
        $foreignKeys = $this->generateForeignKeys($schema);

        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class Create{$resourcePlural}Table extends Migration
{
    public function up()
    {
        \$this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
{$fieldsContent}            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        \$this->forge->addKey('id', true);
{$foreignKeys}        \$this->forge->createTable('{$table}');
    }

    public function down()
    {
        \$this->forge->dropTable('{$table}');
    }
}
PHP;
    }

    private function generateFields(ResourceSchema $schema): string
    {
        $output = "";
        foreach ($schema->fields as $field) {
            $mapping = TypeMapper::get($field->type);
            $dbType = $mapping['db'];
            $null = $field->nullable ? 'true' : 'false';

            $output .= "            '{$field->name}' => [\n";
            $output .= "                'type' => '{$dbType}',\n";

            if ($dbType === 'VARCHAR') {
                $constraint = $field->length ?? 255;
                $output .= "                'constraint' => {$constraint},\n";
            } elseif ($dbType === 'DECIMAL') {
                $precision = $field->precision ?? '10,2';
                $output .= "                'constraint' => '{$precision}',\n";
            } elseif ($dbType === 'INT') {
                $output .= "                'unsigned' => true,\n";
            }

            if ($field->defaultValue !== null) {
                $output .= "                'default' => '{$field->defaultValue}',\n";
            }

            $output .= "                'null' => {$null},\n";
            $output .= "            ],\n";
        }

        return $output;
    }

    private function generateForeignKeys(ResourceSchema $schema): string
    {
        $output = "";
        foreach ($schema->fields as $field) {
            if ($field->fkTable) {
                $output .= "        \$this->forge->addForeignKey('{$field->name}', '{$field->fkTable}', 'id', 'CASCADE', 'SET NULL');\n";
            }
        }
        return $output;
    }
}
