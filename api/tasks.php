<?php
/**
 * RESTful API Endpoint untuk Tasks & Gantt Chart
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database/migrate.php';
migrateDatabase();
seedSampleDataIfEmpty();

require_once __DIR__ . '/../includes/Task.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$projectId = isset($_GET['project_id']) ? (int)$_GET['project_id'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $task = Task::getById($id);
                if (!$task) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Tugas tidak ditemukan']);
                    exit;
                }
                echo json_encode(['data' => $task]);
            } elseif ($projectId) {
                $result = Task::getByProjectId($projectId);
                echo json_encode([
                    'data' => $result['tasks'],
                    'dependencies' => $result['dependencies']
                ]);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Parameter project_id atau id diperlukan']);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['project_id']) || empty($input['name']) || empty($input['start_date']) || empty($input['end_date'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Project ID, nama tugas, tanggal mulai dan selesai wajib diisi']);
                exit;
            }
            $taskId = Task::create($input);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $taskId, 'message' => 'Tugas berhasil ditambahkan']);
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID Tugas diperlukan']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);

            // Jika hanya update tanggal & progress (quick update dari drag Gantt)
            if (isset($input['quick_update']) && $input['quick_update'] === true) {
                Task::updateDatesAndProgress(
                    $id,
                    $input['start_date'],
                    $input['end_date'],
                    isset($input['progress']) ? (int)$input['progress'] : null
                );
            } else {
                Task::update($id, $input);
            }
            echo json_encode(['success' => true, 'message' => 'Tugas berhasil diperbarui']);
            break;

        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID Tugas diperlukan']);
                exit;
            }
            Task::delete($id);
            echo json_encode(['success' => true, 'message' => 'Tugas berhasil dihapus']);
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method tidak diizinkan']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
