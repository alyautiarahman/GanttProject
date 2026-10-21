<?php
/**
 * Task Model & Dependency Manager
 */
require_once __DIR__ . '/../config/database.php';

class Task {
    public static function getByProjectId(int $projectId): array {
        $db = Database::getConnection();
        $stmt = $db->prepare("
            SELECT * FROM tasks
            WHERE project_id = ?
            ORDER BY sort_order ASC, start_date ASC, id ASC
        ");
        $stmt->execute([$projectId]);
        $tasks = $stmt->fetchAll();

        // Ambil dependencies untuk task ini
        $taskIds = array_column($tasks, 'id');
        $dependencies = [];
        if (!empty($taskIds)) {
            $inClause = implode(',', array_fill(0, count($taskIds), '?'));
            $depStmt = $db->prepare("
                SELECT * FROM task_dependencies
                WHERE predecessor_id IN ($inClause) OR successor_id IN ($inClause)
            ");
            $depStmt->execute(array_merge($taskIds, $taskIds));
            $dependencies = $depStmt->fetchAll();
        }

        // Map dependencies to tasks
        $depsBySuccessor = [];
        foreach ($dependencies as $dep) {
            $depsBySuccessor[$dep['successor_id']][] = [
                'id' => (int)$dep['id'],
                'predecessor_id' => (int)$dep['predecessor_id'],
                'type' => $dep['dependency_type']
            ];
        }

        foreach ($tasks as &$task) {
            $task['id'] = (int)$task['id'];
            $task['project_id'] = (int)$task['project_id'];
            $task['duration_days'] = (int)$task['duration_days'];
            $task['progress'] = (int)$task['progress'];
            $task['is_milestone'] = (int)$task['is_milestone'];
            $task['sort_order'] = (int)$task['sort_order'];
            $task['parent_id'] = $task['parent_id'] ? (int)$task['parent_id'] : null;
            $task['dependencies'] = $depsBySuccessor[$task['id']] ?? [];
        }

        return [
            'tasks' => $tasks,
            'dependencies' => $dependencies
        ];
    }

    public static function getById(int $id): ?array {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM tasks WHERE id = ?");
        $stmt->execute([$id]);
        $task = $stmt->fetch();
        if ($task) {
            $task['id'] = (int)$task['id'];
            $task['progress'] = (int)$task['progress'];
            $task['is_milestone'] = (int)$task['is_milestone'];
        }
        return $task ?: null;
    }

    public static function create(array $data): int {
        $db = Database::getConnection();

        // Hitung durasi jika belum ada
        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        $interval = $startDate->diff($endDate);
        $duration = max(1, $interval->days + 1);

        $db->beginTransaction();

        $stmt = $db->prepare("
            INSERT INTO tasks (
                project_id, name, description, start_date, end_date,
                duration_days, progress, status, priority, assignee,
                color, parent_id, is_milestone, sort_order
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $data['project_id'],
            $data['name'],
            $data['description'] ?? '',
            $data['start_date'],
            $data['end_date'],
            $data['duration_days'] ?? $duration,
            $data['progress'] ?? 0,
            $data['status'] ?? 'not_started',
            $data['priority'] ?? 'medium',
            $data['assignee'] ?? '',
            $data['color'] ?? '#3b82f6',
            !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            !empty($data['is_milestone']) ? 1 : 0,
            $data['sort_order'] ?? 0
        ]);
        $taskId = (int)$db->lastInsertId();

        // Dependencies jika ada
        if (!empty($data['dependencies']) && is_array($data['dependencies'])) {
            $depStmt = $db->prepare("
                INSERT OR IGNORE INTO task_dependencies (predecessor_id, successor_id, dependency_type)
                VALUES (?, ?, ?)
            ");
            foreach ($data['dependencies'] as $predId) {
                if ((int)$predId > 0 && (int)$predId !== $taskId) {
                    $depStmt->execute([(int)$predId, $taskId, 'FS']);
                }
            }
        }

        $db->commit();
        return $taskId;
    }

    public static function update(int $id, array $data): bool {
        $db = Database::getConnection();

        $startDate = new DateTime($data['start_date']);
        $endDate = new DateTime($data['end_date']);
        $interval = $startDate->diff($endDate);
        $duration = max(1, $interval->days + 1);

        $db->beginTransaction();

        $stmt = $db->prepare("
            UPDATE tasks SET
                name = ?,
                description = ?,
                start_date = ?,
                end_date = ?,
                duration_days = ?,
                progress = ?,
                status = ?,
                priority = ?,
                assignee = ?,
                color = ?,
                parent_id = ?,
                is_milestone = ?,
                sort_order = ?,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = ?
        ");

        $stmt->execute([
            $data['name'],
            $data['description'] ?? '',
            $data['start_date'],
            $data['end_date'],
            $data['duration_days'] ?? $duration,
            $data['progress'] ?? 0,
            $data['status'] ?? 'not_started',
            $data['priority'] ?? 'medium',
            $data['assignee'] ?? '',
            $data['color'] ?? '#3b82f6',
            !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
            !empty($data['is_milestone']) ? 1 : 0,
            $data['sort_order'] ?? 0,
            $id
        ]);

        if (isset($data['dependencies']) && is_array($data['dependencies'])) {
            // Hapus dependency lama dimana task ini adalah successor
            $delDep = $db->prepare("DELETE FROM task_dependencies WHERE successor_id = ?");
            $delDep->execute([$id]);

            // Insert baru
            $depStmt = $db->prepare("
                INSERT OR IGNORE INTO task_dependencies (predecessor_id, successor_id, dependency_type)
                VALUES (?, ?, ?)
            ");
            foreach ($data['dependencies'] as $predId) {
                if ((int)$predId > 0 && (int)$predId !== $id) {
                    $depStmt->execute([(int)$predId, $id, 'FS']);
                }
            }
        }

        $db->commit();
        return true;
    }

    public static function updateDatesAndProgress(int $id, string $startDate, string $endDate, ?int $progress = null): bool {
        $db = Database::getConnection();
        $s = new DateTime($startDate);
        $e = new DateTime($endDate);
        $duration = max(1, $s->diff($e)->days + 1);

        if ($progress !== null) {
            $status = $progress >= 100 ? 'completed' : ($progress > 0 ? 'in_progress' : 'not_started');
            $stmt = $db->prepare("
                UPDATE tasks
                SET start_date = ?, end_date = ?, duration_days = ?, progress = ?, status = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$startDate, $endDate, $duration, $progress, $status, $id]);
        } else {
            $stmt = $db->prepare("
                UPDATE tasks
                SET start_date = ?, end_date = ?, duration_days = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ?
            ");
            return $stmt->execute([$startDate, $endDate, $duration, $id]);
        }
    }

    public static function delete(int $id): bool {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
        return $stmt->execute([$id]);
    }
}
