<?php

declare(strict_types=1);

namespace App\Database;

use PDO;

abstract class Model
{
    protected PDO $pdo;
    protected string $table = '';
    protected string $primaryKey = 'id';
    protected array $fillable = [];
    protected array $casts = [];

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function findBy(string $field, mixed $value): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$field} = ? LIMIT 1");
        $stmt->execute([$value]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function all(): array
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC");
        return $stmt->fetchAll();
    }

    public function where(string $field, mixed $value, string $operator = '='): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$field} {$operator} ? ORDER BY {$this->primaryKey} DESC");
        $stmt->execute([$value]);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $data = $this->filterFillable($data);
        $fields = array_keys($data);
        $placeholders = array_map(fn($f) => ':' . $f, $fields);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s) RETURNING %s',
            $this->table,
            implode(', ', $fields),
            implode(', ', $placeholders),
            $this->primaryKey
        );

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($data);

        return (int) $stmt->fetchColumn();
    }

    public function update(int $id, array $data): bool
    {
        $data = $this->filterFillable($data);
        if (empty($data)) {
            return false;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');
        $fields = array_keys($data);
        $set = implode(', ', array_map(fn($f) => "{$f} = :{$f}", $fields));

        $sql = "UPDATE {$this->table} SET {$set} WHERE {$this->primaryKey} = :id";
        $data['id'] = $id;

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($data);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = ?");
        return $stmt->execute([$id]);
    }

    public function count(): int
    {
        return (int) $this->pdo->query("SELECT COUNT(*) FROM {$this->table}")->fetchColumn();
    }

    public function paginate(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $total = $this->count();

        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} ORDER BY {$this->primaryKey} DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'data' => $stmt->fetchAll(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    protected function filterFillable(array $data): array
    {
        if (empty($this->fillable)) {
            return $data;
        }
        return array_intersect_key($data, array_flip($this->fillable));
    }

    protected function castValue(string $field, mixed $value): mixed
    {
        if (!isset($this->casts[$field])) {
            return $value;
        }

        return match ($this->casts[$field]) {
            'int', 'integer' => (int) $value,
            'bool', 'boolean' => (bool) $value,
            'float', 'double' => (float) $value,
            'json', 'array' => is_string($value) ? json_decode($value, true) : $value,
            default => $value,
        };
    }

    protected function toJson(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_UNICODE);
    }

    protected function fromJson(?string $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return json_decode($value, true);
    }
}
