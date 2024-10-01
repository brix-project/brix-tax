<?php

namespace Brix\Tax\Helper;

/**
 * @template T
 */
class CSVEntityTable
{
    private string $classString;
    private array $data = [];
    private array $columns = [];
    private array $keyDefinitions = [];
    private ?string $filePath;
    private array $columnOrder = [];

    /**
     * @param class-string<T> $classString
     * @param string|null $filePath
     */
    public function __construct(string $classString, string $filePath=null)
    {
        $this->classString = $classString;
        $this->filePath = $filePath;

        // Analysiere die Eigenschaften der Klasse, um die Spalten zu bestimmen
        $this->analyzeColumns();
        if ($filePath !== null) {
            $this->load();
        }
    }

    private function analyzeColumns(): void
    {
        $reflect = new \ReflectionClass($this->classString);
        $properties = $reflect->getProperties(\ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();
            if (strpos($name, '_') !== 0) {
                $this->columns[] = $name;
            }
        }
    }

    public function load(string $filePath=null): void
    {
        if ($filePath !== null) {
            $this->filePath = $filePath;
        }
        if (!file_exists($this->filePath)) {
            return;
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new Exception("Konnte die Datei nicht öffnen: {$this->filePath}");
        }

        // Lese die erste Zeile als Header, um die Spaltenreihenfolge zu bestimmen
        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);
            return;
        }

        $this->columnOrder = $header;

        while (($row = fgetcsv($handle)) !== false) {
          
            if (count($row) === 1 && $row[0] === null) {
                continue; // Skip empty rows
            }
            $object = (new \ReflectionClass($this->classString))->newInstanceWithoutConstructor();
            foreach ($header as $index => $column) {
                $value = $row[$index] ?? null;
                if (in_array($column, $this->columns)) {
                    $property = $column;
                    $value = $this->deserializeValue($object, $property, $value);
                    $object->$property = $value;
                }
            }
            $this->data[] = $object;
        }

        fclose($handle);
    }

    private function deserializeValue($object, string $property, $value)
    {
        $reflect = new \ReflectionProperty($object, $property);
        $type = $reflect->getType();

        if ($type instanceof \ReflectionNamedType) {
            $typeName = $type->getName();

            if ($typeName === 'array') {
                // Annahme: Es handelt sich um ein String-Array, serialisiert als JSON
                return json_decode($value, true) ?? [];
            } elseif ($typeName === 'bool') {
                return strtolower($value) === 'y';
            } elseif ($typeName === 'int') {
                return (int)$value;
            } elseif ($typeName === 'float') {
                return (float)$value;
            } else {
                return $value;
            }
        }

        return $value;
    }

    private function serializeValue($value)
    {
        if (is_array($value)) {
            // Serialisiere Arrays als JSON
            return json_encode($value);
        } elseif (is_bool($value)) {
            // Boolesche Werte als 'y' oder 'n'
            return $value ? 'y' : 'n';
        } else {
            return $value;
        }
    }

    public function save(): void
    {
        $handle = fopen($this->filePath, 'w');
        if ($handle === false) {
            throw new \Exception("Konnte die Datei nicht zum Schreiben öffnen: {$this->filePath}");
        }

        // Schreibe Header
        if (!empty($this->columnOrder)) {
            $header = $this->columnOrder;
        } else {
            $header = $this->columns;
        }

        fputcsv($handle, $header);

        foreach ($this->data as $object) {
            $row = [];
            foreach ($header as $column) {
                if (property_exists($object, $column)) {
                    $value = $object->$column;
                    $value = $this->serializeValue($value);
                    $row[] = $value;
                } else {
                    $row[] = null;
                }
            }
            fputcsv($handle, $row);
        }

        fclose($handle);
    }

    /**
     * @param array $conditions
     * @return array<T>
     */
    public function query(array $conditions): array
    {
        $results = [];

        foreach ($this->data as $object) {
            $match = true;
            foreach ($conditions as $property => $value) {
                if (!property_exists($object, $property) || $object->$property != $value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $results[] = $object;
            }
        }

        return $results;
    }


    /**
     * @param array $condition
     * @return T|null
     */
    public function select(array $condition) : ?object
    {
        $results = $this->query($condition);
        if (count($results) === 0) {
            return null;
        }
        return $results[0];        
    }

    public function sort(string $column, string $order = 'ASC'): void
    {
        usort($this->data, function ($a, $b) use ($column, $order) {
            if (!property_exists($a, $column) || !property_exists($b, $column)) {
                return 0;
            }

            if ($a->$column == $b->$column) {
                return 0;
            }

            if ($order === 'ASC') {
                return ($a->$column < $b->$column) ? -1 : 1;
            } else {
                return ($a->$column > $b->$column) ? -1 : 1;
            }
        });
    }

    public function addKeyDefinition(array $columns): void
    {
        $this->keyDefinitions[] = $columns;
    }

    private function validateUniqueConstraints($object, &$constaintColumns, &$constraintValues): bool
    {
        foreach ($this->keyDefinitions as $keyColumns) {
            foreach ($this->data as $existingObject) {
                $isDuplicate = true;
                foreach ($keyColumns as $column) {
                    if ($object->$column != $existingObject->$column) {
                        $isDuplicate = false;
                        break;
                    }
                }
                if ($isDuplicate) {
                    $constaintColumns = $keyColumns;
                    $constraintValues = array_map(function($column) use ($object) {
                        return $object->$column;
                    }, $keyColumns);
                    return false;
                }
            }
        }
        return true;
    }

    public function addObject($object): void
    {
        if (!$this->validateUniqueConstraints($object, $columns, $values)) {
            throw new \Exception("Unique Constraint violated for columns " . implode(", ", $columns) . " with values " . implode(", ", $values));
        }
        $this->data[] = $object;
    }

    public function removeObject($object): void
    {
        foreach ($this->data as $index => $existingObject) {
            if ($existingObject === $object) {
                unset($this->data[$index]);
                $this->data = array_values($this->data);
                return;
            }
        }
    }

    /**
     * @return array<T>
     */
    public function getData(): array
    {
        return $this->data;
    }
}