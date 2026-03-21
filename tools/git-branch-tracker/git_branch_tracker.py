import os
import re
import subprocess
import tkinter as tk
from pathlib import Path
from tkinter import messagebox, simpledialog, ttk
from tkinter.scrolledtext import ScrolledText


PROTECTED_BRANCHES = {"main", "master", "develop"}
WINDOW_TITLE = "Git Branch Tracker"


def find_repo_root(start_path: Path) -> Path:
    current = start_path.resolve()
    for candidate in [current, *current.parents]:
        if (candidate / ".git").exists():
            return candidate
    return current


SCRIPT_DIR = Path(__file__).resolve().parent
DEFAULT_REPO = find_repo_root(SCRIPT_DIR)


class GitError(RuntimeError):
    pass


class GitBranchTracker(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title(WINDOW_TITLE)
        self.geometry("1680x980")
        self.minsize(1420, 860)

        self.repo_path = tk.StringVar(value=str(DEFAULT_REPO))
        self.branch_var = tk.StringVar(value="-")
        self.upstream_var = tk.StringVar(value="-")
        self.status_var = tk.StringVar(value="Cargando...")
        self.staged_count_var = tk.StringVar(value="0 staged")
        self.commit_graph_scope_var = tk.StringVar(value="all")
        self.selected_commit_hash = None
        self.commit_nodes = []
        self.commit_text_blocks = []

        self._build_ui()
        self.refresh_all()

    def _build_ui(self) -> None:
        self.columnconfigure(0, weight=1)
        self.rowconfigure(1, weight=1)
        self.rowconfigure(2, weight=2)

        top = ttk.Frame(self, padding=10)
        top.grid(row=0, column=0, sticky="ew")
        top.columnconfigure(1, weight=1)

        ttk.Label(top, text="Repositorio").grid(row=0, column=0, sticky="w")
        repo_entry = ttk.Entry(top, textvariable=self.repo_path)
        repo_entry.grid(row=0, column=1, sticky="ew", padx=(8, 8))
        ttk.Button(top, text="Recargar", command=self.refresh_all).grid(row=0, column=2, padx=(0, 8))
        ttk.Button(top, text="Abrir carpeta", command=self.open_repo_folder).grid(row=0, column=3)

        status_frame = ttk.Frame(top)
        status_frame.grid(row=1, column=0, columnspan=4, sticky="ew", pady=(10, 0))
        for i in range(8):
            status_frame.columnconfigure(i, weight=1 if i % 2 else 0)

        ttk.Label(status_frame, text="Rama actual").grid(row=0, column=0, sticky="w")
        ttk.Label(status_frame, textvariable=self.branch_var).grid(row=0, column=1, sticky="w", padx=(6, 24))
        ttk.Label(status_frame, text="Upstream").grid(row=0, column=2, sticky="w")
        ttk.Label(status_frame, textvariable=self.upstream_var).grid(row=0, column=3, sticky="w", padx=(6, 24))
        ttk.Label(status_frame, text="Estado").grid(row=0, column=4, sticky="w")
        ttk.Label(status_frame, textvariable=self.status_var).grid(row=0, column=5, sticky="w", padx=(6, 24))
        ttk.Label(status_frame, text="Stage").grid(row=0, column=6, sticky="w")
        ttk.Label(status_frame, textvariable=self.staged_count_var).grid(row=0, column=7, sticky="w", padx=(6, 0))

        main_pane = ttk.Panedwindow(self, orient=tk.HORIZONTAL)
        main_pane.grid(row=1, column=0, sticky="nsew", padx=10, pady=(0, 10))

        left = ttk.Frame(main_pane, padding=8)
        left.columnconfigure(0, weight=1)
        left.rowconfigure(2, weight=1)
        left.rowconfigure(5, weight=1)
        main_pane.add(left, weight=1)

        ttk.Label(left, text="Acciones de ramas").grid(row=0, column=0, sticky="w")

        branch_buttons = ttk.Frame(left)
        branch_buttons.grid(row=1, column=0, sticky="ew", pady=(6, 10))
        for i in range(3):
            branch_buttons.columnconfigure(i, weight=1)
        ttk.Button(branch_buttons, text="Nueva rama", command=self.create_branch).grid(row=0, column=0, sticky="ew", padx=(0, 6))
        ttk.Button(branch_buttons, text="Rama automatica", command=self.create_auto_branch).grid(row=0, column=1, sticky="ew", padx=(0, 6))
        ttk.Button(branch_buttons, text="Checkout", command=self.checkout_selected_branch).grid(row=0, column=2, sticky="ew")
        ttk.Button(branch_buttons, text="Desde remota", command=self.checkout_remote_branch).grid(row=1, column=0, sticky="ew", padx=(0, 6), pady=(6, 0))
        ttk.Button(branch_buttons, text="Borrar local", command=self.delete_selected_branch).grid(row=1, column=1, sticky="ew", padx=(0, 6), pady=(6, 0))
        ttk.Button(branch_buttons, text="Merge hacia actual", command=self.merge_selected_branch_into_current).grid(row=1, column=2, sticky="ew", pady=(6, 0))

        branches_pane = ttk.Panedwindow(left, orient=tk.VERTICAL)
        branches_pane.grid(row=2, column=0, sticky="nsew")

        local_frame = ttk.LabelFrame(branches_pane, text="Ramas locales", padding=6)
        local_frame.columnconfigure(0, weight=1)
        local_frame.rowconfigure(0, weight=1)
        self.local_branch_list = tk.Listbox(local_frame, exportselection=False)
        self.local_branch_list.grid(row=0, column=0, sticky="nsew")
        self.local_branch_list.bind("<Double-1>", lambda _e: self.checkout_selected_branch())
        branches_pane.add(local_frame, weight=1)

        remote_frame = ttk.LabelFrame(branches_pane, text="Ramas remotas", padding=6)
        remote_frame.columnconfigure(0, weight=1)
        remote_frame.rowconfigure(0, weight=1)
        self.remote_branch_list = tk.Listbox(remote_frame, exportselection=False)
        self.remote_branch_list.grid(row=0, column=0, sticky="nsew")
        self.remote_branch_list.bind("<Double-1>", lambda _e: self.checkout_remote_branch())
        branches_pane.add(remote_frame, weight=1)

        actions_frame = ttk.LabelFrame(left, text="Acciones rapidas", padding=8)
        actions_frame.grid(row=3, column=0, sticky="ew", pady=(10, 10))
        for i in range(3):
            actions_frame.columnconfigure(i, weight=1)
        ttk.Button(actions_frame, text="Pull actual", command=self.pull_current_branch).grid(row=0, column=0, sticky="ew", padx=(0, 6))
        ttk.Button(actions_frame, text="Push actual", command=self.push_current_branch).grid(row=0, column=1, sticky="ew", padx=(0, 6))
        ttk.Button(actions_frame, text="Checklist PR", command=self.copy_pr_checklist).grid(row=0, column=2, sticky="ew")

        tree_header = ttk.Frame(left)
        tree_header.grid(row=4, column=0, sticky="ew", pady=(2, 6))
        tree_header.columnconfigure(1, weight=1)
        ttk.Label(tree_header, text="Arbol de commits").grid(row=0, column=0, sticky="w")
        scope_box = ttk.Combobox(
            tree_header,
            textvariable=self.commit_graph_scope_var,
            values=["all", "current"],
            state="readonly",
            width=12,
        )
        scope_box.grid(row=0, column=2, sticky="e")
        scope_box.bind("<<ComboboxSelected>>", lambda _e: self.refresh_commit_graph())

        tree_frame = ttk.Frame(left)
        tree_frame.grid(row=5, column=0, sticky="nsew")
        tree_frame.columnconfigure(0, weight=1)
        tree_frame.rowconfigure(0, weight=1)

        self.commit_canvas = tk.Canvas(tree_frame, background="#111827", highlightthickness=0)
        self.commit_canvas.grid(row=0, column=0, sticky="nsew")
        tree_scroll_y = ttk.Scrollbar(tree_frame, orient="vertical", command=self.commit_canvas.yview)
        tree_scroll_y.grid(row=0, column=1, sticky="ns")
        tree_scroll_x = ttk.Scrollbar(tree_frame, orient="horizontal", command=self.commit_canvas.xview)
        tree_scroll_x.grid(row=1, column=0, sticky="ew")
        self.commit_canvas.configure(yscrollcommand=tree_scroll_y.set, xscrollcommand=tree_scroll_x.set)
        self.commit_canvas.bind("<Button-1>", self._handle_canvas_click)

        right = ttk.Frame(main_pane, padding=8)
        right.columnconfigure(0, weight=1)
        right.rowconfigure(1, weight=2)
        right.rowconfigure(3, weight=2)
        right.rowconfigure(6, weight=2)
        main_pane.add(right, weight=2)

        ttk.Label(right, text="Archivos").grid(row=0, column=0, sticky="w")
        files_frame = ttk.Frame(right)
        files_frame.grid(row=1, column=0, sticky="nsew")
        files_frame.columnconfigure(0, weight=1)
        files_frame.rowconfigure(0, weight=1)
        self.files_tree = ttk.Treeview(files_frame, columns=("status", "path"), show="headings", selectmode="browse")
        self.files_tree.heading("status", text="Estado")
        self.files_tree.heading("path", text="Archivo")
        self.files_tree.column("status", width=90, anchor="center")
        self.files_tree.column("path", width=700, anchor="w")
        self.files_tree.grid(row=0, column=0, sticky="nsew")
        self.files_tree.bind("<<TreeviewSelect>>", lambda _e: self.show_selected_diff())
        files_scroll = ttk.Scrollbar(files_frame, orient="vertical", command=self.files_tree.yview)
        files_scroll.grid(row=0, column=1, sticky="ns")
        self.files_tree.configure(yscrollcommand=files_scroll.set)

        stage_buttons = ttk.Frame(right)
        stage_buttons.grid(row=2, column=0, sticky="ew", pady=(8, 10))
        for i in range(4):
            stage_buttons.columnconfigure(i, weight=1)
        ttk.Button(stage_buttons, text="Stage/Unstage archivo", command=self.toggle_stage_selected_file).grid(row=0, column=0, sticky="ew", padx=(0, 6))
        ttk.Button(stage_buttons, text="Stage all", command=self.stage_all).grid(row=0, column=1, sticky="ew", padx=(0, 6))
        ttk.Button(stage_buttons, text="Unstage all", command=self.unstage_all).grid(row=0, column=2, sticky="ew", padx=(0, 6))
        ttk.Button(stage_buttons, text="Refrescar diff", command=self.show_selected_diff).grid(row=0, column=3, sticky="ew")

        ttk.Label(right, text="Diff del archivo seleccionado").grid(row=3, column=0, sticky="w")
        self.diff_text = ScrolledText(right, wrap="none", height=16)
        self.diff_text.grid(row=4, column=0, sticky="nsew", pady=(6, 10))

        commit_action_frame = ttk.Frame(right)
        commit_action_frame.grid(row=5, column=0, sticky="ew")
        commit_action_frame.columnconfigure(1, weight=1)
        ttk.Label(commit_action_frame, text="Commit").grid(row=0, column=0, sticky="w")
        self.commit_message = tk.StringVar()
        ttk.Entry(commit_action_frame, textvariable=self.commit_message).grid(row=0, column=1, sticky="ew", padx=(8, 8))
        ttk.Button(commit_action_frame, text="Commit staged", command=self.commit_staged).grid(row=0, column=2)

        bottom_pane = ttk.Panedwindow(self, orient=tk.HORIZONTAL)
        bottom_pane.grid(row=2, column=0, sticky="nsew", padx=10, pady=(0, 10))

        commit_details_frame = ttk.LabelFrame(bottom_pane, text="Detalle de commit seleccionado", padding=8)
        commit_details_frame.columnconfigure(0, weight=1)
        commit_details_frame.rowconfigure(0, weight=1)
        self.commit_detail_text = ScrolledText(commit_details_frame, wrap="word", height=10)
        self.commit_detail_text.grid(row=0, column=0, sticky="nsew")
        bottom_pane.add(commit_details_frame, weight=1)

        output_frame = ttk.LabelFrame(bottom_pane, text="Salida / Resultado", padding=8)
        output_frame.columnconfigure(0, weight=1)
        output_frame.rowconfigure(0, weight=1)
        self.output_text = ScrolledText(output_frame, wrap="word", height=10)
        self.output_text.grid(row=0, column=0, sticky="nsew")
        bottom_pane.add(output_frame, weight=1)

    def repo(self) -> Path:
        return Path(self.repo_path.get()).expanduser().resolve()

    def append_output(self, text: str) -> None:
        self.output_text.delete("1.0", tk.END)
        self.output_text.insert("1.0", text.strip() + "\n")

    def git(self, *args: str, check: bool = True) -> str:
        try:
            result = subprocess.run(
                ["git", *args],
                cwd=str(self.repo()),
                text=True,
                capture_output=True,
                check=False,
            )
        except FileNotFoundError as exc:
            raise GitError("Git no esta disponible en PATH.") from exc

        output = (result.stdout or "") + (result.stderr or "")
        if check and result.returncode != 0:
            raise GitError(output.strip() or "Error desconocido ejecutando git.")
        return output.strip()

    def safe_git_action(self, action) -> None:
        try:
            action()
        except GitError as exc:
            messagebox.showerror("Git", str(exc))
            self.append_output(str(exc))
        finally:
            self.refresh_all()

    def refresh_all(self) -> None:
        try:
            self.refresh_repo_status()
            self.refresh_branches()
            self.refresh_files()
            self.refresh_commit_graph()
        except GitError as exc:
            self.append_output(str(exc))
            messagebox.showerror("Git", str(exc))

    def refresh_repo_status(self) -> None:
        branch = self.git("branch", "--show-current")
        self.branch_var.set(branch or "(detached)")
        try:
            upstream = self.git("rev-parse", "--abbrev-ref", "--symbolic-full-name", "@{u}")
        except GitError:
            upstream = "-"
        self.upstream_var.set(upstream or "-")

        porcelain = self.git("status", "--porcelain", check=False)
        lines = [line for line in porcelain.splitlines() if line.strip()]
        self.status_var.set("Limpio" if not lines else f"{len(lines)} cambios")
        staged_count = sum(1 for line in lines if line[:1].strip())
        self.staged_count_var.set(f"{staged_count} staged")

    def refresh_branches(self) -> None:
        current = self.branch_var.get()
        locals_output = self.git("branch", "--format=%(refname:short)")
        remotes_output = self.git("branch", "-r", "--format=%(refname:short)")

        self.local_branch_list.delete(0, tk.END)
        for branch in [b for b in locals_output.splitlines() if b]:
            label = f"{branch}  (actual)" if branch == current else branch
            self.local_branch_list.insert(tk.END, label)

        self.remote_branch_list.delete(0, tk.END)
        for branch in [b for b in remotes_output.splitlines() if b and "HEAD ->" not in b]:
            self.remote_branch_list.insert(tk.END, branch)

    def parse_porcelain_entry(self, line: str) -> tuple[str, str]:
        status = line[:2]
        path = line[3:].split(" -> ")[-1]
        if status[0] != " ":
            label = f"{status[0]} staged"
        elif status[1] != " ":
            label = f"{status[1]} unstaged"
        else:
            label = status.strip() or "??"
        return label, path

    def refresh_files(self) -> None:
        for item in self.files_tree.get_children():
            self.files_tree.delete(item)
        porcelain = self.git("status", "--porcelain", check=False)
        for line in [line for line in porcelain.splitlines() if line.strip()]:
            label, path = self.parse_porcelain_entry(line)
            self.files_tree.insert("", tk.END, values=(label, path))
        self.diff_text.delete("1.0", tk.END)

    def current_branch(self) -> str:
        return self.branch_var.get()

    def selected_local_branch(self) -> str | None:
        selection = self.local_branch_list.curselection()
        if not selection:
            return None
        raw = self.local_branch_list.get(selection[0])
        return raw.replace("  (actual)", "")

    def selected_remote_branch(self) -> str | None:
        selection = self.remote_branch_list.curselection()
        if not selection:
            return None
        return self.remote_branch_list.get(selection[0])

    def create_branch(self) -> None:
        name = simpledialog.askstring("Nueva rama", "Nombre de la rama:")
        if not name:
            return

        def action() -> None:
            self.append_output(self.git("checkout", "-b", name))

        self.safe_git_action(action)

    def suggested_branch_name(self) -> str:
        slug = simpledialog.askstring("Rama automatica", "Slug corto de la tarea:")
        if not slug:
            return ""
        slug = re.sub(r"[^a-zA-Z0-9._-]+", "-", slug.strip().lower()).strip("-")
        return f"codex/{slug}" if slug else ""

    def create_auto_branch(self) -> None:
        current = self.current_branch()
        if current and current not in PROTECTED_BRANCHES:
            messagebox.showinfo("Rama automatica", f"Ya estas trabajando en `{current}`.")
            return
        branch_name = self.suggested_branch_name()
        if not branch_name:
            return

        def action() -> None:
            if current in PROTECTED_BRANCHES:
                self.git("pull", "--ff-only", "origin", current, check=False)
            self.append_output(self.git("checkout", "-b", branch_name))

        self.safe_git_action(action)

    def checkout_selected_branch(self) -> None:
        branch = self.selected_local_branch()
        if not branch:
            messagebox.showinfo("Checkout", "Selecciona una rama local.")
            return

        def action() -> None:
            self.append_output(self.git("checkout", branch))

        self.safe_git_action(action)

    def checkout_remote_branch(self) -> None:
        remote_branch = self.selected_remote_branch()
        if not remote_branch:
            messagebox.showinfo("Checkout remoto", "Selecciona una rama remota.")
            return
        local_name = remote_branch.split("/", 1)[1] if "/" in remote_branch else remote_branch
        local_name = simpledialog.askstring("Checkout desde remota", "Nombre de rama local:", initialvalue=local_name)
        if not local_name:
            return

        def action() -> None:
            self.append_output(self.git("checkout", "-b", local_name, "--track", remote_branch))

        self.safe_git_action(action)

    def delete_selected_branch(self) -> None:
        branch = self.selected_local_branch()
        if not branch:
            messagebox.showinfo("Borrar rama", "Selecciona una rama local.")
            return
        if branch in PROTECTED_BRANCHES:
            messagebox.showwarning("Borrar rama", "No se puede borrar una rama protegida.")
            return
        if branch == self.current_branch():
            messagebox.showwarning("Borrar rama", "No puedes borrar la rama actual.")
            return
        if not messagebox.askyesno("Borrar rama", f"Eliminar la rama local `{branch}`?"):
            return

        def action() -> None:
            self.append_output(self.git("branch", "-D", branch))

        self.safe_git_action(action)

    def pull_current_branch(self) -> None:
        branch = self.current_branch()
        self.safe_git_action(lambda: self.append_output(self.git("pull", "--ff-only", "origin", branch)))

    def push_current_branch(self) -> None:
        branch = self.current_branch()
        self.safe_git_action(lambda: self.append_output(self.git("push", "-u", "origin", branch)))

    def merge_selected_branch_into_current(self) -> None:
        branch = self.selected_local_branch()
        current = self.current_branch()
        if not branch:
            messagebox.showinfo("Merge", "Selecciona una rama local para mergear.")
            return
        if branch == current:
            messagebox.showwarning("Merge", "Selecciona una rama distinta a la actual.")
            return
        if not messagebox.askyesno("Merge", f"Mergear `{branch}` hacia `{current}` usando --no-ff?"):
            return

        def action() -> None:
            self.append_output(self.git("merge", "--no-ff", branch))

        self.safe_git_action(action)

    def copy_pr_checklist(self) -> None:
        branch = self.current_branch()
        text = "\n".join(
            [
                "Checklist de PR",
                f"- Rama: {branch}",
                "- Objetivo:",
                "- Cambios principales:",
                "- Riesgos / regresiones:",
                "- Validaciones realizadas:",
                "- Pendiente por probar:",
            ]
        )
        self.clipboard_clear()
        self.clipboard_append(text)
        self.append_output("Checklist de PR copiado al portapapeles.")

    def selected_file_path(self) -> str | None:
        selection = self.files_tree.selection()
        if not selection:
            return None
        values = self.files_tree.item(selection[0], "values")
        return values[1] if values else None

    def selected_file_status(self) -> str | None:
        selection = self.files_tree.selection()
        if not selection:
            return None
        values = self.files_tree.item(selection[0], "values")
        return values[0] if values else None

    def toggle_stage_selected_file(self) -> None:
        path = self.selected_file_path()
        status = self.selected_file_status()
        if not path or not status:
            messagebox.showinfo("Stage", "Selecciona un archivo.")
            return

        def action() -> None:
            if "staged" in status:
                self.append_output(self.git("restore", "--staged", "--", path))
            else:
                self.append_output(self.git("add", "--", path))

        self.safe_git_action(action)

    def stage_all(self) -> None:
        self.safe_git_action(lambda: self.append_output(self.git("add", "-A")))

    def unstage_all(self) -> None:
        self.safe_git_action(lambda: self.append_output(self.git("restore", "--staged", ".")))

    def show_selected_diff(self) -> None:
        path = self.selected_file_path()
        self.diff_text.delete("1.0", tk.END)
        if not path:
            return
        try:
            status = self.selected_file_status() or ""
            if "staged" in status:
                diff = self.git("diff", "--cached", "--", path, check=False)
            else:
                diff = self.git("diff", "--", path, check=False)
            self.diff_text.insert("1.0", diff or "(Sin diff para mostrar)")
        except GitError as exc:
            self.diff_text.insert("1.0", str(exc))

    def commit_staged(self) -> None:
        branch = self.current_branch()
        if branch in PROTECTED_BRANCHES:
            messagebox.showwarning("Commit", f"No se permiten commits directos en `{branch}`.")
            return
        message = self.commit_message.get().strip()
        if not message:
            messagebox.showinfo("Commit", "Escribe un mensaje de commit.")
            return
        if self.staged_count_var.get().startswith("0 "):
            messagebox.showinfo("Commit", "No hay archivos staged.")
            return

        def action() -> None:
            self.append_output(self.git("commit", "-m", message))
            self.commit_message.set("")

        self.safe_git_action(action)

    def open_repo_folder(self) -> None:
        os.startfile(str(self.repo()))

    def get_commit_graph_lines(self) -> list[str]:
        args = ["log", "--graph", "--decorate", "--oneline", "--all"]
        if self.commit_graph_scope_var.get() == "current":
            args = ["log", "--graph", "--decorate", "--oneline", "HEAD"]
        output = self.git(*args, check=False)
        return [line.rstrip() for line in output.splitlines() if line.strip()]

    def color_for_lane(self, lane_index: int) -> str:
        colors = ["#22c55e", "#38bdf8", "#f59e0b", "#f472b6", "#a78bfa", "#ef4444", "#14b8a6", "#eab308"]
        return colors[lane_index % len(colors)]

    def refresh_commit_graph(self) -> None:
        self.commit_canvas.delete("all")
        self.commit_nodes.clear()
        self.commit_text_blocks.clear()
        lines = self.get_commit_graph_lines()
        if not lines:
            self.commit_canvas.create_text(20, 20, text="Sin historial para mostrar", anchor="nw", fill="#e5e7eb")
            self.commit_canvas.configure(scrollregion=(0, 0, 600, 80))
            return

        row_height = 28
        left_padding = 24
        text_start = 240
        lane_chars = "|/\\* "
        y = 20

        for line in lines:
            graph_part = ""
            info_part = line
            match = re.search(r"[0-9a-f]{7,40}\b", line)
            if match:
                graph_part = line[:match.start()]
                info_part = line[match.start():]

            commit_match = re.match(r"(?P<hash>[0-9a-f]{7,40})\s+(?P<rest>.*)", info_part)
            commit_hash = commit_match.group("hash") if commit_match else None
            rest = commit_match.group("rest") if commit_match else info_part

            for idx, ch in enumerate(graph_part):
                if ch not in lane_chars:
                    continue
                x = left_padding + idx * 10
                color = self.color_for_lane(idx)
                if ch == "|":
                    self.commit_canvas.create_line(x, y - 10, x, y + 10, fill=color, width=2)
                elif ch == "/":
                    self.commit_canvas.create_line(x + 6, y - 10, x - 6, y + 10, fill=color, width=2)
                elif ch == "\\":
                    self.commit_canvas.create_line(x - 6, y - 10, x + 6, y + 10, fill=color, width=2)
                elif ch == "*":
                    node = self.commit_canvas.create_oval(x - 6, y - 6, x + 6, y + 6, fill=color, outline="")
                    if commit_hash:
                        self.commit_nodes.append({"id": node, "hash": commit_hash, "x": x, "y": y})

            if commit_hash:
                text_id = self.commit_canvas.create_text(
                    text_start, y, text=f"{commit_hash}  {rest}", anchor="w", fill="#e5e7eb", font=("Consolas", 10)
                )
                self.commit_text_blocks.append({"id": text_id, "hash": commit_hash})
            else:
                self.commit_canvas.create_text(text_start, y, text=rest, anchor="w", fill="#9ca3af", font=("Consolas", 10))
            y += row_height

        self.commit_canvas.configure(scrollregion=(0, 0, 1500, max(120, y + 20)))

    def _handle_canvas_click(self, event) -> None:
        canvas_x = self.commit_canvas.canvasx(event.x)
        canvas_y = self.commit_canvas.canvasy(event.y)
        nearest = None
        nearest_dist = 999999
        for node in self.commit_nodes:
            dx = node["x"] - canvas_x
            dy = node["y"] - canvas_y
            dist = (dx * dx) + (dy * dy)
            if dist < nearest_dist:
                nearest = node
                nearest_dist = dist
        if nearest and nearest_dist <= 18 * 18:
            self.select_commit(nearest["hash"])

    def select_commit(self, commit_hash: str) -> None:
        self.selected_commit_hash = commit_hash
        for node in self.commit_nodes:
            selected = node["hash"] == commit_hash
            fill = "#f8fafc" if selected else self.color_for_lane((node["x"] // 10) % 8)
            outline = "#38bdf8" if selected else ""
            width = 2 if selected else 1
            self.commit_canvas.itemconfigure(node["id"], fill=fill, outline=outline, width=width)
        for text_block in self.commit_text_blocks:
            self.commit_canvas.itemconfigure(text_block["id"], fill="#38bdf8" if text_block["hash"] == commit_hash else "#e5e7eb")
        details = self.git("show", "--stat", "--summary", "--format=fuller", commit_hash, check=False)
        self.commit_detail_text.delete("1.0", tk.END)
        self.commit_detail_text.insert("1.0", details or "(Sin detalle de commit)")


def main() -> int:
    app = GitBranchTracker()
    app.mainloop()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
