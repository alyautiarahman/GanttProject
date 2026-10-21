<?php
/**
 * RESTful API Endpoint untuk Projects
 */
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../database/migrate.php';
migrateDatabase();
seedSampleDataIfEmpty();

require_once __DIR__ . '/../includes/Project.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$id = isset($_GET['id']) ? (int)$_GET['id'] : null;

try {
    switch ($method) {
        case 'GET':
            if ($id) {
                $project = Project::getById($id);
                if (!$project) {
                    http_response_code(404);
                    echo json_encode(['error' => 'Project tidak ditemukan']);
                    exit;
                }
                echo json_encode(['data' => $project]);
            } else {
                $projects = Project::getAll();
                echo json_encode(['data' => $projects]);
            }
            break;

        case 'POST':
            $input = json_decode(file_get_contents('php://input'), true);
            if (empty($input['title']) || empty($input['start_date']) || empty($input['end_date'])) {
                http_response_code(422);
                echo json_encode(['error' => 'Judul, tanggal mulai, dan tanggal selesai wajib diisi']);
                exit;
            }
            $newId = Project::create($input);
            http_response_code(201);
            echo json_encode(['success' => true, 'id' => $newId, 'message' => 'Proyek berhasil dibuat']);
            break;

        case 'PUT':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID Proyek diperlukan']);
                exit;
            }
            $input = json_decode(file_get_contents('php://input'), true);
            Project::update($id, $input);
            echo json_encode(['success' => true, 'message' => 'Proyek berhasil diperbarui']);
            break;

        case 'DELETE':
            if (!$id) {
                http_response_code(400);
                echo json_encode(['error' => 'ID Proyek diperlukan']);
                exit;
            }
            Project::delete($id);
            echo json_encode(['success' => true, 'message' => 'Proyek berhasil dihapus']);
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
