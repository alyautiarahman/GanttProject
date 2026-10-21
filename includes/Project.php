<?php
/**
 * Project Model
 */
require_once __DIR__ . '/../config/database.php';

class Project {
    public static function getAll(): array {
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT p.*,
                   COUNT(t.id) as total_tasks,
                   SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
                   AVG(COALESCE(t.progress, 0)) as avg_progress
            FROM projects p
            LEFT JOIN tasks t ON p.id = t.project_id
            GROUP BY p.id
            ORDER BY p.id DESC
        ");
        $results = $stmt->fetchAll();
        foreach ($results as &$r) {
            $r['avg_progress'] = round((float)($r['avg_progress'] ?? 0), 1);
        }
        return $results;
    }

    public static function getById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        return $project ?: null;
    }

    public static function create(array $data): int {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            INSERT INTO projects (title, description, start_date, end_date, status, budget)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['start_date'],
            $data['end_date'],
            $data['status'] ?? 'planning',
            (float)($data['budget'] ?? 0)
        ]);
        return (int)$db->lastInsertId();
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            UPDATE projects
            SET title = ?, description = ?, start_date = ?, end_date = ?, status = ?, budget = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");
        return $stmt->execute([
            $data['title'],
            $data['description'] ?? '',
            $data['start_date'],
            $data['end_date'],
            $data['status'] ?? 'planning',
            (float)($data['budget'] ?? 0),
            $id
        ]);
    }

    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
