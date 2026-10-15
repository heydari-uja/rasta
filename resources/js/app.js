import Alpine from "alpinejs";
import {
    createElement,
    House,
    CheckCheck,
    FolderClosed,
    Tags,
    Settings,
    Plus,
    Bell,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ArrowUpLeft,
    ArrowLeft,
    MoreHorizontal,
    CalendarDays,
    Flag,
    Users,
    LayoutGrid,
    List,
    Check,
    X,
    Menu,
    LogOut,
    GripVertical,
    Paperclip,
    MessageCircle,
    History,
    Trash2,
    Pencil,
    Target,
    Sun,
    ArrowUpRight,
    CheckCircle2,
    Upload,
    SlidersHorizontal,
    FolderOpen,
    PanelRightClose,
    Link,
    LoaderCircle,
} from "lucide";
const icons = {
    House,
    CheckCheck,
    FolderClosed,
    Tags,
    Settings,
    Plus,
    Bell,
    ChevronLeft,
    ChevronRight,
    ChevronDown,
    ArrowUpLeft,
    ArrowLeft,
    MoreHorizontal,
    CalendarDays,
    Flag,
    Users,
    LayoutGrid,
    List,
    Check,
    X,
    Menu,
    LogOut,
    GripVertical,
    Paperclip,
    MessageCircle,
    History,
    Trash2,
    Pencil,
    Target,
    Sun,
    ArrowUpRight,
    CheckCircle2,
    Upload,
    SlidersHorizontal,
    FolderOpen,
    PanelRightClose,
    Link,
    LoaderCircle,
};
const fa = (n) => new Intl.NumberFormat("fa-IR").format(n ?? 0);
const today = () =>
    new Intl.DateTimeFormat("en-CA", {
        timeZone: "Asia/Tehran",
        year: "numeric",
        month: "2-digit",
        day: "2-digit",
    }).format(new Date());
const cleanPersianDate = (value) => value.replace(/[،,]/g, " ").replace(/\s+/g, " ").trim();
const date = (d) =>
    d
        ? cleanPersianDate(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
              weekday: "long",
              day: "numeric",
              month: "long",
              year: "numeric",
          }).format(new Date(d + "T12:00:00")))
        : "بدون تاریخ";
