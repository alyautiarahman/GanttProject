<?php
/**
 * Main Application Dashboard & Gantt Chart View
 */
require_once __DIR__ . '/database/migrate.php';
migrateDatabase();
seedSampleDataIfEmpty();
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sistem Manajemen Proyek Gantt Chart</title>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

  <!-- Top Navbar -->
  <header class="navbar">
    <div class="brand">
      <div class="brand-icon">G</div>
      <div>
        <span>GanttFlow PM</span>
        <span style="font-size: 0.75rem; font-weight: 500; color: var(--gray-600); margin-left: 8px;">Sistem Manajemen Proyek</span>
      </div>
    </div>
    <div class="nav-actions">
      <button id="btnNewProject" class="btn btn-secondary btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Proyek Baru
      </button>
      <button id="btnNewTask" class="btn btn-primary btn-sm">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
        Tambah Tugas
      </button>
    </div>
  </header>

  <!-- App Body Layout -->
  <div class="app-container">

    <!-- Sidebar Proyek -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <span class="sidebar-title">Daftar Proyek</span>
      </div>
      <ul class="project-list" id="projectList">
        <!-- Injected via gantt.js -->
      </ul>
    </aside>

    <!-- Main Content Panel -->
    <main class="main-content">

      <!-- Project Header & Key Metrics -->
      <div class="project-header">
        <div class="project-info">
          <h1>
            <span id="currentProjectTitle">Memuat Proyek...</span>
            <span id="currentProjectStatus" class="badge">--</span>
          </h1>
          <p id="currentProjectDesc">Memuat ringkasan proyek...</p>
        </div>
        <div class="project-metrics">
          <div class="metric-card">
            <div class="metric-val" id="metricTasks">0</div>
            <div class="metric-lbl">Total Tugas</div>
          </div>
          <div class="metric-card">
            <div class="metric-val" id="metricProgress">0%</div>
            <div class="metric-lbl">Progress Rata-rata</div>
          </div>
          <div class="metric-card">
            <div class="metric-val" id="metricBudget">Rp 0</div>
            <div class="metric-lbl">Anggaran</div>
          </div>
        </div>
      </div>

      <!-- Gantt Toolbar: Zoom / Scale View & Actions -->
      <div class="gantt-toolbar">
        <div class="view-controls">
          <span style="font-size: 0.8rem; font-weight: 600; color: var(--gray-600); margin-right: 4px;">Skala Waktu:</span>
          <div class="btn-group">
            <button class="view-btn active" data-view="day">Harian</button>
            <button class="view-btn" data-view="week">Mingguan</button>
            <button class="view-btn" data-view="month">Bulanan</button>
          </div>
        </div>
        <div style="font-size: 0.8rem; color: var(--gray-600); display: flex; align-items: center; gap: 14px;">
          <span>💡 <b>Petunjuk:</b> Geser bar untuk ubah tanggal, tarik ujung bar untuk ubah durasi, klik dua kali untuk edit.</span>
        </div>
      </div>

      <!-- Gantt Main Split Wrapper -->
      <div class="gantt-wrapper">

        <!-- Left Column: Task Table -->
        <div class="gantt-table-panel">
          <div class="gantt-table-header">
            <div>No</div>
            <div>Nama Tugas</div>
            <div>Progress</div>
            <div style="text-align: right;">Durasi</div>
          </div>
          <div class="gantt-table-body" id="ganttTableBody">
            <!-- Injected via gantt.js -->
          </div>
        </div>

        <!-- Right Column: Interactive Gantt Timeline Canvas -->
        <div class="gantt-timeline-panel" id="ganttTimelinePanel">
          <div class="gantt-timeline-header-wrap" id="ganttTimelineHeaderWrap">
            <div class="gantt-timeline-header" id="ganttTimelineHeader">
              <!-- Injected via gantt.js -->
            </div>
          </div>
          <div class="gantt-timeline-body-wrap" id="ganttTimelineBodyWrap">
            <div class="gantt-timeline-body" id="ganttTimelineBody">
              <!-- Grid lines, task bars, milestone diamonds, SVG dependencies -->
            </div>
          </div>
        </div>

      </div>

    </main>
  </div>

  <!-- Modal: Tambah / Edit Proyek -->
  <div class="modal-overlay" id="projectModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="projectModalTitle">Buat Proyek Baru</h3>
        <button class="modal-close">&times;</button>
      </div>
      <form id="projectForm">
        <div class="modal-body">
          <div class="form-group">
            <label for="projTitle">Nama Proyek</label>
            <input type="text" id="projTitle" class="form-control" required placeholder="Contoh: Migrasi Cloud 2026">
          </div>
          <div class="form-group">
            <label for="projDesc">Deskripsi Ringkas</label>
            <textarea id="projDesc" class="form-control" rows="3" placeholder="Tujuan dan ruang lingkup proyek..."></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="projStartDate">Tanggal Mulai</label>
              <input type="date" id="projStartDate" class="form-control" required>
            </div>
            <div class="form-group">
              <label for="projEndDate">Tanggal Target Selesai</label>
              <input type="date" id="projEndDate" class="form-control" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="projStatus">Status</label>
              <select id="projStatus" class="form-control">
                <option value="planning">Planning (Perencanaan)</option>
                <option value="in_progress">In Progress (Berjalan)</option>
                <option value="completed">Completed (Selesai)</option>
                <option value="on_hold">On Hold (Ditunda)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="projBudget">Anggaran (IDR)</label>
              <input type="number" id="projBudget" class="form-control" placeholder="Contoh: 50000000">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary modal-cancel">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan Proyek</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Modal: Tambah / Edit Tugas & Milestone -->
  <div class="modal-overlay" id="taskModal">
    <div class="modal">
      <div class="modal-header">
        <h3 id="taskModalTitle">Kelola Tugas</h3>
        <button class="modal-close">&times;</button>
      </div>
      <form id="taskForm">
        <input type="hidden" id="taskId">
        <div class="modal-body">
          <div class="form-group">
            <label for="taskName">Nama Tugas / Aktivitas</label>
            <input type="text" id="taskName" class="form-control" required placeholder="Contoh: Desain Skema Database">
          </div>
          <div class="form-group">
            <label for="taskDesc">Keterangan / Checklist</label>
            <textarea id="taskDesc" class="form-control" rows="2" placeholder="Catatan detail pekerjaan..."></textarea>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="taskStartDate">Tanggal Mulai</label>
              <input type="date" id="taskStartDate" class="form-control" required>
            </div>
            <div class="form-group">
              <label for="taskEndDate">Tanggal Selesai</label>
              <input type="date" id="taskEndDate" class="form-control" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="taskProgress">Progress Selesai: <span id="progressOutput" style="font-weight: 700; color: var(--primary);">0%</span></label>
              <input type="range" id="taskProgress" min="0" max="100" step="5" value="0" style="width: 100%;">
            </div>
            <div class="form-group">
              <label for="taskStatus">Status</label>
              <select id="taskStatus" class="form-control">
                <option value="not_started">Belum Dimulai</option>
                <option value="in_progress">Sedang Dikerjakan</option>
                <option value="completed">Selesai</option>
                <option value="blocked">Terkendala (Blocked)</option>
              </select>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label for="taskPriority">Prioritas</label>
              <select id="taskPriority" class="form-control">
                <option value="low">Rendah (Low)</option>
                <option value="medium" selected>Sedang (Medium)</option>
                <option value="high">Tinggi (High)</option>
                <option value="urgent">Mendesak (Urgent)</option>
              </select>
            </div>
            <div class="form-group">
              <label for="taskAssignee">Penanggung Jawab (PIC)</label>
              <input type="text" id="taskAssignee" class="form-control" placeholder="Contoh: Budi Santoso">
            </div>
          </div>

          <div class="form-group">
            <label for="taskDependencies">Ketergantungan (Task Predecessor)</label>
            <select id="taskDependencies" class="form-control" multiple style="height: 90px;">
              <!-- Injected via JS -->
            </select>
            <small style="color: var(--gray-600); font-size: 0.725rem;">Tahan Ctrl (Windows) / Cmd (Mac) untuk memilih lebih dari satu tugas prasyarat.</small>
          </div>

          <div class="form-row" style="align-items: center; margin-top: 10px;">
            <div class="form-group">
              <label>Warna Bar</label>
              <input type="hidden" id="taskColor" value="#3b82f6">
              <div class="color-picker-group">
                <div class="color-option selected" style="background: #3b82f6;" data-color="#3b82f6"></div>
                <div class="color-option" style="background: #10b981;" data-color="#10b981"></div>
                <div class="color-option" style="background: #f59e0b;" data-color="#f59e0b"></div>
                <div class="color-option" style="background: #ef4444;" data-color="#ef4444"></div>
                <div class="color-option" style="background: #8b5cf6;" data-color="#8b5cf6"></div>
                <div class="color-option" style="background: #ec4899;" data-color="#ec4899"></div>
                <div class="color-option" style="background: #06b6d4;" data-color="#06b6d4"></div>
              </div>
            </div>
            <div class="form-group" style="display: flex; align-items: center; gap: 8px; margin-top: 12px;">
              <input type="checkbox" id="taskIsMilestone" value="1" style="width: 18px; height: 18px;">
              <label for="taskIsMilestone" style="margin-bottom: 0; cursor: pointer;">Jadikan Titik Milestone (0 Durasi)</label>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" id="btnDeleteTask" class="btn btn-danger" style="margin-right: auto; display: none;">Hapus</button>
          <button type="button" class="btn btn-secondary modal-cancel">Batal</button>
          <button type="submit" class="btn btn-primary">Simpan</button>
        </div>
      </form>
    </div>
  </div>

  <script src="assets/js/gantt.js"></script>
</body>
</html>
