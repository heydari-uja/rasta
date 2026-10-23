import Alpine from "alpinejs";
import Sortable from "sortablejs";
import {
    createElement,
    House,
    CheckCheck,
    FolderClosed,
    Tags,
    Settings,
    Plus,
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
    Moon,
    Monitor,
    ArrowUpRight,
    CheckCircle2,
    Upload,
    UserRound,
    ShieldCheck,
    SlidersHorizontal,
    FolderOpen,
    PanelRight,
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
    Moon,
    Monitor,
    ArrowUpRight,
    CheckCircle2,
    Upload,
    UserRound,
    ShieldCheck,
    SlidersHorizontal,
    FolderOpen,
    PanelRight,
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
const shortDate = (d) =>
    d
        ? cleanPersianDate(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
              day: "numeric",
              month: "long",
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
            const fullLabel = cleanPersianDate(new Intl.DateTimeFormat("fa-IR-u-ca-persian", {
                weekday: "long",
                day: "numeric",
                month: "long",
                year: "numeric",
            }).format(d));
            return {
                iso,
                label: fa(this.parts(d).day),
                muted: this.parts(d).month !== this.parts(start).month,
                today: iso === today(),
                fullLabel,
            };
        });
    },
    position() {
        const rect = this.$refs.trigger?.getBoundingClientRect();
        if (!rect) return;
        const width = Math.min(288, window.innerWidth - 24);
        const left = Math.max(12, Math.min(window.innerWidth - width - 12, rect.right - width));
        const height = Math.min(this.$refs.calendar?.offsetHeight || 350, window.innerHeight - 24);
        const below = rect.bottom + 6;
        const preferredTop = below + height <= window.innerHeight - 12 ? below : rect.top - height - 6;
        const top = Math.max(12, Math.min(preferredTop, window.innerHeight - height - 12));
        this.calendarStyle = `position:fixed;top:${top}px;left:${left}px;width:${width}px;max-height:${window.innerHeight - 24}px;overflow-y:auto`;
    },
    move(n) {
        const d = new Date(this.cursor);
        d.setDate(d.getDate() + (n > 0 ? 32 : -1));
        this.cursor = d;
        this.render();
    },
    selectToday() {
        this.value = today();
        this.open = false;
    },
    label() {
        return date(this.value);
    },
}));
Alpine.data("workspace", () => ({
    user: {},
    projects: [],
    personalStages: [],
    tasks: [],
    labels: [],
    page: "tasks",
    projectId: null,
    loading: true,
    loadError: "",
    sidebarCollapsed: (() => {
        const saved = localStorage.getItem("rasta-sidebar-collapsed");
        return saved === null
            ? window.matchMedia("(max-width: 639px)").matches
            : saved === "true";
    })(),
    projectsExpanded: true,
    profileMenuOpen: false,
    sidebarTooltipVisible: false,
    sidebarTooltipTimer: null,
    sidebarTooltipTarget: null,
    sidebarTooltipStyle: "",
    view: "board",
    selectedProject: "all",
    toast: "",
    toastError: false,
    toastActionLabel: "",
    toastActionTaskId: null,
    busy: false,
    errors: {},
    modal: null,
    closingModal: null,
    modalCloseTimer: null,
    confirmBox: null,
    modalSnapshot: "",
    taskForm: {},
    pendingFiles: [],
    commentText: "",
    commentFiles: [],
    taskDetailsEditing: false,
    taskEditSnapshot: null,
    labelFilterId: null,
    taskDateOpen: false,
    taskDateMode: "single",
    taskDateCursor: new Date(),
    taskDateDays: [],
    taskDatePickingEnd: false,
    taskDateStyle: "",
    labelQuery: "",
    newLabelName: "",
    inlineStage: false,
    inlineStageForm: {},
    projectStep: 1,
    projectForm: {},
    projectEditId: null,
    projectEditForm: {},
    stageForm: {},
    stageKind: "product",
    stageId: null,
    stageUser: null,
    stageProjectId: null,
    labelForm: {},
    goalForm: {},
    dragIndex: null,
    dragStage: null,
    teamDrag: null,
    myStageDrag: null,
    stageSortable: null,
    taskSortables: [],
    taskDragBlockedUntil: 0,
    taskDrag: null,
    inlineColumn: null,
    inlineColumnForm: { title: "", description: "", product_stage_id: "", project_id: "" },
    columnRename: null,
    columnRenameTitle: "",
    taskAutosaveTimer: null,
    taskSaving: false,
    taskSaveStatus: "",
    expandedSubtasks: JSON.parse(localStorage.getItem("rasta-subtasks-open") || "{}"),
    account: {},
    security: {},
    securityBusy: "",
    securityDialog: "",
    securityDialogTrigger: null,
    settingsSection: "account",
    avatarUploading: false,
    async init() {
        this.applyTheme();
        document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
        await this.refresh();
        this.route();
        window.addEventListener("popstate", () => this.route());
        window.addEventListener("keydown", (e) => {
            if (e.key === "Escape") {
                this.hideSidebarTooltip();
                this.profileMenuOpen = false;
                if (this.confirmBox) this.confirmBox = null;
                else if (this.modal) this.closeModal();
            }
            const target = e.target;
            const editing = target instanceof Element && (
                target.closest("input, textarea, select, [contenteditable]:not([contenteditable='false'])") || target.isContentEditable
            );
            if ((e.ctrlKey || e.metaKey) && e.shiftKey && !e.altKey && !e.repeat && !e.isComposing && e.key.toLowerCase() === "s" && !editing) {
                e.preventDefault();
                this.toggleSidebarCollapsed();
            }

        });
        window.addEventListener("resize", () => this.positionSidebarTooltip());
        window.addEventListener("scroll", () => this.positionSidebarTooltip(), true);
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
        let result = {};
        if (res.status !== 204) {
            try {
                result = await res.json();
            } catch {
                throw { message: "پاسخ سرور معتبر نیست. لطفاً دوباره تلاش کنید." };
            }
        }
        if (!res.ok) throw result;
        return result;
    },
    async refresh() {
        try {
            const d = await this.api("/workspace");
            Object.assign(this, d);
            this.account = { name: d.user.name, job_title: d.user.job_title };
            this.loadError = "";
            this.$nextTick(() => this.setupTaskSortables());
        } catch (e) {
            this.loadError = e.message || "دریافت اطلاعات ممکن نشد.";
        } finally {
            this.loading = false;
        }
    },
    route() {
        const path = location.pathname.split("/").filter(Boolean);
        this.page = path[0] || "tasks";
        this.projectId =
            this.page === "projects" && path[1] ? Number(path[1]) : null;
        if (this.page === "tasks" && path[1])
            this.openTask(this.tasks.find((t) => t.id === Number(path[1])));
        this.$nextTick(() => this.setupTaskSortables());
    },
    go(page, id = null) {
        this.page = page;
        this.projectId = id;
        history.pushState({}, "", `/${page}${id ? "/" + id : ""}`);
        window.scrollTo(0, 0);
        this.$nextTick(() => this.setupTaskSortables());
    },
    toggleSidebarCollapsed() {
        this.sidebarCollapsed = !this.sidebarCollapsed;
        localStorage.setItem("rasta-sidebar-collapsed", String(this.sidebarCollapsed));
        this.hideSidebarTooltip();
    },
    showSidebarTooltip(button) {
        window.clearTimeout(this.sidebarTooltipTimer);
        this.sidebarTooltipTarget = button;
        this.sidebarTooltipTimer = window.setTimeout(() => {
            if (!this.sidebarTooltipTarget?.isConnected) return;
            this.positionSidebarTooltip();
            this.sidebarTooltipVisible = true;
        }, 300);
    },
    positionSidebarTooltip() {
        const button = this.sidebarTooltipTarget;
        if (!button?.isConnected) return;
        const rect = button.getBoundingClientRect();
        const sidebarRect = button.closest(".sidebar")?.getBoundingClientRect() || rect;
        const rtl = window.getComputedStyle(button).direction === "rtl";
        const inlineStart = rtl
            ? window.innerWidth - sidebarRect.left + 8
            : sidebarRect.right + 8;
        this.sidebarTooltipStyle = `top:${rect.top + rect.height / 2}px;inset-inline-start:${inlineStart}px`;
    },
    hideSidebarTooltip() {
        window.clearTimeout(this.sidebarTooltipTimer);
        this.sidebarTooltipTimer = null;
        this.sidebarTooltipVisible = false;
        this.sidebarTooltipTarget = null;
    },
    fa,
    date,
    shortDate,
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
        const root = document.documentElement;
        root.classList.add("dark");
        root.dataset.theme = "dark";
        root.dataset.themePreference = "dark";
        const themeColor = document.querySelector('meta[name="theme-color"]');
        if (themeColor) {
            const pageColor = getComputedStyle(root).getPropertyValue("--surface-page").trim();
            if (pageColor) themeColor.content = pageColor;
        }
    },
    get currentProject() {
        return this.projects.find((p) => p.id === this.projectId);
    },
    get myTasks() {
        return this.tasks.filter((t) => t.owner_id === this.user.id);
    },
    get todayTasks() {
        return this.myTasks.filter(
            (t) => !t.completed_at && t.due_date === today(),
        );
    },
    get deadlines() {
        const end = new Date(today() + "T12:00:00");
        end.setDate(end.getDate() + 7);
        const limit = end.toLocaleDateString("en-CA");
        return this.myTasks.filter((task) => !task.completed_at && task.due_date && task.due_date >= today() && task.due_date <= limit);
    },
    get upcoming() {
        return [...this.myTasks]
            .filter((t) => !t.completed_at && t.due_date)
            .sort((a, b) => a.due_date.localeCompare(b.due_date))
            .slice(0, 5);
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
        const memberId = Number(id) || 0;
        const marker = Number(memberId) === Number(this.user?.id) ? 4 : (Math.abs(memberId) % 8) + 1;
        return `var(--project-marker-${marker})`;
    },
    markerToken(value) {
        const marker = /^project-marker-([1-8])$/.exec(String(value ?? ""));
        return marker ? marker[0] : "project-marker-1";
    },
    labelColorPalette() {
        return Array.from({ length: 8 }, (_, index) => `project-marker-${index + 1}`);
    },
    projectColorPalette() {
        return Array.from({ length: 8 }, (_, index) => `project-marker-${index + 1}`);
    },
    stageColor(i) {
        return `var(--project-marker-${(i % 8) + 1})`;
    },
    myStages(project) {
        return (project?.member_stages || []).filter((s) => s.member_id === this.user.id);
    },
    get myStageColumns() {
        return [
            ...(this.personalStages || []),
            ...this.projects.flatMap((project) => (project.member_stages || []).filter((stage) => stage.member_id === this.user.id)),
        ].sort((a, b) => (Number(a.position ?? 0) - Number(b.position ?? 0)) || (Number(a.id) - Number(b.id)));
    },
    projectForStage(stage) {
        return this.projects.find((project) => Number(project.id) === Number(stage?.project_id)) || null;
    },
    beginMyStageDrag(stage, event) {
        this.myStageDrag = stage.id;
        if (event?.dataTransfer) {
            event.dataTransfer.effectAllowed = "move";
            event.dataTransfer.setData("text/plain", String(stage.id));
        }
    },
    async dropMyStageColumn(target) {
        const sourceId = Number(this.myStageDrag);
        if (!sourceId || sourceId === Number(target?.id)) return;
        const stages = [...this.myStageColumns];
        const from = stages.findIndex((stage) => Number(stage.id) === sourceId);
        const to = stages.findIndex((stage) => Number(stage.id) === Number(target.id));
        if (from < 0 || to < 0) return;
        const previous = new Map(stages.map((stage) => [stage.id, stage.position]));
        const [moved] = stages.splice(from, 1);
        stages.splice(to, 0, moved);
        stages.forEach((stage, index) => { stage.position = index; });
        this.myStageDrag = null;
        try {
            await this.api("/member-stages/order", "PUT", { ids: stages.map((stage) => stage.id) });
            this.notify("ترتیب ستون‌ها ذخیره شد.");
        } catch (e) {
            stages.forEach((stage) => { stage.position = previous.get(stage.id); });
            this.fail(e);
        }
    },
    destroyTaskSortables() {
        this.stageSortable?.destroy();
        this.stageSortable = null;
        for (const sortable of this.taskSortables || []) sortable?.destroy();
        this.taskSortables = [];
    },
    setupTaskSortables() {
        this.destroyTaskSortables();
        if (this.page !== "tasks") return;
        const board = document.querySelector('[data-my-stages-board]');
        if (!board) return;

        const sanitizeFallback = () => {
            for (const root of document.querySelectorAll('.sortable-drag-fallback')) {
                for (const el of [root, ...root.querySelectorAll('*')]) {
                    for (const attr of [...el.attributes]) {
                        if (attr.name.startsWith('x-') || attr.name.startsWith('@') || attr.name.startsWith(':')) {
                            el.removeAttribute(attr.name);
                        }
                    }
                }
            }
        };
        const common = {
            animation: 260,
            easing: 'cubic-bezier(.22,1,.36,1)',
            forceFallback: true,
            fallbackOnBody: true,
            fallbackTolerance: 5,
            fallbackClass: 'sortable-drag-fallback',
            delay: 140,
            delayOnTouchOnly: true,
            touchStartThreshold: 5,
            scroll: true,
            scrollSensitivity: 70,
            scrollSpeed: 12,
            onClone: (evt) => {
                const root = evt.clone;
                if (!root) return;
                root.setAttribute('data-sortable-clone', 'true');
                for (const el of [root, ...root.querySelectorAll('*')]) {
                    for (const attr of [...el.attributes]) {
                        if (attr.name.startsWith('x-') || attr.name.startsWith('@') || attr.name.startsWith(':')) {
                            el.removeAttribute(attr.name);
                        }
                    }
                }
            },
        };

        this.stageSortable = Sortable.create(board, {
            ...common,
            draggable: '.task-stage-column',
            handle: '.task-stage-drag-handle',
            direction: () => window.matchMedia('(max-width: 760px)').matches ? 'vertical' : 'horizontal',
            swapThreshold: .62,
            ghostClass: 'sortable-column-ghost',
            chosenClass: 'sortable-column-chosen',
            dragClass: 'sortable-column-drag',
            onChoose: (evt) => evt.item?.classList.add('is-column-lifting'),
            onUnchoose: (evt) => evt.item?.classList.remove('is-column-lifting'),
            onStart: () => { sanitizeFallback(); this.taskDragBlockedUntil = Date.now() + 450; },
            onMove: () => { sanitizeFallback(); return true; },
            onEnd: async (evt) => {
                this.taskDragBlockedUntil = Date.now() + 450;
                evt.item?.classList.remove('is-column-lifting');
                const addCard = board.querySelector('.task-global-add-column');
                if (addCard) board.appendChild(addCard);
                const ids = [...board.querySelectorAll(':scope > .task-stage-column')]
                    .map((el) => Number(el.dataset.stageId))
                    .filter(Boolean);
                if (!ids.length || evt.oldDraggableIndex === evt.newDraggableIndex) return;
                try {
                    await this.api('/member-stages/order', 'PUT', { ids });
                    await this.refresh();
                } catch (e) {
                    await this.refresh();
                    this.fail(e);
                }
            },
        });

        for (const list of board.querySelectorAll('.column-tasks[data-stage-id]')) {
            const sortable = Sortable.create(list, {
                ...common,
                group: { name: 'my-task-columns', pull: true, put: true },
                draggable: '.task-card',
                direction: (evt, target, dragEl) => dragEl?.parentElement === list ? 'vertical' : 'horizontal',
                swapThreshold: .68,
                invertedSwapThreshold: .92,
                emptyInsertThreshold: 34,
                ghostClass: 'sortable-task-ghost',
                chosenClass: 'sortable-task-chosen',
                dragClass: 'sortable-task-drag',
                filter: '.completion,.subtask-toggle,.subtask-completion,.remove-subtask,input,textarea,a,[contenteditable="true"]',
                preventOnFilter: false,
                onChoose: (evt) => evt.item?.classList.add('is-task-lifting'),
                onUnchoose: (evt) => evt.item?.classList.remove('is-task-lifting'),
                onStart: () => { sanitizeFallback(); this.taskDragBlockedUntil = Date.now() + 450; },
                onMove: () => { sanitizeFallback(); return true; },
                onEnd: (evt) => {
                    evt.item?.classList.remove('is-task-lifting');
                    this.persistTaskSort(evt);
                },
            });
            this.taskSortables.push(sortable);
        }
    },
    async persistTaskSort(evt) {
        this.taskDragBlockedUntil = Date.now() + 450;
        const taskId = Number(evt.item?.dataset.taskId);
        const targetStageId = Number(evt.to?.dataset.stageId);
        const sourceStageId = Number(evt.from?.dataset.stageId);
        if (!taskId || !targetStageId || !sourceStageId) {
            await this.refresh();
            return;
        }
        if (sourceStageId === targetStageId && evt.oldDraggableIndex === evt.newDraggableIndex) return;

        const idsIn = (el) => [...el.querySelectorAll(':scope > .task-card')]
            .map((node) => Number(node.dataset.taskId))
            .filter(Boolean);
        const sourceVisible = evt.from === evt.to ? idsIn(evt.to) : idsIn(evt.from);
        const targetVisible = idsIn(evt.to);
        const orderedStageIds = (stageId) => this.myTasks
            .filter((item) => Number(item.member_stage_id) === Number(stageId))
            .sort((a, b) => (Number(a.position ?? 0) - Number(b.position ?? 0)) || (Number(a.id) - Number(b.id)))
            .map((item) => Number(item.id));
        const sourceBefore = orderedStageIds(sourceStageId);
        const targetBefore = sourceStageId === targetStageId ? [...sourceBefore] : orderedStageIds(targetStageId);
        const mergeVisible = (baseIds, visibleIds) => {
            const visibleSet = new Set(visibleIds.map(Number));
            let cursor = 0;
            return baseIds.map((id) => visibleSet.has(Number(id)) ? visibleIds[cursor++] : Number(id));
        };

        let sourceIds;
        let targetIds;
        if (sourceStageId === targetStageId) {
            targetIds = mergeVisible([...targetBefore], targetVisible);
            sourceIds = [...targetIds];
        } else {
            sourceIds = mergeVisible(sourceBefore.filter((id) => id !== taskId), sourceVisible);
            targetIds = mergeVisible([...targetBefore, taskId], targetVisible);
        }

        try {
            await this.api('/tasks/reorder', 'PUT', {
                task_id: taskId,
                target_stage_id: targetStageId,
                target_ids: targetIds,
                source_ids: sourceIds,
            });
            await this.refresh();
        } catch (e) {
            await this.refresh();
            this.fail(e);
        }
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
    startTaskColumn() {
        this.inlineColumn = { kind: "member", source: "tasks" };
        this.inlineColumnForm = {
            title: "",
            description: "",
            product_stage_id: "",
            project_id: "",
        };
        this.$nextTick(() => document.querySelector('[data-inline-column-input]')?.focus());
    },
    async saveTaskColumn() {
        if (!this.inlineColumnForm.title.trim() || this.inlineColumnForm.project_id === "") return;
        const personal = this.inlineColumnForm.project_id === "none";
        const project = personal ? null : this.projects.find((item) => Number(item.id) === Number(this.inlineColumnForm.project_id));
        if (!personal && !project) return;
        const productStage = project?.stages?.[0] || null;
        if (project && !productStage) {
            this.notify("این پروژه مرحله محصول ندارد.", true);
            return;
        }
        try {
            if (personal) {
                await this.api('/member-stages', 'POST', {
                    title: this.inlineColumnForm.title.trim(),
                    description: this.inlineColumnForm.description.trim() || "",
                    start_date: null,
                    end_date: null,
                });
            } else {
                await this.api(`/projects/${project.id}/stages`, "POST", {
                    title: this.inlineColumnForm.title.trim(),
                    description: this.inlineColumnForm.description.trim() || "",
                    start_date: project.goal?.start_date || today(),
                    end_date: project.goal?.target_date || today(),
                    member_id: this.user.id,
                    product_stage_id: productStage.id,
                });
            }
            await this.refresh();
            this.cancelInlineColumn();
            this.notify(personal ? "مرحله شخصی ساخته شد." : "ستون جدید ساخته شد.");
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
        this.inlineColumnForm = { title: "", description: "", product_stage_id: "", project_id: "" };
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
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
            const personal = kind === 'member' && !stage.project_id;
            await this.api(personal ? `/member-stages/${stage.id}` : `/projects/${project.id}/stages/${kind}/${stage.id}`, "PUT", {
                title,
                description: stage.description || "",
                start_date: stage.start_date || null,
                end_date: stage.end_date || null,
                ...(!personal && kind === "member" ? { product_stage_id: stage.product_stage_id } : {}),
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
            completed: !!task.completed_at,
            subtasks: task.subtasks.map((item) => ({ id: item.id, title: item.title, completed: item.id === subtask.id ? !item.completed_at : !!item.completed_at })),
        };
        try {
            await this.api(`/tasks/${task.id}`, "PUT", this.taskPayload(payload));
            subtask.completed_at = subtask.completed_at ? null : new Date().toISOString();
            this.notify("ذخیره شد");
        } catch (e) { this.fail(e); }
    },
    taskPayload(source = this.taskForm) {
        const data = clone(source);
        delete data.id; delete data.owner_id; delete data.assignee; delete data.members; delete data.comments; delete data.attachments; delete data.activities; delete data.created_at; delete data.updated_at; delete data.completed_at;
        data.labels = (data.labels || []).map((l) => typeof l === 'object' ? l.id : l);
        data.subtasks = (data.subtasks || []).filter((st) => st.title?.trim()).map((st) => ({id:st.id || null,title:st.title,completed:!!st.completed}));
        data.completed = !!(source.completed || source.completed_at);
        return data;
    },
    scheduleTaskAutosave() {
        if (!this.taskForm.id || this.taskReadOnly) return;
        this.taskSaveStatus = "pending";
        clearTimeout(this.taskAutosaveTimer);
        this.taskAutosaveTimer = setTimeout(() => this.autosaveTask(), 500);
    },
    async autosaveTask() {
        if (!this.taskForm.id || this.taskReadOnly) return false;
        if (this.taskSaving) {
            await this.taskAutosavePromise;
            return this.autosaveTask();
        }
        clearTimeout(this.taskAutosaveTimer);
        this.taskSaving = true;
        this.taskSaveStatus = "saving";
        this.errors = {};
        const form = this.taskForm;
        const payload = this.taskPayload(form);
        const submittedSubtasks = form.subtasks.filter((st) => st.title?.trim());
        let saved = false;
        let finishSave;
        this.taskAutosavePromise = new Promise((resolve) => { finishSave = resolve; });
        try {
            const updated = await this.api(`/tasks/${form.id}`, "PUT", payload);
            submittedSubtasks.forEach((subtask, index) => {
                if (!subtask.id && updated.subtasks?.[index]) subtask.id = updated.subtasks[index].id;
            });
            const local = this.tasks.find((item) => item.id === form.id);
            if (local) {
                Object.assign(local, updated);
            }
            this.taskEditSnapshot = { title: payload.title, description: payload.description };
            this.taskSaveStatus = "saved";
            saved = true;
        } catch (e) { this.taskSaveStatus = "error"; this.fail(e); } finally { this.taskSaving = false; finishSave(); }
        return saved;
    },
    autosaveTaskNow(includeDetails = false) {
        if (!this.taskForm.id || this.taskReadOnly) return;
        clearTimeout(this.taskAutosaveTimer);
        return this.autosaveTask(includeDetails);
    },
    finishTaskModalClose() {
        clearTimeout(this.modalCloseTimer);
        this.closingModal = "task";
        this.modal = null;
        this.modalCloseTimer = setTimeout(() => {
            this.closingModal = null;
            this.modalCloseTimer = null;
        }, 280);
    },
    async closeTaskComposer() {
        if (this.taskForm.id && !this.taskReadOnly) {
            if (!await this.autosaveTaskNow(true)) return;
        }
        this.taskDetailsEditing = false;
        this.finishTaskModalClose();
    },
    taskNeighbor(delta) {
        if (!this.taskForm.id) return;
        const list = this.tasks.filter((item) => item.project_id === this.taskForm.project_id && !item.completed_at);
        const index = list.findIndex((item) => item.id === this.taskForm.id);
        if (index < 0 || list.length < 2) return;
        this.openTask(list[(index + delta + list.length) % list.length]);
    },
    toggleTaskForm() {
        if (this.taskReadOnly) return;
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
        return (mine ? this.myTasks : this.tasks)
            .filter((t) => !t.completed_at && (mine ? t.member_stage_id === id : t.product_stage_id === id))
            .sort((a, b) => (Number(a.position ?? 0) - Number(b.position ?? 0)) || (Number(a.id) - Number(b.id)));
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
        return this.tasks.filter((t) => t.product_stage_id === id && !t.completed_at).length;
    },
    stageDone(id) {
        return this.tasks.filter((t) => t.product_stage_id === id && t.completed_at)
            .length;
    },
    subProgress(t) {
        return `${fa(t.subtasks.filter((s) => s.completed_at || s.completed).length)}/${fa(t.subtasks.length)}`;
    },
    overdue(t) {
        return !!t.due_date && !t.completed_at && t.due_date < today();
    },
    canWrite(project) {
        return !!project;
    },
    isOwner(project) {
        return !!project;
    },
    canEditTask(task) {
        return !!task && Number(task.created_by) === Number(this.user.id);
    },
    get taskReadOnly() {
        return !!this.taskForm?.id && !this.canEditTask(this.taskForm);
    },
    clearToast() {
        clearTimeout(this.toastTimer);
        this.toast = "";
        this.toastError = false;
        this.toastActionLabel = "";
        this.toastActionTaskId = null;
    },
    notify(message, error = false, duration = 4500) {
        clearTimeout(this.toastTimer);
        this.toast = message;
        this.toastError = error;
        this.toastActionLabel = "";
        this.toastActionTaskId = null;
        this.toastTimer = setTimeout(() => this.clearToast(), duration);
    },
    showTaskUndo(taskId) {
        clearTimeout(this.toastTimer);
        this.toast = "تسک انجام شد.";
        this.toastError = false;
        this.toastActionLabel = "برگرداندن";
        this.toastActionTaskId = Number(taskId);
        this.toastTimer = setTimeout(() => this.clearToast(), 6000);
    },
    async undoCompletedTask() {
        const taskId = Number(this.toastActionTaskId);
        if (!taskId) return;
        clearTimeout(this.toastTimer);
        try {
            const updated = await this.api(`/tasks/${taskId}/complete`, "PATCH", { completed: false });
            const task = this.tasks.find((item) => Number(item.id) === taskId);
            if (task) task.completed_at = updated.completed_at;
            if (Number(this.taskForm?.id) === taskId) {
                this.taskForm.completed_at = updated.completed_at;
                this.taskForm.completed = false;
            }
            this.notify("تسک برگردانده شد.");
        } catch (e) {
            this.fail(e);
        }
    },
    fail(e) {
        this.errors = e.errors || {};
        const validationMessages = Object.values(this.errors).flat().filter(Boolean);
        this.notify(validationMessages.join(" · ") || e.message || "ذخیره انجام نشد. دوباره تلاش کنید.", true, 6500);
    },
    err(k) {
        return (this.errors[k]?.[0] || "").replace(/[0-9]/g,d=>"۰۱۲۳۴۵۶۷۸۹"[d]);
    },
    showModal(name) {
        this.errors = {};
        clearTimeout(this.modalCloseTimer);
        this.closingModal = null;
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
            projectEdit: this.projectEditForm,
            stage: this.stageForm,
            label: this.labelForm,
            goal: this.goalForm,
            files: this.pendingFiles.map((f) => f.name),
            comment: this.commentText,
        });
    },
    taskDetailsDirty() {
        if (this.modal !== "task" || !this.taskForm.id || !this.taskDetailsEditing || !this.taskEditSnapshot) return false;
        return (this.taskForm.title ?? "") !== (this.taskEditSnapshot.title ?? "")
            || (this.taskForm.description ?? "") !== (this.taskEditSnapshot.description ?? "");
    },
    isDirty() {
        if (!this.modal) return false;
        if (this.modal === "task") return this.taskDetailsDirty();
        return this.snapshot() !== this.modalSnapshot;
    },
    closeModal() {
        if (this.busy) return;
        if (this.modal === "task") return this.closeTaskComposer();
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
        this.commentFiles = [];
        this.commentText = "";
        this.taskDateOpen = false;
        this.taskDetailsEditing = !task;
        this.taskSaveStatus = "";
        this.labelQuery = "";
        this.inlineStage = false;
        const personalStage = !!stage && !stage.project_id;
        let p = personalStage ? null : (project || this.currentProject || this.projects.find((p) => this.canWrite(p)));
        if (task) {
            try {
                task = await this.api("/tasks/" + task.id);
                p = this.projects.find((p) => p.id === task.project_id) || null;
            } catch (e) {
                return this.fail(e);
            }
        }
        if (!p && !personalStage && !task) {
            this.notify("ابتدا یک پروژه و مراحل آن را بسازید.");
            return this.openProject();
        }
        this.taskForm = task
            ? {
                  ...clone(task),
                  labels: task.labels.map((l) => l.id),
                  completed: !!task.completed_at,
                  subtasks: task.subtasks.map((s) => ({ ...s, completed: !!s.completed_at })),
              }
            : {
                  project_id: p?.id || null,
                  title: "",
                  description: "",
                  product_stage_id: stage?.member_id ? null : (stage?.id || null),
                  member_stage_id: stage?.member_id === this.user.id ? stage.id : (p ? (this.myStages(p).find((s) => !stage || s.product_stage_id === stage.id)?.id || "") : null),
                  owner_id: this.user.id,
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
        const ownerId = this.taskForm.created_by || this.user.id;
        if (!this.formProject) return (this.personalStages || []).filter((stage) => stage.member_id == ownerId);
        return (this.formProject.member_stages || []).filter((stage) => stage.member_id == ownerId);
    },
    get taskHistory() { return (this.taskForm.activities || []).filter((a) => ['title', 'start_date', 'due_date'].includes(a.type)); },
    get selectedLabel() { return this.labels.find((l) => l.id == this.labelFilterId) || null; },
    get selectedLabelTasks() { return this.labelFilterId ? this.tasks.filter((t) => !t.completed_at && t.labels.some((l) => l.id == this.labelFilterId)) : []; },
    get formLabels() {
        return this.labels.filter(
            (l) => (l.project_id == this.taskForm.project_id || (!l.project_id && l.member_id === this.user.id)) && l.name.includes(this.labelQuery),
        );
    },
    startTaskDetailsEdit() {
        if (this.taskReadOnly) return;
        this.taskEditSnapshot = {title:this.taskForm.title, description:this.taskForm.description};
        this.taskDetailsEditing = true;
        this.$nextTick(() => document.querySelector('.task-title-editor')?.focus());
    },
    cancelTaskDetailsEdit() {
        if (this.taskEditSnapshot) Object.assign(this.taskForm, this.taskEditSnapshot);
        this.taskDetailsEditing = false;
    },
    async saveTaskDetailsEdit() {
        if (!this.taskForm.id) return;
        await this.autosaveTaskNow(true);
        this.taskEditSnapshot = {title:this.taskForm.title, description:this.taskForm.description};
        this.taskDetailsEditing = false;
    },
    autoGrow(e) { e.target.style.height='auto'; e.target.style.height=e.target.scrollHeight+'px'; },
    taskDateLabel() {
        const a = this.taskForm.start_date, b = this.taskForm.due_date;
        if (!a && !b) return 'بدون تاریخ';
        const toDate = (x) => new Date(x + 'T12:00:00');
        const parts = (x) => Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian', {year:'numeric',month:'numeric',day:'numeric'}).formatToParts(toDate(x)).filter(p=>p.type!=='literal').map(p=>[p.type,+p.value]));
        const fmt = (x, year=false) => cleanPersianDate(new Intl.DateTimeFormat('fa-IR-u-ca-persian',{day:'numeric',month:'long',...(year?{year:'numeric'}:{})}).format(toDate(x)));
        const currentYear = +new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric'}).format(new Date());
        const pa = parts(a), pb = b ? parts(b) : pa;
        if (a === b || !b) return fmt(a, pa.year !== currentYear);
        if (pa.year === pb.year && pa.month === pb.month) return fa(pa.day) + ' تا ' + fmt(b, pb.year !== currentYear);
        return fmt(a, pa.year !== currentYear) + ' تا ' + fmt(b, pb.year !== currentYear);
    },
    openTaskDate(trigger) {
        this.taskDateOpen = true;
        this.taskDatePickingEnd = false;
        this.taskDateMode = this.taskForm.start_date && this.taskForm.due_date && this.taskForm.start_date !== this.taskForm.due_date ? 'range' : 'single';
        this.taskDateCursor = new Date((this.taskForm.start_date || today()) + 'T12:00:00');
        this.renderTaskDate();
        const r = trigger?.getBoundingClientRect();
        if (r) {
            const w = Math.min(288, innerWidth - 24);
            const left = Math.max(12, Math.min(innerWidth - w - 12, r.right - w));
            const top = r.bottom + 4;
            this.taskDateStyle = `position:fixed;top:${top}px;left:${left}px;width:${w}px`;
        }
    },
    taskDateHeading(){return cleanPersianDate(new Intl.DateTimeFormat('fa-IR-u-ca-persian',{month:'long',year:'numeric'}).format(this.taskDateCursor));},
    renderTaskDate() {
        const d = new Date(this.taskDateCursor);
        const p = Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian',{year:'numeric',month:'numeric',day:'numeric'}).formatToParts(d).filter(x=>x.type!=='literal').map(x=>[x.type,+x.value]));
        const start = new Date(d); start.setDate(start.getDate() - p.day + 1); this.taskDateCursor = start;
        const off = (start.getDay() + 1) % 7;
        this.taskDateDays = Array.from({length:42}, (_, i) => {
            const x = new Date(start); x.setDate(x.getDate() + i - off);
            const iso = [x.getFullYear(), String(x.getMonth()+1).padStart(2,'0'), String(x.getDate()).padStart(2,'0')].join('-');
            const pp = Object.fromEntries(new Intl.DateTimeFormat('en-US-u-ca-persian',{month:'numeric',day:'numeric'}).formatToParts(x).filter(z=>z.type!=='literal').map(z=>[z.type,+z.value]));
            const fullLabel = cleanPersianDate(new Intl.DateTimeFormat('fa-IR-u-ca-persian',{weekday:'long',day:'numeric',month:'long',year:'numeric'}).format(x));
            return {iso, label:fa(pp.day), muted:pp.month!==p.month, fullLabel};
        });
    },
    moveTaskDate(n){const d=new Date(this.taskDateCursor);d.setDate(d.getDate()+(n>0?32:-1));this.taskDateCursor=d;this.renderTaskDate();},
    pickTaskDate(iso){
        if(this.taskDateMode==='single'){this.taskForm.start_date=iso;this.taskForm.due_date=iso;this.taskDateOpen=false;this.autosaveTaskNow();return;}
        if(!this.taskDatePickingEnd){this.taskForm.start_date=iso;this.taskForm.due_date=iso;this.taskDatePickingEnd=true;return;}
        if(iso<this.taskForm.start_date){this.taskForm.due_date=this.taskForm.start_date;this.taskForm.start_date=iso;}else{this.taskForm.due_date=iso;}
        this.taskDatePickingEnd=false;this.taskDateOpen=false;this.autosaveTaskNow();
    },
    taskDateClass(day){
        const start=this.taskForm.start_date, end=this.taskForm.due_date;
        const inRange=this.taskDateMode==='range'&&start&&end&&day.iso>=start&&day.iso<=end;
        return {'range-day':inRange,'range-start':inRange&&day.iso===start,'range-end':inRange&&day.iso===end,'single-selected':this.taskDateMode==='single'&&day.iso===start,'is-today':day.iso===today(),'is-past':day.iso<today(),'muted':day.muted};
    },
    memberStageChanged() {
        this.autosaveTaskNow();
    },
    productStageChanged() {
        this.autosaveTaskNow();
    },
    get selectedProductStage() {
        return this.formProject?.stages?.find((stage) => stage.id == this.taskForm.product_stage_id) || null;
    },
    get selectedMemberStage() {
        return this.myStageColumns.find((stage) => stage.id == this.taskForm.member_stage_id) || null;
    },
    async saveTask() {
        if (this.busy || this.taskSaving) return;
        this.errors = {};
        const missing = [];
        if (!String(this.taskForm.title || "").trim()) {
            this.errors.title = ["نام تسک را وارد کنید."];
            missing.push("نام تسک وارد نشده است");
        }
        if (this.formProject && !this.taskForm.product_stage_id) {
            this.errors.product_stage_id = ["مرحله پروژه را انتخاب کنید."];
            missing.push("مرحله پروژه انتخاب نشده است");
        } else if (!this.formProject && !this.taskForm.member_stage_id) {
            this.errors.member_stage_id = ["مرحله شخصی را انتخاب کنید."];
            missing.push("مرحله شخصی انتخاب نشده است");
        }
        if (missing.length) {
            this.notify("تسک ثبت نشد: " + missing.join("؛ ") + ".", true, 6500);
            const selector = !String(this.taskForm.title || "").trim() ? ".task-title-editor" : ".task-project-trigger";
            this.$nextTick(() => this.$refs.modalPanel?.querySelector(selector)?.focus());
            return;
        }
        this.busy = true;
        try {
            const id = this.taskForm.id;
            const result = await this.api(
                "/tasks" + (id ? "/" + id : ""),
                id ? "PUT" : "POST",
                this.taskPayload(),
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
                const data = new FormData();
                data.append("body", this.commentText);
                for (const file of this.commentFiles) data.append("files[]", file);
                await this.api(`/tasks/${result.id}/comments`, "POST", data);
                this.commentFiles = [];
                this.commentText = "";
            }
            this.finishTaskModalClose();
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
        await this.refresh();
            this.notify(id ? "تغییرات ذخیره شد." : "تسک جدید ساخته شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async toggleTask(t) {
        if (!this.canEditTask(t)) return;
        const completing = !t.completed_at;
        try {
            const updated = await this.api(`/tasks/${t.id}/complete`, "PATCH", { completed: completing });
            const task = this.tasks.find((item) => Number(item.id) === Number(t.id));
            if (task) task.completed_at = updated.completed_at;
            t.completed_at = updated.completed_at;
            if (Number(this.taskForm?.id) === Number(t.id)) {
                this.taskForm.completed_at = updated.completed_at;
                this.taskForm.completed = !!updated.completed_at;
            }
            if (updated.completed_at) {
                if (this.modal === "task" && Number(this.taskForm?.id) === Number(t.id)) this.finishTaskModalClose();
                this.showTaskUndo(t.id);
            } else {
                this.notify("تسک دوباره باز شد.");
            }
        } catch (e) {
            this.fail(e);
        }
    },
    removeTask() {
        if (this.taskReadOnly) return;
        this.ask(
            "حذف تسک",
            "تسک، نظرها و پیوست‌های آن حذف می‌شوند.",
            async () => {
                try {
                    await this.api("/tasks/" + this.taskForm.id, "DELETE");
                    this.finishTaskModalClose();
                    document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
        if (this.taskReadOnly) return;
        this.taskForm.subtasks.push({ title: "", completed: false });
    },
    reorderSubtask(i) {
        if (this.taskReadOnly || this.dragIndex === null) return;
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
            member_id: this.taskForm.created_by || this.user.id,
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
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
    async createOrSelectLabel() {
        const name = this.labelQuery.trim();
        if (!name) return;
        const normalized = name.toLocaleLowerCase("fa-IR");
        const existing = this.labels.find((l) =>
            (l.project_id == this.taskForm.project_id || (!l.project_id && l.member_id === this.user.id)) &&
            l.name.trim().toLocaleLowerCase("fa-IR") === normalized,
        );
        if (existing) {
            if (!this.taskForm.labels.includes(existing.id)) {
                this.taskForm.labels.push(existing.id);
                await this.autosaveTaskNow();
            }
            this.labelQuery = "";
            return;
        }
        await this.quickLabel();
    },
    async quickLabel() {
        const name = this.labelQuery.trim();
        if (!name) return;
        try {
            const l = await this.api("/labels", "POST", {
                name,
                color: "project-marker-1",
                project_id: this.taskForm.project_id,
            });
            this.labels.push(l);
            if (!this.taskForm.labels.includes(l.id)) this.taskForm.labels.push(l.id);
            this.labelQuery = "";
            await this.autosaveTaskNow();
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
            const data = new FormData();
            data.append("body", this.commentText);
            for (const file of this.commentFiles) data.append("files[]", file);
            const c = await this.api(`/tasks/${this.taskForm.id}/comments`, "POST", data);
            this.taskForm.comments.push(c);
            for (const attachment of c.attachments || []) {
                if (!this.taskForm.attachments.some((item) => item.id === attachment.id)) {
                    this.taskForm.attachments.push(attachment);
                }
            }
            this.commentFiles = [];
            this.commentText = "";
            this.notify("نظر ثبت شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    async editComment(c) {
        if (!c.editBody?.trim()) return;
        try {
            const data = new FormData();
            data.append("body", c.editBody);
            for (const file of c.editFiles || []) data.append("files[]", file);
            const updated = await this.api("/comments/" + c.id, "POST", data);
            c.body = updated.body;
            c.attachments = updated.attachments || [];
            c.editFiles = [];
            c.editing = false;
            for (const attachment of c.attachments) {
                if (!this.taskForm.attachments.some((item) => item.id === attachment.id)) {
                    this.taskForm.attachments.push(attachment);
                }
            }
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
                    const attachmentIds = new Set((c.attachments || []).map((item) => item.id));
                    this.taskForm.comments = this.taskForm.comments.filter(
                        (x) => x.id !== c.id,
                    );
                    this.taskForm.attachments = (this.taskForm.attachments || []).filter(
                        (item) => !attachmentIds.has(item.id),
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
                    for (const comment of this.taskForm.comments || []) {
                        comment.attachments = (comment.attachments || []).filter(
                            (item) => item.id !== a.id,
                        );
                    }
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف",
        );
    },
    openProjectEdit(project) {
        if (!project) return;
        this.projectEditId = Number(project.id);
        this.projectEditForm = {
            name: project.name || "",
            description: project.description || "",
            color: this.markerToken(project.color),
            goal: {
                title: project.goal?.title || "",
                start_date: project.goal?.start_date?.slice(0, 10) || today(),
                target_date: project.goal?.target_date?.slice(0, 10) || today(),
            },
        };
        this.showModal("projectEdit");
    },
    async saveProjectEdit() {
        if (!this.projectEditId || !this.projectEditForm.name?.trim()) return;
        this.busy = true;
        try {
            const updated = await this.api(`/projects/${this.projectEditId}`, "PUT", this.projectEditForm);
            const project = this.projects.find((item) => Number(item.id) === Number(this.projectEditId));
            if (project) Object.assign(project, updated);
            await this.refresh();
            this.modal = null;
            this.notify("پروژه ویرایش شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.busy = false;
        }
    },
    deleteProject(project = null) {
        const target = project || this.projects.find((item) => Number(item.id) === Number(this.projectEditId));
        if (!target) return;
        this.ask(
            "حذف پروژه",
            `پروژه «${target.name}» همراه با مراحل و تسک‌های آن برای همیشه حذف شود؟`,
            async () => {
                try {
                    await this.api(`/projects/${target.id}`, "DELETE");
                    const deletingCurrent = Number(this.projectId) === Number(target.id);
                    this.modal = null;
                    await this.refresh();
                    if (deletingCurrent) this.go("tasks");
                    this.notify("پروژه حذف شد.");
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف پروژه",
        );
    },
    openProject() {
        this.projectStep = 1;
        this.projectForm = {
            color: "project-marker-1",
            name: "",
            goal: {
                title: "",
                start_date: today(),
                target_date: today(),
            },
        };
        this.showModal("project");
    },
    validateProjectStep(step = this.projectStep) {
        this.errors = {};
        if (step === 1 && !this.projectForm.name.trim()) {
            this.errors = { name: ["نام پروژه را وارد کنید."] };
            return false;
        }
        if (step === 2) {
            for (const key of [
                "title",
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
            if (Object.keys(this.errors).length) return false;
        }
        return true;
    },
    nextStep() {
        if (this.projectStep >= 2 || !this.validateProjectStep()) return;
        this.projectStep++;
    },
    goProjectStep(step) {
        const target = Math.max(1, Math.min(2, Number(step)));
        if (target <= this.projectStep) {
            this.errors = {};
            this.projectStep = target;
            return;
        }
        while (this.projectStep < target) {
            if (!this.validateProjectStep()) return;
            this.projectStep++;
        }
    },
    async saveProject() {
        if (!this.validateProjectStep(2)) return;
        this.busy = true;
        try {
            const p = await this.api("/projects", "POST", this.projectForm);
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
        const personal = !!stage && !stage.project_id && !!stage.member_id;
        this.stageProjectId = p?.id || null;
        this.stageId = stage?.id || null;
        this.stageUser = user;
        this.stageKind = personal ? "personal" : (user ? "member" : "product");
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
            const personal = this.stageKind === "personal";
            await this.api(
                personal
                    ? `/member-stages/${this.stageId}`
                    : `/projects/${this.stageProjectId}/stages` + (this.stageId ? `/${this.stageKind}/${this.stageId}` : ""),
                this.stageId ? "PUT" : "POST",
                this.stageForm,
            );
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
        const personal = kind === 'member' && !stage.project_id;
        const project = personal ? null : (this.currentProject || this.projects.find((item) => item.id === stage.project_id));
        const options = personal
            ? (this.personalStages || []).filter((item) => item.id !== stage.id).map((item) => ({ id: item.id, title: item.title }))
            : (kind === "member"
                ? project.member_stages.filter((item) => item.member_id === (user?.id || stage.member_id) && item.id !== stage.id)
                : project.stages.filter((item) => item.id !== stage.id)
            ).map((item) => ({ id: item.id, title: item.title }));
        const hasTasks = kind === "member"
            ? this.tasks.some((task) => !task.completed_at && task.member_stage_id === stage.id)
            : this.tasks.some((task) => !task.completed_at && task.product_stage_id === stage.id);
        if (hasTasks && !options.length) {
            return this.notify("برای حذف این ستون ابتدا یک ستون مقصد بسازید.", true);
        }
        this.ask(
            "حذف ستون",
            hasTasks ? "این ستون تسک دارد. ستون مقصد انتقال تسک‌ها را انتخاب کنید." : "این ستون حذف شود؟",
            async (transferId) => {
                try {
                    const url = personal ? `/member-stages/${stage.id}` : `/projects/${project.id}/stages/${kind}/${stage.id}`;
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
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
            : { name: "", color: "project-marker-1", description: "", project_id: null };
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
            document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
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
                    document.addEventListener("input", (e) => { if (e.target instanceof HTMLTextAreaElement) this.autoGrow(e); });
        await this.refresh();
                    this.notify("برچسب حذف شد.");
                } catch (e) {
                    this.fail(e);
                }
            },
            "حذف",
        );
    },
    async saveAdminPin(form) {
        if (this.securityBusy) return;
        this.securityBusy = "admin";
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await this.api("/security/admin-pin", "PUT", payload);
            form.reset();
            this.errors = {};
            await this.refresh();
            this.closeSecurityDialog(true);
            this.notify("رمز مدیر تغییر کرد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.securityBusy = "";
        }
    },
    async saveMemberPin(form) {
        if (this.securityBusy) return;
        this.securityBusy = "member";
        try {
            const payload = Object.fromEntries(new FormData(form).entries());
            await this.api("/security/member-pin", "PUT", payload);
            form.reset();
            this.errors = {};
            await this.refresh();
            this.closeSecurityDialog(true);
            this.notify("رمز مشترک اعضا تغییر کرد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.securityBusy = "";
        }
    },
    openSecurityDialog(kind) {
        this.errors = {};
        this.securityDialogTrigger = document.activeElement;
        this.securityDialog = kind;
        this.$nextTick(() => {
            const field = kind === "admin"
                ? this.$refs.adminPinCurrent
                : this.$refs.memberPinCurrent;
            field?.focus();
        });
    },
    closeSecurityDialog(force = false) {
        if (this.securityBusy && !force) return;
        const trigger = this.securityDialogTrigger;
        this.$refs.adminPinForm?.reset();
        this.$refs.memberPinForm?.reset();
        this.securityDialog = "";
        this.securityDialogTrigger = null;
        this.errors = {};
        this.$nextTick(() => trigger?.focus?.());
    },
    trapSecurityDialog(e) {
        const panel = this.$refs.securityDialogPanel;
        const focusable = [...(panel?.querySelectorAll("button,input:not([type=hidden]),[tabindex='0']") || [])]
            .filter((el) => !el.disabled && el.getClientRects().length);
        if (!focusable.length) return;
        const first = focusable[0];
        const last = focusable.at(-1);
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    },
    async saveSettings() {
        if (this.busy) return;
        this.errors = {};
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
    async uploadAvatar(file) {
        if (!file || this.avatarUploading) return;
        const extensions = ["jpg", "jpeg", "png", "webp"];
        const mimeTypes = ["image/jpeg", "image/png", "image/webp"];
        const extension = file.name?.split(".").pop()?.toLowerCase();
        if (!extensions.includes(extension) || (file.type && !mimeTypes.includes(file.type))) {
            this.notify("فقط تصویرهای JPG، PNG یا WebP پذیرفته می‌شوند.", true);
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            this.notify("حجم تصویر باید حداکثر ۲ مگابایت باشد.", true);
            return;
        }

        this.avatarUploading = true;
        try {
            const data = new FormData();
            data.append("avatar", file);
            await this.api("/settings/avatar", "POST", data);
            await this.refresh();
            this.notify("عکس پروفایل به‌روزرسانی شد.");
        } catch (e) {
            this.notify(e.errors?.avatar?.[0] || e.message || "آپلود عکس انجام نشد. دوباره تلاش کنید.", true);
        } finally {
            this.avatarUploading = false;
        }
    },
    async removeAvatar() {
        if (this.avatarUploading) return;
        this.avatarUploading = true;
        try {
            await this.api("/settings/avatar", "DELETE");
            await this.refresh();
            this.notify("عکس پروفایل حذف شد.");
        } catch (e) {
            this.fail(e);
        } finally {
            this.avatarUploading = false;
        }
    },
}));
window.Alpine = Alpine;
Alpine.start();
