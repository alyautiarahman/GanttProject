/**
 * Gantt Chart Engine & Project Management Interactivity
 */

class GanttApp {
  constructor() {
    this.projects = [];
    this.currentProject = null;
    this.tasks = [];
    this.dependencies = [];
    this.viewMode = 'day'; // 'day', 'week', 'month'
    this.columnWidth = 42; // px per day
    this.rowHeight = 48; // px per row
    this.startDate = null;
    this.endDate = null;
    this.totalDays = 0;
    this.isDragging = false;
    this.isResizing = false;
    this.resizeEdge = null;
    this.activeTask = null;
    this.dragStartX = 0;
    this.originalStart = null;
    this.originalEnd = null;

    this.init();
  }

  async init() {
    this.bindEvents();
    await this.loadProjects();
    if (this.projects.length > 0) {
      this.selectProject(this.projects[0].id);
    }
  }

  bindEvents() {
    // Project modal
    document.getElementById('btnNewProject')?.addEventListener('click', () => this.openProjectModal());
    document.getElementById('projectForm')?.addEventListener('submit', (e) => this.handleProjectSubmit(e));

    // Task modal
    document.getElementById('btnNewTask')?.addEventListener('click', () => this.openTaskModal());
    document.getElementById('taskForm')?.addEventListener('submit', (e) => this.handleTaskSubmit(e));
    document.getElementById('btnDeleteTask')?.addEventListener('click', () => this.handleTaskDelete());

    // View controls
    document.querySelectorAll('.view-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
        e.target.classList.add('active');
        this.setViewMode(e.target.dataset.view);
      });
    });

    // Close modal on click outside
    document.querySelectorAll('.modal-overlay').forEach(modal => {
      modal.addEventListener('click', (e) => {
        if (e.target === modal) this.closeModals();
      });
    });
    document.querySelectorAll('.modal-close, .modal-cancel').forEach(btn => {
      btn.addEventListener('click', () => this.closeModals());
    });

    // Sync scroll between Left Panel and Right Canvas
    const tableBody = document.getElementById('ganttTableBody');
    const timelineBodyWrap = document.getElementById('ganttTimelineBodyWrap');
    const timelinePanel = document.getElementById('ganttTimelinePanel');
    const headerWrap = document.getElementById('ganttTimelineHeaderWrap');

    if (tableBody && timelineBodyWrap) {
      tableBody.addEventListener('scroll', () => {
        timelineBodyWrap.scrollTop = tableBody.scrollTop;
      });
      timelineBodyWrap.addEventListener('scroll', () => {
        tableBody.scrollTop = timelineBodyWrap.scrollTop;
      });
    }

    if (timelinePanel && headerWrap) {
      timelinePanel.addEventListener('scroll', () => {
        headerWrap.scrollLeft = timelinePanel.scrollLeft;
      });
    }

    // Color picker
    document.querySelectorAll('.color-option').forEach(el => {
      el.addEventListener('click', (e) => {
        document.querySelectorAll('.color-option').forEach(o => o.classList.remove('selected'));
        e.target.classList.add('selected');
        const colorInput = document.getElementById('taskColor');
        if (colorInput) colorInput.value = e.target.dataset.color;
      });
    });

    // Global drag & drop listeners
    window.addEventListener('mousemove', (e) => this.handleMouseMove(e));
    window.addEventListener('mouseup', (e) => this.handleMouseUp(e));
  }

  setViewMode(mode) {
    this.viewMode = mode;
    if (mode === 'day') this.columnWidth = 42;
    else if (mode === 'week') this.columnWidth = 24;
    else if (mode === 'month') this.columnWidth = 12;
    this.renderGantt();
  }

  async loadProjects() {
    try {
      const res = await fetch('api/projects.php');
      const data = await res.json();
      this.projects = data.data || [];
      this.renderProjectSidebar();
    } catch (err) {
      console.error('Failed to load projects', err);
    }
  }

  renderProjectSidebar() {
    const list = document.getElementById('projectList');
    if (!list) return;
    list.innerHTML = '';

    this.projects.forEach(p => {
      const li = document.createElement('li');
      li.className = `project-item ${this.currentProject?.id === p.id ? 'active' : ''}`;
      li.onclick = () => this.selectProject(p.id);

      li.innerHTML = `
        <div class="project-item-title">${this.escapeHtml(p.title)}</div>
        <div class="project-item-meta">
          <span>${p.completed_tasks || 0}/${p.total_tasks || 0} Selesai</span>
          <span>${p.avg_progress || 0}%</span>
        </div>
        <div class="progress-bar-bg">
          <div class="progress-bar-fill" style="width: ${p.avg_progress || 0}%"></div>
        </div>
      `;
      list.appendChild(li);
    });
  }

  async selectProject(projectId) {
    const p = this.projects.find(x => x.id === projectId);
    if (!p) return;
    this.currentProject = p;

    // Update Header
    document.getElementById('currentProjectTitle').textContent = p.title;
    document.getElementById('currentProjectDesc').textContent = p.description || 'Tidak ada deskripsi';
    document.getElementById('currentProjectStatus').textContent = p.status.toUpperCase();
    document.getElementById('currentProjectStatus').className = `badge badge-${p.status}`;
    document.getElementById('metricTasks').textContent = p.total_tasks || 0;
    document.getElementById('metricProgress').textContent = (p.avg_progress || 0) + '%';
    document.getElementById('metricBudget').textContent = 'Rp ' + Number(p.budget || 0).toLocaleString('id-ID');

    // Highlight sidebar
    this.renderProjectSidebar();

    // Load tasks
    await this.loadTasks(projectId);
  }

  async loadTasks(projectId) {
    try {
      const res = await fetch(`api/tasks.php?project_id=${projectId}`);
      const data = await res.json();
      this.tasks = data.data || [];
      this.dependencies = data.dependencies || [];
      this.populateDependencySelect();
      this.renderGantt();
    } catch (err) {
      console.error('Failed to load tasks', err);
    }
  }

  populateDependencySelect() {
    const sel = document.getElementById('taskDependencies');
    if (!sel) return;
    sel.innerHTML = '';
    this.tasks.forEach(t => {
      const opt = document.createElement('option');
      opt.value = t.id;
      opt.textContent = `#${t.id} - ${t.name}`;
      sel.appendChild(opt);
    });
  }

  computeDateRange() {
    if (!this.tasks || this.tasks.length === 0) {
      const now = new Date();
      this.startDate = new Date(now.getFullYear(), now.getMonth(), 1);
      this.endDate = new Date(now.getFullYear(), now.getMonth() + 2, 0);
    } else {
      let min = new Date(this.tasks[0].start_date);
      let max = new Date(this.tasks[0].end_date);

      this.tasks.forEach(t => {
        const s = new Date(t.start_date);
        const e = new Date(t.end_date);
        if (s < min) min = s;
        if (e > max) max = e;
      });

      // Buffer 4 days before & 10 days after
      min.setDate(min.getDate() - 4);
      max.setDate(max.getDate() + 10);

      this.startDate = min;
      this.endDate = max;
    }

    const diffTime = Math.abs(this.endDate - this.startDate);
    this.totalDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
  }

  renderGantt() {
    this.computeDateRange();
    this.renderLeftTable();
    this.renderTimelineHeader();
    this.renderTimelineGridAndBars();
    this.renderDependencyLines();
  }

  renderLeftTable() {
    const body = document.getElementById('ganttTableBody');
    if (!body) return;
    body.innerHTML = '';

    if (this.tasks.length === 0) {
      body.innerHTML = `<div style="padding: 24px; text-align: center; color: #94a3b8;">Belum ada tugas. Klik tombol "+ Tambah Tugas".</div>`;
      return;
    }

    this.tasks.forEach((task, idx) => {
      const row = document.createElement('div');
      row.className = 'gantt-table-row';
      row.id = `table-row-${task.id}`;
      row.onclick = () => this.openTaskModal(task);

      row.innerHTML = `
        <div style="font-weight: 600; color: #64748b;">${idx + 1}</div>
        <div class="gantt-task-name" title="${this.escapeHtml(task.name)}">
          ${task.is_milestone ? '<span class="milestone-icon">◆</span>' : ''}
          ${this.escapeHtml(task.name)}
        </div>
        <div>
          <span class="badge badge-${task.status}">${task.progress}%</span>
        </div>
        <div style="font-size: 0.75rem; color: #64748b; text-align: right;">
          ${task.duration_days}h
        </div>
      `;
      body.appendChild(row);
    });
  }

  renderTimelineHeader() {
    const headerContainer = document.getElementById('ganttTimelineHeader');
    if (!headerContainer) return;
    headerContainer.innerHTML = '';

    const todayStr = new Date().toISOString().split('T')[0];
    const curr = new Date(this.startDate);

    for (let i = 0; i < this.totalDays; i++) {
      const dateStr = curr.toISOString().split('T')[0];
      const dayNum = curr.getDate();
      const dayName = curr.toLocaleDateString('id-ID', { weekday: 'narrow' });
      const monthName = curr.toLocaleDateString('id-ID', { month: 'short' });
      const isWeekend = curr.getDay() === 0 || curr.getDay() === 6;
      const isToday = dateStr === todayStr;

      const col = document.createElement('div');
      col.className = `timeline-col-header ${isWeekend ? 'weekend' : ''} ${isToday ? 'today' : ''}`;
      col.style.width = `${this.columnWidth}px`;
      col.style.minWidth = `${this.columnWidth}px`;

      if (this.viewMode === 'day') {
        col.innerHTML = `
          <div style="font-size: 0.65rem;">${dayName}</div>
          <div style="font-size: 0.8rem;">${dayNum}</div>
        `;
      } else {
        col.innerHTML = `
          <div style="font-size: 0.65rem;">${dayNum === 1 || i === 0 ? monthName : ''}</div>
          <div style="font-size: 0.75rem;">${dayNum}</div>
        `;
      }

      headerContainer.appendChild(col);
      curr.setDate(curr.getDate() + 1);
    }

    headerContainer.style.width = `${this.totalDays * this.columnWidth}px`;
  }

  renderTimelineGridAndBars() {
    const bodyWrap = document.getElementById('ganttTimelineBody');
    if (!bodyWrap) return;
    bodyWrap.innerHTML = '';
    bodyWrap.style.width = `${this.totalDays * this.columnWidth}px`;

    // 1. Grid Background Lines
    const gridLines = document.createElement('div');
    gridLines.className = 'timeline-grid-lines';
    gridLines.style.width = `${this.totalDays * this.columnWidth}px`;

    const todayStr = new Date().toISOString().split('T')[0];
    const curr = new Date(this.startDate);

    for (let i = 0; i < this.totalDays; i++) {
      const dateStr = curr.toISOString().split('T')[0];
      const isWeekend = curr.getDay() === 0 || curr.getDay() === 6;
      const isToday = dateStr === todayStr;

      const line = document.createElement('div');
      line.className = `timeline-grid-col ${isWeekend ? 'weekend' : ''} ${isToday ? 'today-line' : ''}`;
      line.style.width = `${this.columnWidth}px`;
      line.style.minWidth = `${this.columnWidth}px`;
      gridLines.appendChild(line);

      curr.setDate(curr.getDate() + 1);
    }
    bodyWrap.appendChild(gridLines);

    // 2. SVG overlay for dependencies
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('class', 'gantt-svg-overlay');
    svg.setAttribute('id', 'ganttSvg');
    svg.style.width = `${this.totalDays * this.columnWidth}px`;
    svg.style.height = `${Math.max(300, this.tasks.length * this.rowHeight)}px`;

    // Marker arrow
    const defs = document.createElementNS('http://www.w3.org/2000/svg', 'defs');
    defs.innerHTML = `
      <marker id="arrow" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
        <path d="M 0 1 L 10 5 L 0 9 z" class="dep-arrow"/>
      </marker>
      <marker id="arrow-highlight" viewBox="0 0 10 10" refX="6" refY="5" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
        <path d="M 0 1 L 10 5 L 0 9 z" fill="#4f46e5"/>
      </marker>
    `;
    svg.appendChild(defs);
    bodyWrap.appendChild(svg);

    // 3. Task rows & Bars
    this.tasks.forEach((task, index) => {
      const row = document.createElement('div');
      row.className = 'timeline-row';
      row.id = `timeline-row-${task.id}`;

      const tStart = new Date(task.start_date);
      const tEnd = new Date(task.end_date);

      const offsetDays = Math.max(0, Math.round((tStart - this.startDate) / (1000 * 60 * 60 * 24)));
      const durationDays = Math.max(1, Math.round((tEnd - tStart) / (1000 * 60 * 60 * 24)) + 1);

      const leftPx = offsetDays * this.columnWidth;
      const widthPx = durationDays * this.columnWidth;

      if (task.is_milestone) {
        // Render milestone diamond
        const milestone = document.createElement('div');
        milestone.className = 'gantt-milestone';
        milestone.id = `bar-${task.id}`;
        milestone.style.left = `${leftPx + (this.columnWidth / 2) - 11}px`;
        milestone.title = `${task.name} (${task.start_date})`;
        milestone.onclick = () => this.openTaskModal(task);
        row.appendChild(milestone);
      } else {
        // Render Task Bar
        const bar = document.createElement('div');
        bar.className = 'gantt-bar-wrap';
        bar.id = `bar-${task.id}`;
        bar.style.left = `${leftPx}px`;
        bar.style.width = `${Math.max(28, widthPx - 4)}px`;
        bar.style.backgroundColor = task.color || '#3b82f6';
        bar.dataset.taskId = task.id;

        // Progress fill
        const progress = document.createElement('div');
        progress.className = 'gantt-bar-progress';
        progress.style.width = `${task.progress}%`;

        // Title
        const title = document.createElement('span');
        title.className = 'gantt-bar-title';
        title.textContent = `${task.name} (${task.progress}%)`;

        // Resize handles
        const leftHandle = document.createElement('div');
        leftHandle.className = 'bar-resize-handle bar-resize-left';
        leftHandle.onmousedown = (e) => this.initResize(e, task, 'left');

        const rightHandle = document.createElement('div');
        rightHandle.className = 'bar-resize-handle bar-resize-right';
        rightHandle.onmousedown = (e) => this.initResize(e, task, 'right');

        // Drag Bar
        bar.onmousedown = (e) => {
          if (e.target.classList.contains('bar-resize-handle')) return;
          this.initDrag(e, task);
        };

        bar.ondblclick = () => this.openTaskModal(task);

        bar.appendChild(leftHandle);
        bar.appendChild(progress);
        bar.appendChild(title);
        bar.appendChild(rightHandle);
        row.appendChild(bar);
      }

      bodyWrap.appendChild(row);
    });
  }

  renderDependencyLines() {
    const svg = document.getElementById('ganttSvg');
    if (!svg) return;

    // Bersihkan garis sebelumnya kecuali defs
    const existingPaths = svg.querySelectorAll('path.dep-line');
    existingPaths.forEach(p => p.remove());

    if (!this.dependencies || this.dependencies.length === 0) return;

    this.dependencies.forEach(dep => {
      const predBar = document.getElementById(`bar-${dep.predecessor_id}`);
      const succBar = document.getElementById(`bar-${dep.successor_id}`);

      if (!predBar || !succBar) return;

      const predRowIndex = this.tasks.findIndex(t => t.id === Number(dep.predecessor_id));
      const succRowIndex = this.tasks.findIndex(t => t.id === Number(dep.successor_id));

      if (predRowIndex === -1 || succRowIndex === -1) return;

      const predLeft = parseFloat(predBar.style.left);
      const predWidth = predBar.offsetWidth;
      const succLeft = parseFloat(succBar.style.left);

      // Titik A: Akhir bar predecessor
      const startX = predLeft + predWidth;
      const startY = (predRowIndex * this.rowHeight) + 24;

      // Titik B: Awal bar successor
      const endX = succLeft;
      const endY = (succRowIndex * this.rowHeight) + 24;

      // Path kurva siku / orthogonal
      const midX = startX + 14;
      let d = '';

      if (endX >= startX + 20) {
        // Normal finish to start forward
        d = `M ${startX} ${startY} L ${midX} ${startY} L ${midX} ${endY} L ${endX} ${endY}`;
      } else {
        // Predecessor berakhir setelah successor mulai (overlap atau backward loop)
        const loopDown = Math.max(startY, endY) + 18;
        d = `M ${startX} ${startY} L ${startX + 10} ${startY} L ${startX + 10} ${loopDown} L ${endX - 10} ${loopDown} L ${endX - 10} ${endY} L ${endX} ${endY}`;
      }

      const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
      path.setAttribute('d', d);
      path.setAttribute('class', 'dep-line');
      path.setAttribute('marker-end', 'url(#arrow)');
      path.dataset.predId = dep.predecessor_id;
      path.dataset.succId = dep.successor_id;

      svg.appendChild(path);
    });
  }

  // Dragging & Resizing Interactions
  initDrag(e, task) {
    if (task.is_milestone) return;
    this.isDragging = true;
    this.activeTask = task;
    this.dragStartX = e.clientX;
    this.originalStart = new Date(task.start_date);
    this.originalEnd = new Date(task.end_date);
    document.body.style.cursor = 'grabbing';
    e.preventDefault();
  }

  initResize(e, task, edge) {
    this.isResizing = true;
    this.resizeEdge = edge;
    this.activeTask = task;
    this.dragStartX = e.clientX;
    this.originalStart = new Date(task.start_date);
    this.originalEnd = new Date(task.end_date);
    document.body.style.cursor = 'ew-resize';
    e.stopPropagation();
    e.preventDefault();
  }

  handleMouseMove(e) {
    if (!this.isDragging && !this.isResizing) return;
    const deltaX = e.clientX - this.dragStartX;
    const daysShift = Math.round(deltaX / this.columnWidth);

    const bar = document.getElementById(`bar-${this.activeTask.id}`);
    if (!bar) return;

    if (this.isDragging) {
      const newStart = new Date(this.originalStart);
      newStart.setDate(newStart.getDate() + daysShift);
      const newEnd = new Date(this.originalEnd);
      newEnd.setDate(newEnd.getDate() + daysShift);

      const offsetDays = Math.round((newStart - this.startDate) / (1000 * 60 * 60 * 24));
      bar.style.left = `${offsetDays * this.columnWidth}px`;
    } else if (this.isResizing) {
      if (this.resizeEdge === 'right') {
        const newEnd = new Date(this.originalEnd);
        newEnd.setDate(newEnd.getDate() + daysShift);
        if (newEnd >= this.originalStart) {
          const durationDays = Math.round((newEnd - this.originalStart) / (1000 * 60 * 60 * 24)) + 1;
          bar.style.width = `${Math.max(28, (durationDays * this.columnWidth) - 4)}px`;
        }
      } else if (this.resizeEdge === 'left') {
        const newStart = new Date(this.originalStart);
        newStart.setDate(newStart.getDate() + daysShift);
        if (newStart <= this.originalEnd) {
          const offsetDays = Math.round((newStart - this.startDate) / (1000 * 60 * 60 * 24));
          const durationDays = Math.round((this.originalEnd - newStart) / (1000 * 60 * 60 * 24)) + 1;
          bar.style.left = `${offsetDays * this.columnWidth}px`;
          bar.style.width = `${Math.max(28, (durationDays * this.columnWidth) - 4)}px`;
        }
      }
    }

    this.renderDependencyLines();
  }

  async handleMouseUp(e) {
    if (!this.isDragging && !this.isResizing) return;

    const deltaX = e.clientX - this.dragStartX;
    const daysShift = Math.round(deltaX / this.columnWidth);

    let updatedStart = new Date(this.originalStart);
    let updatedEnd = new Date(this.originalEnd);

    if (this.isDragging) {
      updatedStart.setDate(updatedStart.getDate() + daysShift);
      updatedEnd.setDate(updatedEnd.getDate() + daysShift);
    } else if (this.isResizing) {
      if (this.resizeEdge === 'right') {
        updatedEnd.setDate(updatedEnd.getDate() + daysShift);
        if (updatedEnd < updatedStart) updatedEnd = new Date(updatedStart);
      } else if (this.resizeEdge === 'left') {
        updatedStart.setDate(updatedStart.getDate() + daysShift);
        if (updatedStart > updatedEnd) updatedStart = new Date(updatedEnd);
      }
    }

    const startStr = updatedStart.toISOString().split('T')[0];
    const endStr = updatedEnd.toISOString().split('T')[0];

    // Quick Update ke backend
    try {
      await fetch(`api/tasks.php?id=${this.activeTask.id}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          quick_update: true,
          start_date: startStr,
          end_date: endStr,
          progress: this.activeTask.progress
        })
      });

      // Update model lokal
      this.activeTask.start_date = startStr;
      this.activeTask.end_date = endStr;
      this.activeTask.duration_days = Math.max(1, Math.round((updatedEnd - updatedStart) / (1000 * 60 * 60 * 24)) + 1);
    } catch (err) {
      console.error('Failed to update task dates', err);
    }

    this.isDragging = false;
    this.isResizing = false;
    this.activeTask = null;
    document.body.style.cursor = 'default';

    this.renderGantt();
  }

  // Modals & Forms
  openProjectModal() {
    document.getElementById('projectModalTitle').textContent = 'Buat Proyek Baru';
    document.getElementById('projectForm').reset();
    document.getElementById('projStartDate').value = new Date().toISOString().split('T')[0];
    const nextMonth = new Date();
    nextMonth.setMonth(nextMonth.getMonth() + 1);
    document.getElementById('projEndDate').value = nextMonth.toISOString().split('T')[0];
    document.getElementById('projectModal').classList.add('active');
  }

  async handleProjectSubmit(e) {
    e.preventDefault();
    const payload = {
      title: document.getElementById('projTitle').value,
      description: document.getElementById('projDesc').value,
      start_date: document.getElementById('projStartDate').value,
      end_date: document.getElementById('projEndDate').value,
      status: document.getElementById('projStatus').value,
      budget: parseFloat(document.getElementById('projBudget').value || 0)
    };

    try {
      const res = await fetch('api/projects.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();
      if (data.success) {
        this.closeModals();
        await this.loadProjects();
        this.selectProject(data.id);
      }
    } catch (err) {
      alert('Gagal menyimpan proyek: ' + err.message);
    }
  }

  openTaskModal(task = null) {
    if (!this.currentProject) {
      alert('Pilih atau buat proyek terlebih dahulu');
      return;
    }

    const form = document.getElementById('taskForm');
    form.reset();

    const titleEl = document.getElementById('taskModalTitle');
    const deleteBtn = document.getElementById('btnDeleteTask');

    if (task) {
      titleEl.textContent = 'Edit Tugas / Milestone';
      deleteBtn.style.display = 'inline-flex';
      document.getElementById('taskId').value = task.id;
      document.getElementById('taskName').value = task.name;
      document.getElementById('taskDesc').value = task.description || '';
      document.getElementById('taskStartDate').value = task.start_date;
      document.getElementById('taskEndDate').value = task.end_date;
      document.getElementById('taskProgress').value = task.progress;
      document.getElementById('progressOutput').textContent = task.progress + '%';
      document.getElementById('taskStatus').value = task.status;
      document.getElementById('taskPriority').value = task.priority;
      document.getElementById('taskAssignee').value = task.assignee || '';
      document.getElementById('taskColor').value = task.color || '#3b82f6';
      document.getElementById('taskIsMilestone').checked = task.is_milestone === 1;

      // Select dependencies
      const depSel = document.getElementById('taskDependencies');
      Array.from(depSel.options).forEach(opt => {
        opt.selected = task.dependencies && task.dependencies.some(d => d.predecessor_id === Number(opt.value));
      });
    } else {
      titleEl.textContent = 'Tambah Tugas Baru';
      deleteBtn.style.display = 'none';
      document.getElementById('taskId').value = '';
      document.getElementById('taskStartDate').value = new Date().toISOString().split('T')[0];
      const nextWeek = new Date();
      nextWeek.setDate(nextWeek.getDate() + 7);
      document.getElementById('taskEndDate').value = nextWeek.toISOString().split('T')[0];
      document.getElementById('taskProgress').value = 0;
      document.getElementById('progressOutput').textContent = '0%';
      document.getElementById('taskColor').value = '#3b82f6';
    }

    document.getElementById('taskModal').classList.add('active');
  }

  async handleTaskSubmit(e) {
    e.preventDefault();
    const id = document.getElementById('taskId').value;
    const depSel = document.getElementById('taskDependencies');
    const selectedDeps = Array.from(depSel.selectedOptions).map(opt => Number(opt.value));

    const payload = {
      project_id: this.currentProject.id,
      name: document.getElementById('taskName').value,
      description: document.getElementById('taskDesc').value,
      start_date: document.getElementById('taskStartDate').value,
      end_date: document.getElementById('taskEndDate').value,
      progress: parseInt(document.getElementById('taskProgress').value, 10),
      status: document.getElementById('taskStatus').value,
      priority: document.getElementById('taskPriority').value,
      assignee: document.getElementById('taskAssignee').value,
      color: document.getElementById('taskColor').value,
      is_milestone: document.getElementById('taskIsMilestone').checked ? 1 : 0,
      dependencies: selectedDeps
    };

    try {
      const url = id ? `api/tasks.php?id=${id}` : 'api/tasks.php';
      const method = id ? 'PUT' : 'POST';

      const res = await fetch(url, {
        method: method,
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      });
      const data = await res.json();

      if (data.success) {
        this.closeModals();
        await this.loadProjects();
        await this.selectProject(this.currentProject.id);
      }
    } catch (err) {
      alert('Gagal menyimpan tugas: ' + err.message);
    }
  }

  async handleTaskDelete() {
    const id = document.getElementById('taskId').value;
    if (!id || !confirm('Yakin ingin menghapus tugas ini?')) return;

    try {
      const res = await fetch(`api/tasks.php?id=${id}`, { method: 'DELETE' });
      const data = await res.json();
      if (data.success) {
        this.closeModals();
        await this.loadProjects();
        await this.selectProject(this.currentProject.id);
      }
    } catch (err) {
      alert('Gagal menghapus tugas: ' + err.message);
    }
  }

  closeModals() {
    document.querySelectorAll('.modal-overlay').forEach(m => m.classList.remove('active'));
  }

  escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>'"]/g, tag => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      "'": '&#39;',
      '"': '&quot;'
    }[tag] || tag));
  }
}

// Inisialisasi saat DOM siap
document.addEventListener('DOMContentLoaded', () => {
  window.app = new GanttApp();

  // Range slider label sync
  const pInput = document.getElementById('taskProgress');
  const pOutput = document.getElementById('progressOutput');
  if (pInput && pOutput) {
    pInput.addEventListener('input', () => {
      pOutput.textContent = `${pInput.value}%`;
    });
  }
});
