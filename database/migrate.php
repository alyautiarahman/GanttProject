<?php
/**
 * Inisialisasi Skema Database & Migrasi Otomatis
 */
require_once __DIR__ . '/../config/database.php';

function migrateDatabase(): void {
    $db = Database::getConnection();

    // Table: projects
    $db->exec("
        CREATE TABLE IF NOT EXISTS projects (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            status VARCHAR(50) DEFAULT 'planning', -- 'planning', 'in_progress', 'completed', 'on_hold'
            budget DECIMAL(15,2) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
    ");

    // Table: tasks
    $db->exec("
        CREATE TABLE IF NOT EXISTS tasks (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            project_id INTEGER NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            duration_days INTEGER NOT NULL DEFAULT 1,
            progress INTEGER NOT NULL DEFAULT 0, -- 0 to 100
            status VARCHAR(50) DEFAULT 'not_started', -- 'not_started', 'in_progress', 'completed', 'blocked'
            priority VARCHAR(50) DEFAULT 'medium', -- 'low', 'medium', 'high', 'urgent'
            assignee VARCHAR(100),
            color VARCHAR(30) DEFAULT '#3b82f6',
            parent_id INTEGER NULL,
            is_milestone INTEGER DEFAULT 0, -- 0 = task, 1 = milestone
            sort_order INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES tasks(id) ON DELETE SET NULL
        );
    ");

    // Table: task_dependencies
    $db->exec("
        CREATE TABLE IF NOT EXISTS task_dependencies (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            predecessor_id INTEGER NOT NULL, -- task yang harus selesai dulu
            successor_id INTEGER NOT NULL,   -- task yang bergantung
            dependency_type VARCHAR(10) DEFAULT 'FS', -- FS (Finish-to-Start), SS, FF, SF
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (predecessor_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (successor_id) REFERENCES tasks(id) ON DELETE CASCADE,
            UNIQUE(predecessor_id, successor_id)
        );
    ");
}

function seedSampleDataIfEmpty(): void {
    $db = Database::getConnection();

    $stmt = $db->query("SELECT COUNT(*) as cnt FROM projects");
    $count = (int)$stmt->fetch()['cnt'];

    if ($count > 0) {
        return; // Sudah ada data
    }

    // Buat Proyek 1: Implementasi Sistem ERP & Mobile App
    $db->beginTransaction();

    $today = date('Y-m-d');
    $pStart = date('Y-m-d', strtotime('-10 days'));
    $pEnd = date('Y-m-d', strtotime('+50 days'));

    $stmt = $db->prepare("
        INSERT INTO projects (title, description, start_date, end_date, status, budget)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        'Pengembangan Sistem ERP & Mobile App 2026',
        'Proyek transformasi digital untuk modul Supply Chain, Inventory, dan Mobile Dispatcher.',
        $pStart,
        $pEnd,
        'in_progress',
        175000000.00
    ]);
    $projectId = (int)$db->lastInsertId();

    // Data Tasks Proyek 1
    $tasksData = [
        [
            'name' => 'Kick-off Meeting & Analisis Kebutuhan',
            'desc' => 'Wawancara stakeholder, pengumpulan user requirements dan sign-off BRD',
            'start' => date('Y-m-d', strtotime('-10 days')),
            'end' => date('Y-m-d', strtotime('-5 days')),
            'duration' => 6,
            'progress' => 100,
            'status' => 'completed',
            'priority' => 'high',
            'assignee' => 'Budi Santoso (PM)',
            'color' => '#10b981',
            'milestone' => 0,
            'order' => 1
        ],
        [
            'name' => 'Sign-off Dokumen BRD & FSD',
            'desc' => 'Persetujuan resmi spesifikasi desain fungsional',
            'start' => date('Y-m-d', strtotime('-5 days')),
            'end' => date('Y-m-d', strtotime('-5 days')),
            'duration' => 1,
            'progress' => 100,
            'status' => 'completed',
            'priority' => 'urgent',
            'assignee' => 'Direksi & Stakeholder',
            'color' => '#f59e0b',
            'milestone' => 1,
            'order' => 2
        ],
        [
            'name' => 'Desain UI/UX & Wireframing',
            'desc' => 'High-fidelity Figma prototype untuk modul web dashboard dan mobile app',
            'start' => date('Y-m-d', strtotime('-4 days')),
            'end' => date('Y-m-d', strtotime('+3 days')),
            'duration' => 8,
            'progress' => 85,
            'status' => 'in_progress',
            'priority' => 'medium',
            'assignee' => 'Sarah Putri (UI/UX)',
            'color' => '#8b5cf6',
            'milestone' => 0,
            'order' => 3
        ],
        [
            'name' => 'Setup Arsitektur Backend & Database PDO',
            'desc' => 'Perancangan ERD, skema migrasi database, autentikasi JWT/Session, dan RESTful base',
            'start' => date('Y-m-d', strtotime('-3 days')),
            'end' => date('Y-m-d', strtotime('+5 days')),
            'duration' => 9,
            'progress' => 70,
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee' => 'Ahmad Fauzi (Lead Dev)',
            'color' => '#3b82f6',
            'milestone' => 0,
            'order' => 4
        ],
        [
            'name' => 'Pengembangan Modul Inventory & Gudang',
            'desc' => 'CRUD master data barang, stock opname, mutasi barang dan laporan persediaan',
            'start' => date('Y-m-d', strtotime('+4 days')),
            'end' => date('Y-m-d', strtotime('+18 days')),
            'duration' => 15,
            'progress' => 15,
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee' => 'Rian Hidayat (Backend Dev)',
            'color' => '#06b6d4',
            'milestone' => 0,
            'order' => 5
        ],
        [
            'name' => 'Pengembangan Mobile App Driver & Dispatcher',
            'desc' => 'Aplikasi Flutter/React Native untuk pelacakan rute pengiriman dan scan QR',
            'start' => date('Y-m-d', strtotime('+6 days')),
            'end' => date('Y-m-d', strtotime('+22 days')),
            'duration' => 17,
            'progress' => 0,
            'status' => 'not_started',
            'priority' => 'medium',
            'assignee' => 'Dewi Lestari (Mobile Dev)',
            'color' => '#ec4899',
            'milestone' => 0,
            'order' => 6
        ],
        [
            'name' => 'Integrasi API Payment Gateway & Notifikasi WhatsApp',
            'desc' => 'Koneksi payment gateway dan webhook auto WhatsApp alert',
            'start' => date('Y-m-d', strtotime('+16 days')),
            'end' => date('Y-m-d', strtotime('+26 days')),
            'duration' => 11,
            'progress' => 0,
            'status' => 'not_started',
            'priority' => 'medium',
            'assignee' => 'Ahmad Fauzi (Lead Dev)',
            'color' => '#6366f1',
            'milestone' => 0,
            'order' => 7
        ],
        [
            'name' => 'Alpha Release & QA Integration Testing',
            'desc' => 'Uji beban, unit test, security test dan bug fixing internal',
            'start' => date('Y-m-d', strtotime('+26 days')),
            'end' => date('Y-m-d', strtotime('+35 days')),
            'duration' => 10,
            'progress' => 0,
            'status' => 'not_started',
            'priority' => 'high',
            'assignee' => 'Kevin Pratama (QA Engineer)',
            'color' => '#14b8a6',
            'milestone' => 0,
            'order' => 8
        ],
        [
            'name' => 'Milestone: UAT (User Acceptance Test) Lolos',
            'desc' => 'Verifikasi dan persetujuan pengguna operasional gudang & logistik',
            'start' => date('Y-m-d', strtotime('+36 days')),
            'end' => date('Y-m-d', strtotime('+36 days')),
            'duration' => 1,
            'progress' => 0,
            'status' => 'not_started',
            'priority' => 'urgent',
            'assignee' => 'Budi Santoso & Client Team',
            'color' => '#ef4444',
            'milestone' => 1,
            'order' => 9
        ],
        [
            'name' => 'Pelatihan Staff & Go-Live Deployment',
            'desc' => 'Training user, migrasi data live, switch DNS server produksi',
            'start' => date('Y-m-d', strtotime('+37 days')),
            'end' => date('Y-m-d', strtotime('+45 days')),
            'duration' => 9,
            'progress' => 0,
            'status' => 'not_started',
            'priority' => 'urgent',
            'assignee' => 'All Team & DevOps',
            'color' => '#84cc16',
            'milestone' => 0,
            'order' => 10
        ]
    ];

    $taskIds = [];
    $stmtTask = $db->prepare("
        INSERT INTO tasks (project_id, name, description, start_date, end_date, duration_days, progress, status, priority, assignee, color, is_milestone, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($tasksData as $idx => $t) {
        $stmtTask->execute([
            $projectId,
            $t['name'],
            $t['desc'],
            $t['start'],
            $t['end'],
            $t['duration'],
            $t['progress'],
            $t['status'],
            $t['priority'],
            $t['assignee'],
            $t['color'],
            $t['milestone'],
            $t['order']
        ]);
        $taskIds[$idx + 1] = (int)$db->lastInsertId();
    }

    // Dependencies (predecessor -> successor)
    // 1 -> 2 (Analisis -> Signoff)
    // 2 -> 3 (Signoff -> UI/UX)
    // 2 -> 4 (Signoff -> Backend)
    // 4 -> 5 (Backend -> Inventory)
    // 3 -> 6 (UI/UX -> Mobile)
    // 5 -> 7 (Inventory -> Payment)
    // 6 -> 8 (Mobile -> QA)
    // 7 -> 8 (Payment -> QA)
    // 8 -> 9 (QA -> UAT Milestone)
    // 9 -> 10 (UAT -> Go-Live)
    $deps = [
        [1, 2],
        [2, 3],
        [2, 4],
        [4, 5],
        [3, 6],
        [5, 7],
        [6, 8],
        [7, 8],
        [8, 9],
        [9, 10]
    ];

    $stmtDep = $db->prepare("
        INSERT INTO task_dependencies (predecessor_id, successor_id, dependency_type)
        VALUES (?, ?, 'FS')
    ");

    foreach ($deps as $d) {
        if (isset($taskIds[$d[0]], $taskIds[$d[1]])) {
            $stmtDep->execute([$taskIds[$d[0]], $taskIds[$d[1]]]);
        }
    }

    // Buat Proyek 2: Redesign Website & SEO Marketing
    $stmt->execute([
        'Redesign Portal Corporate & SEO Campaign',
        'Pembaruan tampilan website institusi dengan optimasi Core Web Vitals dan sistem blog.',
        date('Y-m-d', strtotime('-2 days')),
        date('Y-m-d', strtotime('+28 days')),
        'in_progress',
        45000000.00
    ]);
    $p2Id = (int)$db->lastInsertId();

    $p2Tasks = [
        ['Auditing SEO & Konten Lama', date('Y-m-d', strtotime('-2 days')), date('Y-m-d', strtotime('+3 days')), 6, 100, 'completed', 'medium', 'Nadia (SEO)', '#10b981', 0, 1],
        ['Prototyping Landing Page Baru', date('Y-m-d', strtotime('+4 days')), date('Y-m-d', strtotime('+12 days')), 9, 30, 'in_progress', 'high', 'Sarah Putri', '#8b5cf6', 0, 2],
        ['Slicing HTML/Tailwind & CMS Integration', date('Y-m-d', strtotime('+13 days')), date('Y-m-d', strtotime('+22 days')), 10, 0, 'not_started', 'high', 'Doni (Frontend)', '#3b82f6', 0, 3],
        ['Launch & Review Ranking', date('Y-m-d', strtotime('+23 days')), date('Y-m-d', strtotime('+23 days')), 1, 0, 'not_started', 'urgent', 'Marketing Lead', '#ef4444', 1, 4]
    ];

    $p2TaskIds = [];
    foreach ($p2Tasks as $idx => $t) {
        $stmtTask->execute([
            $p2Id, $t[0], 'Tugas untuk proyek website corporate', $t[1], $t[2], $t[3], $t[4], $t[5], $t[6], $t[7], $t[8], $t[9], $t[10]
        ]);
        $p2TaskIds[$idx + 1] = (int)$db->lastInsertId();
    }

    $stmtDep->execute([$p2TaskIds[1], $p2TaskIds[2]]);
    $stmtDep->execute([$p2TaskIds[2], $p2TaskIds[3]]);
    $stmtDep->execute([$p2TaskIds[3], $p2TaskIds[4]]);

    $db->commit();
}
