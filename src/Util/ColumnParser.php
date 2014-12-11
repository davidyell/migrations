<?php
namespace Migrations\Util;

use Cake\Collection\Collection;
use Cake\Utility\Hash;
use Phinx\Db\Adapter\AdapterInterface;
use ReflectionClass;

class ColumnParser
{
    public function parse($arguments)
    {
        return [
            'fields' => $this->parseFields($arguments),
            'indexes' => $this->parseIndexes($arguments),
        ];
    }

    public function parseFields($arguments)
    {
        $fields = [];
        $arguments = $this->validArguments($arguments);
        foreach ($arguments as $field) {
            preg_match('/^(\w*)(?::(\w*))?(?::(\w*))?(?::(\w*))?/', $field, $matches);
            $field = $matches[1];
            $type = Hash::get($matches, 2);

            $type = $this->getType($type, $field);
            $length = $this->getLength($type);
            $fields[$field] = [
                'columnType' => $type,
                'options' => [
                    'null' => false,
                    'default' => null,
                ]
            ];

            if ($length !== null) {
                $fields[$field]['options']['limit'] = $length;
            }
        }

        return $fields;
    }

    public function parseIndexes($arguments)
    {
        $indexes = [];
        $arguments = $this->validArguments($arguments);
        foreach ($arguments as $field) {
            preg_match('/^(\w*)(?::(\w*))?(?::(\w*))?(?::(\w*))?/', $field, $matches);
            $field = $matches[1];
            $type = Hash::get($matches, 2);
            $indexType = Hash::get($matches, 3);

            if ($type == 'primary_key') {
                $indexType = 'primary';
            }

            if ($indexType === null) {
                continue;
            }

            $indexUnique = false;
            if ($indexType == 'primary') {
                $indexName = 'PRIMARY';
                $indexUnique = true;
            } elseif ($indexType == 'unique') {
                $indexUnique = true;
            }

            if (empty($indexName)) {
                $indexName = strtoupper('BY_' . $field);
                if ($indexUnique) {
                    $indexName = strtoupper('UNIQUE_' . $field);
                }
            }

            if (empty($indexes[$indexName])) {
                $indexes[$indexName] = [
                    'columns' => [],
                    'options' => [
                        'unique' => $indexUnique,
                        'name' => $indexName,
                    ],
                ];
            }

            $indexes[$indexName]['columns'][] = $field;
        }

        return $indexes;
    }

    public function validArguments($arguments)
    {
        $collection = new Collection($arguments);
        return $collection->filter(function ($_, $field) {
            $_;
            return preg_match('/^(\w*)(?::(\w*))?(?::(\w*))?(?::(\w*))?/', $field);
        })->toArray();
    }

    public function getType($type, $field)
    {
        $reflector = new ReflectionClass('Phinx\Db\Adapter\AdapterInterface');
        $collection = new Collection($reflector->getConstants());
        $validTypes = array_values($collection->filter(function ($_, $constant) {
            $_;
            return substr($constant, 0, strlen('PHINX_TYPE_')) === 'PHINX_TYPE_';
        })->toArray());

        if ($type === null || !in_array($type, $validTypes)) {
            if ($type == 'primary_key') {
                $type = 'integer';
            } elseif ($field == 'id') {
                $type = 'integer';
            } elseif (in_array($field, ['created', 'modified', 'updated'])) {
                $type = 'datetime';
            } else {
                $type = 'string';
            }
        }

        return $type;
    }

    public function getLength($type)
    {
        $length = null;
        if ($type == 'string') {
            $length = 255;
        } elseif ($type == 'integer') {
            $length = 11;
        } elseif ($type == 'biginteger') {
            $length = 20;
        }

        return $length;
    }
}