const commentTime = (d) => cleanPersianDate(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
    day: "numeric",
    month: "long",
    hour: "2-digit",
    minute: "2-digit",
}).format(new Date(d)));
const clone = (x) => JSON.parse(JSON.stringify(x));
Alpine.magic(
    "icon",
    () => (name) =>
        createElement(icons[name] || FolderClosed, {
            width: 20,
            height: 20,
            "stroke-width": 1.7,
            "aria-hidden": "true",
        }).outerHTML,
);
Alpine.data("datePicker", () => ({
    value: "",
    open: false,
    cursor: new Date(),
    days: [],
    heading: "",
    calendarStyle: "",
    init() {
        this.$watch("value", () => {
            if (this.value) this.cursor = new Date(this.value + "T12:00:00");
            this.render();
        });
        this.render();
    },
    parts(d) {
        return Object.fromEntries(
            new Intl.DateTimeFormat("en-US-u-ca-persian", {
                year: "numeric",
                month: "numeric",
                day: "numeric",
            })
                .formatToParts(d)
                .filter((p) => p.type !== "literal")
                .map((p) => [p.type, +p.value]),
        );
    },
    render() {
        const start = new Date(this.cursor);
        start.setDate(start.getDate() - this.parts(start).day + 1);
        this.cursor = start;
        this.heading = cleanPersianDate(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
            month: "long",
            year: "numeric",
        }).format(start));
        const offset = (start.getDay() + 1) % 7;
        this.days = Array.from({ length: 42 }, (_, i) => {
            const d = new Date(start);
            d.setDate(d.getDate() + i - offset);
            const iso = [
                d.getFullYear(),
                String(d.getMonth() + 1).padStart(2, "0"),
                String(d.getDate()).padStart(2, "0"),
            ].join("-");
            return {
                iso,
                label: fa(this.parts(d).day),
                muted: this.parts(d).month !== this.parts(start).month,
            };
        });
    },
    position() {
        const rect = this.$refs.trigger?.getBoundingClientRect();
        if (!rect) return;
        const width = Math.min(270, window.innerWidth - 24);
        const left = Math.max(12, Math.min(window.innerWidth - width - 12, rect.right - width));
        const below = rect.bottom + 8;
        const top = below + 330 < window.innerHeight ? below : Math.max(12, rect.top - 330);
        this.calendarStyle = `position:fixed;top:${top}px;left:${left}px;width:${width}px`;
    },
    move(n) {
        const d = new Date(this.cursor);
        d.setDate(d.getDate() + (n > 0 ? 32 : -1));
        this.cursor = d;
        this.render();
    },
    label() {
        return date(this.value);
    },
}));
Alpine.data("workspace", () => ({
    user: {},
    projects: [],
    members: [],
    tasks: [],
    labels: [],
    notices: [],
    page: "dashboard",
    projectId: null,
    loading: true,
    loadError: "",
    sidebar: false,
    notificationsOpen: false,
    view: "board",
    taskFilter: "active",
    selectedProject: "all",
    toast: "",
    toastError: false,
    busy: false,
    errors: {},
    modal: null,
    confirmBox: null,
    modalSnapshot: "",
    taskForm: {},
    pendingFiles: [],
    commentText: "",
    labelQuery: "",
    newLabelName: "",
    inlineStage: false,
    inlineStageForm: {},
    projectStep: 1,
    projectForm: {},
    stageForm: {},
    stageKind: "product",
    stageId: null,
    stageUser: null,
    stageProjectId: null,
    labelForm: {},
    goalForm: {},
    memberForm: { member_id: "" },
    dragIndex: null,
    dragStage: null,
    teamDrag: null,
    taskDrag: null,
    inlineColumn: null,
    inlineColumnForm: { title: "", description: "", product_stage_id: "" },
    columnRename: null,
    columnRenameTitle: "",
    taskAutosaveTimer: null,
    taskSaving: false,
    expandedSubtasks: JSON.parse(localStorage.getItem("rasta-subtasks-open") || "{}"),
    account: {},
    theme: localStorage.getItem("rasta-theme") || "system",
    async init() {
        this.applyTheme();
        window.matchMedia("(prefers-color-scheme: dark)").addEventListener("change", () => {
            if (this.theme === "system") this.applyTheme();
        });
        await this.refresh();
        this.route();
        window.addEventListener("popstate", () => this.route());
        window.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                if (this.confirmBox) this.confirmBox = null;
                else if (this.modal) this.closeModal();
                else {
                    this.notificationsOpen = false;
                }
            }

        });
        window.addEventListener("beforeunload", (e) => {
            if (this.isDirty()) {
                e.preventDefault();
                e.returnValue = "";
            }
        });
    },
    async api(url, method = "GET", data = null) {
        const headers = {
            Accept: "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')
                .content,
        };
        if (!(data instanceof FormData))
            headers["Content-Type"] = "application/json";
        let res;
        try {
            res = await fetch("/api" + url, {
                method,
                headers,
                body: data
                    ? data instanceof FormData
                        ? data
                        : JSON.stringify(data)
                    : null,
            });
        } catch {
            throw { message: "ارتباط برقرار نشد. دوباره تلاش کنید." };
        }
        if (res.status === 401 || res.status === 419) {
            window.location = "/login";
            throw { message: "لطفاً دوباره وارد شوید." };
        }
        const result = res.status === 204 ? {} : await res.json();
        if (!res.ok) throw result;
        return result;
    },
    async refresh() {
        try {
            const d = await this.api("/workspace");
            Object.assign(this, d);
            this.account = { name: d.user.name, job_title: d.user.job_title };
            this.loadError = "";
        } catch (e) {
            this.loadError = e.message || "دریافت اطلاعات ممکن نشد.";
        } finally {
            this.loading = false;
        }
    },
    route() {
        const path = location.pathname.split("/").filter(Boolean);
        this.page = path[0] || "dashboard";
        this.projectId =
            this.page === "projects" && path[1] ? Number(path[1]) : null;
        if (this.page === "tasks" && path[1])
            this.openTask(this.tasks.find((t) => t.id === Number(path[1])));
    },
    go(page, id = null) {
        this.page = page;
        this.projectId = id;
        this.sidebar = false;
        history.pushState({}, "", `/${page}${id ? "/" + id : ""}`);
        window.scrollTo(0, 0);
    },
    fa,
    date,
    today,
    fullDate() {
        return cleanPersianDate(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
            weekday: "long",
            day: "numeric",
            month: "long",
            year: "numeric",
            timeZone: "Asia/Tehran",
        }).format(new Date()));
    },
    commentTime,
    relativeTime(value) {
        const diff = new Date(value).getTime() - Date.now();
        const abs = Math.abs(diff);
        const rtf = new Intl.RelativeTimeFormat("fa-IR", { numeric: "auto" });
        if (abs < 60 * 60 * 1000) return rtf.format(Math.round(diff / 60000), "minute");
        if (abs < 24 * 60 * 60 * 1000) return rtf.format(Math.round(diff / 3600000), "hour");
        return rtf.format(Math.round(diff / 86400000), "day");
    },
    activityText(a) {
        const name = a.member?.name || "یک عضو";
        if (a.type === "created") return `${name} تسک را ساخت`;
        if (a.type === "title") return `${name} عنوان را از «${a.old_value}» به «${a.new_value}» تغییر داد`;
        if (a.type === "start_date") return `${name} تاریخ شروع را از ${date(a.old_value)} به ${date(a.new_value)} تغییر داد`;
        if (a.type === "due_date") return `${name} سررسید را از ${date(a.old_value)} به ${date(a.new_value)} تغییر داد`;
        if (a.type === "stage") return `${name} مرحله را از «${a.old_value || "بدون مرحله"}» به «${a.new_value || "بدون مرحله"}» تغییر داد`;
        if (a.type === "assignee") return `${name} مسئول را از «${a.old_value || "بدون مسئول"}» به «${a.new_value || "بدون مسئول"}» تغییر داد`;
        if (a.type === "completed") return `${name} تسک را تکمیل کرد`;
        if (a.type === "reopened") return `${name} تسک را دوباره باز کرد`;
        return `${name} تسک را تغییر داد`;
    },
    applyTheme() {
        const dark = this.theme === "dark" || (this.theme === "system" && window.matchMedia("(prefers-color-scheme: dark)").matches);
        document.documentElement.classList.toggle("dark", dark);
        document.documentElement.dataset.theme = this.theme;
    },
    setTheme(theme) {
        this.theme = theme;
        localStorage.setItem("rasta-theme", theme);
        this.applyTheme();
    },
    get currentProject() {
        return this.projects.find((p) => p.id === this.projectId);
    },
    get myTasks() {
        return this.tasks.filter(
            (t) =>
                t.owner_id === this.user.id ||
                t.members.some((m) => m.id === this.user.id),
        );
    },
    get todayTasks() {
        return this.myTasks.filter(
            (t) => !t.completed_at && t.due_date === today(),
        );
    },
    get deadlines() {
        const end=new Date(today()+"T12:00:00"); end.setDate(end.getDate()+7);
        const limit=end.toLocaleDateString("en-CA");
        return this.myTasks.filter(t=>!t.completed_at&&t.due_date>=today()&&t.due_date<=limit);
    },
    get upcoming() {
        return [...this.myTasks]
            .filter((t) => !t.completed_at)
            .sort((a, b) => a.due_date.localeCompare(b.due_date))
            .slice(0, 5);
    },
    get unread() {
        return this.notices.filter((n) => !n.read_at).length;
    },
    get boardProjects() {
        return this.projects.filter(
            (p) =>
                (this.selectedProject === "all" ||
                    p.id == this.selectedProject) &&
                (this.page !== "tasks" ||
                    this.myTasks.some((t) => t.project_id === p.id) ||
                    this.projects.length === 1),
        );
    },
    initials(name) {
        return (name || "")
            .split(" ")
            .map((x) => x[0])
            .slice(0, 2)
            .join("");
    },
    member(id) {
        return (
            this.projects.flatMap((p) => p.members).find((m) => m.id === id) ||
            this.user
        );
    },
    color(id) {
        return [`var(--avatar-1)`, `var(--avatar-2)`, `var(--avatar-3)`, `var(--avatar-4)`][Number(id) % 4];
    },
    stageColor(i) {
        return `var(--stage-${(i % 5) + 1})`;
    },
    myStages(project) {
        return (project?.member_stages || []).filter((s) => s.member_id === this.user.id);
    },
    beginTaskDrag(task) {
        this.taskDrag = task.id;
    },
    async dropTaskOnProductStage(stage) {
        if (!this.taskDrag) return;
        const task = this.tasks.find((item) => item.id === this.taskDrag);
        this.taskDrag = null;
        if (!task || task.product_stage_id === stage.id) return;
        try {
            const updated = await this.api(`/tasks/${task.id}/product-stage`, "PATCH", { product_stage_id: stage.id });
            task.product_stage_id = updated.product_stage_id;
            task.member_stage_id = updated.member_stage_id;
            this.notify("مرحله محصول تسک تغییر کرد.");
        } catch (e) {
            this.fail(e);
        }
    },
    startInlineColumn(kind, project) {
        this.inlineColumn = { kind, project_id: project.id };
        this.inlineColumnForm = { title: "", description: "", product_stage_id: kind === "member" ? (project.stages[0]?.id || "") : "" };
        this.$nextTick(() => document.querySelector('[data-inline-column-input]')?.focus());
    },
    cancelInlineColumn() {
        this.inlineColumn = null;
        this.inlineColumnForm = { title: "", description: "", product_stage_id: "" };
    },
    async saveInlineColumn(kind, project) {
        if (!this.inlineColumnForm.title.trim()) return;
        const payload = {
            title: this.inlineColumnForm.title.trim(),
            description: this.inlineColumnForm.description.trim() || "",
            start_date: project.goal?.start_date || today(),
            end_date: project.goal?.target_date || today(),
        };
        if (kind === "member") {
            payload.member_id = this.user.id;
            payload.product_stage_id = this.inlineColumnForm.product_stage_id;
        }
        try {
            await this.api(`/projects/${project.id}/stages`, "POST", payload);
            await this.refresh();
            this.inlineColumnForm.title = "";
            this.inlineColumnForm.description = "";
            this.notify("ستون جدید ساخته شد.");
            this.$nextTick(() => document.querySelector('[data-inline-column-input]')?.focus());
        } catch (e) {
            this.fail(e);
        }
    },
    beginColumnRename(stage, kind, project, user = null) {
        this.columnRename = { id: stage.id, kind, project_id: project.id, user_id: user?.id || stage.member_id || null };
        this.columnRenameTitle = stage.title;
        this.$nextTick(() => document.querySelector('[data-column-rename-input]')?.focus());
    },
    cancelColumnRename() {
        this.columnRename = null;
        this.columnRenameTitle = "";
    },
    async saveColumnRename(stage, kind, project) {
        const title = this.columnRenameTitle.trim();
        if (!title) return;
        try {
            await this.api(`/projects/${project.id}/stages/${kind}/${stage.id}`, "PUT", {
                title,
                description: stage.description || "",
                start_date: stage.start_date || null,
                end_date: stage.end_date || null,
                ...(kind === "member" ? { product_stage_id: stage.product_stage_id } : {}),
            });
            stage.title = title;
            this.cancelColumnRename();
            this.notify("ذخیره شد");
        } catch (e) { this.fail(e); }
    },
    subtasksExpanded(task) {
        return !!this.expandedSubtasks[task.id];
    },
    toggleSubtasks(task) {
        this.expandedSubtasks[task.id] = !this.expandedSubtasks[task.id];
        this.expandedSubtasks = { ...this.expandedSubtasks };
        localStorage.setItem("rasta-subtasks-open", JSON.stringify(this.expandedSubtasks));
    },
    async toggleBoardSubtask(task, subtask) {
        const payload = {
            ...clone(task),
            labels: task.labels.map((item) => item.id),
            members: task.members.map((item) => item.id),
            completed: !!task.completed_at,
            subtasks: task.subtasks.map((item) => ({ id: item.id, title: item.title, completed: item.id === subtask.id ? !item.completed_at : !!item.completed_at })),
        };
        try {
            await this.api(`/tasks/${task.id}`, "PUT", payload);
            subtask.completed_at = subtask.completed_at ? null : new Date().toISOString();
            this.notify("ذخیره شد");
        } catch (e) { this.fail(e); }
    },
    scheduleTaskAutosave() {
        if (!this.taskForm.id || !this.canWrite(this.formProject)) return;
        clearTimeout(this.taskAutosaveTimer);
        this.taskAutosaveTimer = setTimeout(() => this.autosaveTask(), 500);
    },
    async autosaveTask() {
        if (!this.taskForm.id || this.taskSaving || !this.canWrite(this.formProject)) return;
        clearTimeout(this.taskAutosaveTimer);
        this.taskSaving = true;
        this.errors = {};
        try {
            await this.api(`/tasks/${this.taskForm.id}`, "PUT", this.taskForm);
            const local = this.tasks.find((item) => item.id === this.taskForm.id);
            if (local) {
                Object.assign(local, { title: this.taskForm.title, description: this.taskForm.description, owner_id: this.taskForm.owner_id, member_stage_id: this.taskForm.member_stage_id, product_stage_id: this.taskForm.product_stage_id, start_date: this.taskForm.start_date, due_date: this.taskForm.due_date });
            }
            this.modalSnapshot = this.snapshot();
            this.notify("ذخیره شد");
        } catch (e) { this.fail(e); } finally { this.taskSaving = false; }
    },
    autosaveTaskNow() {
        if (!this.taskForm.id) return;
        clearTimeout(this.taskAutosaveTimer);
        return this.autosaveTask();
    },
    async closeTaskComposer() {
        if (!this.taskForm.id) return this.closeModal();
        await this.autosaveTaskNow();
        this.modal = null;
    },
    taskNeighbor(delta) {
        if (!this.taskForm.id) return;
        const list = this.tasks.filter((item) => item.project_id === this.taskForm.project_id);
        const index = list.findIndex((item) => item.id === this.taskForm.id);
        if (index < 0 || list.length < 2) return;
        this.openTask(list[(index + delta + list.length) % list.length]);
    },
    toggleTaskForm() {
        if (!this.taskForm.id) { this.taskForm.completed = !this.taskForm.completed; return; }
        this.toggleTask(this.taskForm).then(() => { this.taskForm.completed = !!this.taskForm.completed_at; });
    },
    progress(p) {
        if (!p) return 0;
        const list = this.tasks.filter((t) => t.project_id === p.id);
        return list.length
            ? Math.round(
                  (list.filter((t) => t.completed_at).length / list.length) *
                      100,
              )
            : 0;
    },
    stageTasks(id, mine = false) {
        return (mine ? this.myTasks : this.tasks).filter(
            (t) =>
                (mine ? t.member_stage_id === id : t.product_stage_id === id) &&
                (this.taskFilter === "all" ||
                    (this.taskFilter === "done" && t.completed_at) ||
                    (this.taskFilter === "active" && !t.completed_at)),
        );
    },
    stageProgress(id) {
        const ts = this.tasks.filter((t) => t.product_stage_id === id);
        return ts.length
            ? Math.round(
                  (ts.filter((t) => t.completed_at).length / ts.length) * 100,
              )
            : 0;
    },
    stageTotal(id) {
        return this.tasks.filter((t) => t.product_stage_id === id).length;
    },
    stageDone(id) {
        return this.tasks.filter((t) => t.product_stage_id === id && t.completed_at)
            .length;
    },
    subProgress(t) {
        return `${fa(t.subtasks.filter((s) => s.completed_at || s.completed).length)}/${fa(t.subtasks.length)}`;
    },
    overdue(t) {
        return !t.completed_at && t.due_date < today();
    },
    canWrite(project) {
        return !!project;
    },
    isOwner(project) {
        return !!project;
    },
    notify(message, error = false) {
        this.toast = message;
        this.toastError = error;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => (this.toast = ""), 4500);
    },
    fail(e) {
        this.errors = e.errors || {};
        this.notify(e.message || "ذخیره انجام نشد. دوباره تلاش کنید.", true);
    },
    err(k) {
        return (this.errors[k]?.[0] || "").replace(/[0-9]/g,d=>"۰۱۲۳۴۵۶۷۸۹"[d]);
    },
    showModal(name) {
        this.errors = {};
        this.modal = name;
        this.$nextTick(() => {
            this.modalSnapshot = this.snapshot();
            this.$refs.modalPanel
                ?.querySelector("input,textarea,button")
                ?.focus();
        });
    },
    snapshot() {
        return JSON.stringify({
            task: this.taskForm,
            project: this.projectForm,
            stage: this.stageForm,
            label: this.labelForm,
            goal: this.goalForm,
            member: this.memberForm,
            files: this.pendingFiles.map((f) => f.name),
            comment: this.commentText,
        });
    },
    isDirty() {
        return this.modal && this.snapshot() !== this.modalSnapshot;
    },
    closeModal() {
        if (this.busy) return;
        if (this.isDirty())
            this.ask(
                "تغییرات ذخیره نشده‌اند",
                "از بستن فرم و کنار گذاشتن تغییرات مطمئن هستید؟",
                () => (this.modal = null),
                "کنار گذاشتن",
            );
        else this.modal = null;
    },
    ask(title, body, action, label = "تأیید", transferOptions = []) {
        this.confirmBox = { title, body, action, label, transferOptions, transferId: transferOptions[0]?.id || "" };
    },
    confirm() {
        const box = this.confirmBox;
        this.confirmBox = null;
        box.action(box.transferId || null);
    },
    trap(e) {
        const focusable = [
            ...this.$refs.modalPanel.querySelectorAll(
                'button,input,textarea,select,a[href],[tabindex="0"]',
            ),
        ].filter((el) => !el.disabled && el.getClientRects().length);
        if (!focusable.length) return;
        const first = focusable[0],
            last = focusable.at(-1);
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    },
    async openTask(task = null, stage = null, project = null) {
        if (task && !task.id) return;
        this.busy = false;
        this.pendingFiles = [];
        this.commentText = "";
        this.labelQuery = "";
        this.inlineStage = false;
        let p =
            project ||
            this.currentProject ||
            this.projects.find((p) => this.canWrite(p));
        if (task) {
            try {
                task = await this.api("/tasks/" + task.id);
                p = this.projects.find((p) => p.id === task.project_id);
            } catch (e) {
                return this.fail(e);
            }
        }
        if (!p) {
            this.notify("ابتدا یک پروژه و مراحل آن را بسازید.");
            return this.openProject();
        }
        this.taskForm = task
            ? {
                  ...clone(task),
                  labels: task.labels.map((l) => l.id),
                  members: task.members.map((m) => m.id),
                  completed: !!task.completed_at,
                  subtasks: task.subtasks.map((s) => ({
                      ...s,
                      completed: !!s.completed_at,
                  })),
              }
            : {
                  project_id: p.id,
                  title: "",
                  description: "",
                  product_stage_id: stage?.product_stage_id || null,
                  member_stage_id: stage?.member_id === this.user.id ? stage.id : (this.myStages(p).find((s) => !stage || s.product_stage_id === stage.id)?.id || ""),
                  owner_id: this.user.id,
                  members: [],
                  labels: [],
                  start_date: today(),
                  due_date: today(),
                  subtasks: [],
                  completed: false,
                  comments: [],
                  attachments: [],
                  activities: [],
              };
        this.showModal("task");
    },
    get formProject() {
        return this.projects.find((p) => p.id == this.taskForm.project_id);
    },
    get formMyStages() {
        return (this.formProject?.member_stages || []).filter((stage) => stage.member_id == this.taskForm.owner_id);
    },
    get formLabels() {
        return this.labels.filter(
            (l) => (l.project_id == this.taskForm.project_id || (!l.project_id && l.member_id === this.user.id)) && l.name.includes(this.labelQuery),
        );
    },
    projectChanged() {
        this.taskForm.owner_id = this.user.id;
        this.taskForm.member_stage_id = "";
        this.taskForm.product_stage_id = null;
        this.taskForm.members = [];
        this.taskForm.labels = [];
        this.autosaveTaskNow();
    },
    ownerChanged() {
        this.taskForm.member_stage_id = "";
        this.taskForm.product_stage_id = null;
        this.autosaveTaskNow();
    },
    memberStageChanged() {
        const stage = this.formMyStages.find((item) => item.id == this.taskForm.member_stage_id);
        this.taskForm.product_stage_id = stage?.product_stage_id || null;
        this.autosaveTaskNow();
    },
    get selectedProductStage() {
        return this.formProject?.stages?.find((stage) => stage.id == this.taskForm.product_stage_id) || null;
    },
    async saveTask() {
        this.busy = true;
        this.errors = {};
        try {
            const id = this.taskForm.id;
            const result = await this.api(
                "/tasks" + (id ? "/" + id : ""),
                id ? "PUT" : "POST",
                this.taskForm,
            );
            this.taskForm.id = result.id;
            for (const file of [...this.pendingFiles]) {
                const data = new FormData();
                data.append("file", file);
                const a = await this.api(
                    `/tasks/${result.id}/attachments`,
                    "POST",
                    data,
                );
                this.taskForm.attachments.push(a);
                this.pendingFiles.splice(this.pendingFiles.indexOf(file), 1);
            }
            if (this.commentText.trim()) {
                await this.api(`/tasks/${result.id}/comments`, "POST", {
                    body: this.commentText,
                });
                this.commentText = "";
            }
            this.modal = null;
            await this.refresh();
            this.notify(id ? "تغییرات ذخیره شد." : "تسک جدید ساخته شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async toggleTask(t) {
        try {
            const updated = await this.api(`/tasks/${t.id}/complete`, "PATCH", {
                completed: !t.completed_at,
            });
            t.completed_at = updated.completed_at;
            this.notify(
                t.completed_at
                    ? "یک قدم نزدیک‌تر به هدف!"
                    : "تسک دوباره باز شد.",
            );
        } catch (e) {
            this.fail(e);
        }
    },
    removeTask() {
        this.ask(
            "حذف تسک",
            "تسک، نظرها و پیوست‌های آن حذف می‌شوند.",
            async () => {
                try {
                    await this.api("/tasks/" + this.taskForm.id, "DELETE");
                    this.modal = null;
                    await this.refresh();
                    this.notify("تسک حذف شد.");
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف تسک",
        );
    },
    addSubtask() {
        this.taskForm.subtasks.push({ title: "", completed: false });
    },
    reorderSubtask(i) {
        if (this.dragIndex === null) return;
        const [s] = this.taskForm.subtasks.splice(this.dragIndex, 1);
        this.taskForm.subtasks.splice(i, 0, s);
        this.dragIndex = null;
    },
    startInlineStage() {
        this.inlineStageForm = {
            title: "",
            description: "",
            start_date: this.taskForm.start_date,
            end_date: this.taskForm.due_date,
            member_id: this.taskForm.owner_id,
            product_stage_id: this.formProject?.stages?.[0]?.id || "",
        };
        this.inlineStage = true;
    },
    async createInlineStage() {
        this.busy = true;
        try {
            const stage = await this.api(
                `/projects/${this.taskForm.project_id}/stages`,
                "POST",
                this.inlineStageForm,
            );
            await this.refresh();
            this.taskForm.member_stage_id = stage.id;
            this.taskForm.product_stage_id = stage.product_stage_id;
            this.inlineStage = false;
            this.notify("مرحله من ساخته و انتخاب شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async quickLabel() {
        if (!this.labelQuery.trim()) return;
        try {
            const l = await this.api("/labels", "POST", {
                name: this.labelQuery,
                color: "#9471bd",
                project_id: this.taskForm.project_id,
            });
            this.labels.push(l);
            this.taskForm.labels.push(l.id);
            this.labelQuery = "";
            this.autosaveTaskNow();
        } catch (e) {
            this.fail(e);
        }
    },
    async filesAdded(files) {
        for (const f of files) {
            if (f.size > 5 * 1024 * 1024) {
                this.notify("حداکثر حجم هر فایل ۵ مگابایت است.", true);
                continue;
            }
            if (!this.taskForm.id) {
                this.pendingFiles.push(f);
                continue;
            }
            try {
                const data = new FormData();
                data.append("file", f);
                const attachment = await this.api(`/tasks/${this.taskForm.id}/attachments`, "POST", data);
                this.taskForm.attachments.push(attachment);
                this.notify("ذخیره شد");
            } catch (e) { this.fail(e); }
        }
    },
    size(n) {
        return n < 1024 * 1024
            ? fa(Math.ceil(n / 1024)) + " کیلوبایت"
            : fa((n / 1024 / 1024).toFixed(1)) + " مگابایت";
    },
    async addComment() {
        if (!this.commentText.trim()) return;
        this.busy = true;
        try {
            const c = await this.api(
                `/tasks/${this.taskForm.id}/comments`,
                "POST",
                { body: this.commentText },
            );
            this.taskForm.comments.push(c);
            this.commentText = "";
            this.notify("نظر ثبت شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async editComment(c) {
        try {
            await this.api("/comments/" + c.id, "PUT", { body: c.editBody });
            c.body = c.editBody;
            c.editing = false;
            this.notify("نظر ویرایش شد.");
        } catch (e) {
            this.fail(e);
        }
    },
    deleteComment(c) {
        this.ask(
            "حذف نظر",
            "این نظر حذف شود؟",
            async () => {
                try {
                    await this.api("/comments/" + c.id, "DELETE");
                    this.taskForm.comments = this.taskForm.comments.filter(
                        (x) => x.id !== c.id,
                    );
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف",
        );
    },
    deleteAttachment(a) {
        this.ask(
            "حذف پیوست",
            "این فایل حذف شود؟",
            async () => {
                try {
                    await this.api("/attachments/" + a.id, "DELETE");
                    this.taskForm.attachments =
                        this.taskForm.attachments.filter((x) => x.id !== a.id);
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف",
        );
    },
    openProject() {
        this.projectStep = 1;
        this.projectForm = {
            color: "#d29268",
            name: "",
            description: "",
            goal: {
                title: "",
                description: "",
                success_criterion: "",
                start_date: today(),
                target_date: today(),
            },
            stages: [],
        };
        this.showModal("project");
    },
    nextStep() {
        this.errors = {};
        if (this.projectStep === 1 && !this.projectForm.name.trim()) {
            this.errors = { name: ["نام پروژه را وارد کنید."] };
            return;
        }
        if (this.projectStep === 2) {
            for (const key of [
                "title",
                "success_criterion",
                "start_date",
                "target_date",
            ])
                if (!this.projectForm.goal[key])
                    this.errors["goal." + key] = ["این فیلد الزامی است."];
            if (
                this.projectForm.goal.target_date <
                this.projectForm.goal.start_date
            )
                this.errors["goal.target_date"] = [
                    "تاریخ پایان باید بعد از شروع باشد.",
                ];
            if (Object.keys(this.errors).length) return;
        }
        this.projectStep++;
    },
    async saveProject() {
        this.busy = true;
        try {
            const p = await this.api("/projects", "POST", this.projectForm);
            await this.refresh();
            this.modal = null;
            this.go("projects", p.id);
            this.notify("پروژه شما آماده است.");
        } catch (e) {
            this.fail(e);
            if (Object.keys(this.errors).some((k) => k.startsWith("goal")))
                this.projectStep = 2;
        } finally {
            this.busy = false;
        }
    },
    moveWizard(i, delta) {
        const j = i + delta;
        if (j < 0 || j >= this.projectForm.stages.length) return;
        const s = this.projectForm.stages.splice(i, 1)[0];
        this.projectForm.stages.splice(j, 0, s);
    },
    dropWizard(i) {
        if (this.dragIndex === null) return;
        const s = this.projectForm.stages.splice(this.dragIndex, 1)[0];
        this.projectForm.stages.splice(i, 0, s);
        this.dragIndex = null;
    },
    openStage(stage = null, user = null, project = null) {
        const p = project || this.currentProject || this.projects.find((item) => item.id === stage?.project_id);
        this.stageProjectId = p?.id || null;
        this.stageId = stage?.id || null;
        this.stageUser = user;
        this.stageKind = user ? "member" : "product";
        this.stageForm = stage
            ? clone(stage)
            : {
                  title: "",
                  description: "",
                  start_date: p?.goal?.start_date || today(),
                  end_date: p?.goal?.target_date || today(),
                  product_stage_id: user ? p?.stages?.[0]?.id || "" : null,
              };
        if (user) this.stageForm.member_id = user.id;
        this.showModal("stage");
    },
    get stageProject() {
        return this.projects.find((item) => item.id === this.stageProjectId) || null;
    },
    async saveStage() {
        this.busy = true;
        try {
            await this.api(
                `/projects/${this.stageProjectId}/stages` +
                    (this.stageId ? `/${this.stageKind}/${this.stageId}` : ""),
                this.stageId ? "PUT" : "POST",
                this.stageForm,
            );
            await this.refresh();
            this.modal = null;
            this.notify("مرحله ذخیره شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    deleteStage(stage, kind = "product", user = null) {
        const project = this.currentProject || this.projects.find((item) => item.id === stage.project_id);
        const options = (kind === "member"
            ? project.member_stages.filter((item) => item.member_id === (user?.id || stage.member_id) && item.id !== stage.id)
            : project.stages.filter((item) => item.id !== stage.id)
        ).map((item) => ({ id: item.id, title: item.title }));
        const hasTasks = kind === "member"
            ? this.tasks.some((task) => task.member_stage_id === stage.id)
            : this.tasks.some((task) => task.product_stage_id === stage.id);
        if (hasTasks && !options.length) {
            return this.notify("برای حذف این ستون ابتدا یک ستون مقصد بسازید.", true);
        }
        this.ask(
            "حذف ستون",
            hasTasks ? "این ستون تسک دارد. ستون مقصد انتقال تسک‌ها را انتخاب کنید." : "این ستون حذف شود؟",
            async (transferId) => {
                try {
                    const url = `/projects/${project.id}/stages/${kind}/${stage.id}`;
                    const headers = { Accept: "application/json", "Content-Type": "application/json", "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content };
                    const res = await fetch("/api" + url, { method: "DELETE", headers, body: hasTasks ? JSON.stringify({ transfer_stage_id: Number(transferId) }) : null });
                    const data = res.status === 204 ? {} : await res.json();
                    if (!res.ok) throw data;
                    await this.refresh();
                    this.modal = null;
                    this.notify("ستون حذف شد.");
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف ستون",
            hasTasks ? options : [],
        );
    },
    async reorderStage(target, kind = "product", user = null) {
        const source = kind === "product" ? this.dragStage : this.teamDrag;
        if (!source || source === target.id) return;
        const stages =
            kind === "product"
                ? [...this.currentProject.stages]
                : this.currentProject.member_stages.filter(
                      (s) => s.member_id === user.id,
                  );
        const a = stages.findIndex((s) => s.id === source),
            b = stages.findIndex((s) => s.id === target.id);
        if (a < 0 || b < 0) return;
        const item = stages.splice(a, 1)[0];
        stages.splice(b, 0, item);
        try {
            await this.api(
                `/projects/${this.projectId}/stages/${kind}/order`,
                "PUT",
                { ids: stages.map((s) => s.id), member_id: user?.id },
            );
            await this.refresh();
            this.notify("ترتیب مراحل ذخیره شد.");
        } catch (e) {
            this.fail(e);
        }
        this.dragStage = null;
        this.teamDrag = null;
    },
    async moveStage(s, delta, kind = "product", user = null) {
        const stages =
            kind === "product"
                ? this.currentProject.stages
                : this.currentProject.member_stages.filter(
                      (s) => s.member_id === user.id,
                  );
        const target = stages[stages.findIndex((x) => x.id === s.id) + delta];
        if (!target) return;
        if (kind === "product") this.dragStage = s.id;
        else this.teamDrag = s.id;
        await this.reorderStage(target, kind, user);
    },
    openGoal() {
        this.goalForm = clone(this.currentProject.goal);
        this.showModal("goal");
    },
    async saveGoal() {
        this.busy = true;
        try {
            await this.api(
                `/projects/${this.projectId}/goal`,
                "PUT",
                this.goalForm,
            );
            await this.refresh();
            this.modal = null;
            this.notify("هدف به‌روزرسانی شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    openLabel(l = null) {
        this.labelForm = l
            ? clone(l)
            : { name: "", color: "#ed8555", description: "", project_id: null };
        this.showModal("label");
    },
    async saveLabel() {
        this.busy = true;
        try {
            await this.api(
                "/labels" + (this.labelForm.id ? "/" + this.labelForm.id : ""),
                this.labelForm.id ? "PUT" : "POST",
                this.labelForm,
            );
            await this.refresh();
            this.modal = null;
            this.notify("برچسب ذخیره شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    deleteLabel(l) {
        this.ask(
            "حذف برچسب",
            "برچسب از تسک‌ها هم برداشته خواهد شد.",
            async () => {
                try {
                    await this.api("/labels/" + l.id, "DELETE");
                    await this.refresh();
                    this.notify("برچسب حذف شد.");
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف",
        );
    },
    async saveMember() {
        this.busy = true;
        try {
            await this.api(
                `/projects/${this.projectId}/members`,
                "POST",
                this.memberForm,
            );
            await this.refresh();
            this.modal = null;
            this.notify("عضو به پروژه اضافه شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async uploadAvatar(file) {
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
            return this.notify("عکس باید JPG، PNG یا WebP و حداکثر ۲ مگابایت باشد.", true);
        }
        this.busy = true;
        try {
            const data = new FormData();
            data.append("avatar", file);
            this.user = await this.api("/settings/avatar", "POST", data);
            const index = this.members.findIndex((m) => m.id === this.user.id);
            if (index >= 0) this.members[index] = this.user;
            this.notify("عکس پروفایل ذخیره شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async removeAvatar() {
        this.busy = true;
        try {
            this.user = await this.api("/settings/avatar", "DELETE");
            const index = this.members.findIndex((m) => m.id === this.user.id);
            if (index >= 0) this.members[index] = this.user;
            this.notify("عکس پروفایل حذف شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },    async saveSettings() {
        this.busy = true;
        try {
            await this.api("/settings", "PUT", this.account);
            await this.refresh();
            this.notify("اطلاعات حساب ذخیره شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async readNotices() {
        try {
            await this.api("/notices/read", "POST");
            this.notices.forEach((n) => (n.read_at = true));
        } catch (e) {
            this.fail(e);
        }
    },
}));
window.Alpine = Alpine;
Alpine.start();
