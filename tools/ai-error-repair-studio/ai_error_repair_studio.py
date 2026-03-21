import json
import re
import tkinter as tk
from copy import deepcopy
from pathlib import Path
from tkinter import messagebox, ttk
from tkinter.scrolledtext import ScrolledText


WINDOW_TITLE = "AI Error Repair Studio"
SCRIPT_DIR = Path(__file__).resolve().parent
DATA_DIR = SCRIPT_DIR / "data"
SAVED_CASES_DIR = SCRIPT_DIR / "saved_cases"
KNOWLEDGE_BASE_PATH = DATA_DIR / "knowledge_base.json"

CASE_TEXT_FIELDS = [
    "problem_summary",
    "symptoms",
    "reproduction_pattern",
    "environment_notes",
    "evidence",
    "actions_taken",
    "results",
    "working_theories",
    "next_steps",
    "lessons",
]


def slugify(value: str) -> str:
    slug = re.sub(r"[^a-zA-Z0-9._-]+", "-", value.strip().lower()).strip("-")
    return slug or "untitled-case"


def normalize_tags(raw_tags: str) -> list[str]:
    tags = []
    for item in raw_tags.split(","):
        clean = item.strip().lower()
        if clean and clean not in tags:
            tags.append(clean)
    return tags


class AIErrorRepairStudio(tk.Tk):
    def __init__(self) -> None:
        super().__init__()
        self.title(WINDOW_TITLE)
        self.geometry("1620x980")
        self.minsize(1380, 860)

        self.case_path: Path | None = None
        self.knowledge_base = self.load_knowledge_base()
        self.current_case = self.build_blank_case()

        self.case_id_var = tk.StringVar()
        self.title_var = tk.StringVar()
        self.system_var = tk.StringVar()
        self.status_var = tk.StringVar()
        self.tags_var = tk.StringVar()
        self.outcome_var = tk.StringVar()

        self.case_text_widgets: dict[str, ScrolledText] = {}
        self.knowledge_case_ids: list[str] = []

        self._build_ui()
        self.populate_case_form(self.get_default_case())
        self.refresh_knowledge_list()
        self.refresh_blueprint_view()
        self.generate_prompt()

    def _build_ui(self) -> None:
        self.columnconfigure(0, weight=1)
        self.rowconfigure(1, weight=1)
        self.rowconfigure(2, weight=1)

        top = ttk.Frame(self, padding=10)
        top.grid(row=0, column=0, sticky="ew")
        for index in range(8):
            top.columnconfigure(index, weight=1 if index in {1, 3, 5} else 0)

        ttk.Label(top, text="Caso").grid(row=0, column=0, sticky="w")
        ttk.Entry(top, textvariable=self.title_var).grid(row=0, column=1, sticky="ew", padx=(8, 10))
        ttk.Label(top, text="Sistema").grid(row=0, column=2, sticky="w")
        ttk.Entry(top, textvariable=self.system_var).grid(row=0, column=3, sticky="ew", padx=(8, 10))
        ttk.Label(top, text="Estado").grid(row=0, column=4, sticky="w")
        ttk.Entry(top, textvariable=self.status_var).grid(row=0, column=5, sticky="ew", padx=(8, 10))
        ttk.Button(top, text="Nuevo caso", command=self.new_case).grid(row=0, column=6, padx=(0, 8))
        ttk.Button(top, text="Guardar caso", command=self.save_current_case).grid(row=0, column=7)

        info = ttk.Frame(top)
        info.grid(row=1, column=0, columnspan=8, sticky="ew", pady=(10, 0))
        for index in range(8):
            info.columnconfigure(index, weight=1 if index in {1, 3, 5} else 0)

        ttk.Label(info, text="Case ID").grid(row=0, column=0, sticky="w")
        ttk.Entry(info, textvariable=self.case_id_var).grid(row=0, column=1, sticky="ew", padx=(8, 10))
        ttk.Label(info, text="Tags").grid(row=0, column=2, sticky="w")
        ttk.Entry(info, textvariable=self.tags_var).grid(row=0, column=3, sticky="ew", padx=(8, 10))
        ttk.Label(info, text="Resultado").grid(row=0, column=4, sticky="w")
        ttk.Entry(info, textvariable=self.outcome_var).grid(row=0, column=5, sticky="ew", padx=(8, 10))
        ttk.Button(info, text="Generar prompt IA", command=self.generate_prompt).grid(row=0, column=6, padx=(0, 8))
        ttk.Button(info, text="Copiar prompt", command=self.copy_prompt).grid(row=0, column=7)

        notebook = ttk.Notebook(self)
        notebook.grid(row=1, column=0, sticky="nsew", padx=10, pady=(0, 10))

        self.case_tab = ttk.Frame(notebook, padding=10)
        self.case_tab.columnconfigure(0, weight=1)
        self.case_tab.columnconfigure(1, weight=1)
        notebook.add(self.case_tab, text="Caso actual")

        self.knowledge_tab = ttk.Frame(notebook, padding=10)
        self.knowledge_tab.columnconfigure(0, weight=1)
        self.knowledge_tab.columnconfigure(1, weight=2)
        self.knowledge_tab.rowconfigure(1, weight=1)
        notebook.add(self.knowledge_tab, text="Conocimiento")

        self.prompt_tab = ttk.Frame(notebook, padding=10)
        self.prompt_tab.columnconfigure(0, weight=1)
        self.prompt_tab.rowconfigure(1, weight=1)
        notebook.add(self.prompt_tab, text="Prompt IA")

        self.system_tab = ttk.Frame(notebook, padding=10)
        self.system_tab.columnconfigure(0, weight=1)
        self.system_tab.rowconfigure(0, weight=1)
        notebook.add(self.system_tab, text="Sistema")

        self._build_case_tab()
        self._build_knowledge_tab()
        self._build_prompt_tab()
        self._build_system_tab()

        output_frame = ttk.LabelFrame(self, text="Salida / bitacora", padding=8)
        output_frame.grid(row=2, column=0, sticky="nsew", padx=10, pady=(0, 10))
        output_frame.columnconfigure(0, weight=1)
        output_frame.rowconfigure(0, weight=1)
        self.output_text = ScrolledText(output_frame, wrap="word", height=12)
        self.output_text.grid(row=0, column=0, sticky="nsew")

    def _build_case_tab(self) -> None:
        self.case_tab.rowconfigure(0, weight=1)
        self.case_tab.rowconfigure(1, weight=1)
        self.case_tab.rowconfigure(2, weight=1)
        self.case_tab.rowconfigure(3, weight=1)
        self.case_tab.rowconfigure(4, weight=1)

        layout = [
            ("Resumen del problema", "problem_summary", 0, 0),
            ("Sintomas observados", "symptoms", 0, 1),
            ("Patron de reproduccion", "reproduction_pattern", 1, 0),
            ("Entorno / notas", "environment_notes", 1, 1),
            ("Evidencia", "evidence", 2, 0),
            ("Acciones realizadas", "actions_taken", 2, 1),
            ("Resultados", "results", 3, 0),
            ("Hipotesis de trabajo", "working_theories", 3, 1),
            ("Siguientes pasos", "next_steps", 4, 0),
            ("Lecciones / entrenamiento", "lessons", 4, 1),
        ]

        for title, field_name, row, column in layout:
            frame = ttk.LabelFrame(self.case_tab, text=title, padding=6)
            frame.grid(row=row, column=column, sticky="nsew", padx=(0, 8) if column == 0 else 0, pady=(0, 8))
            frame.columnconfigure(0, weight=1)
            frame.rowconfigure(0, weight=1)
            widget = ScrolledText(frame, wrap="word", height=8)
            widget.grid(row=0, column=0, sticky="nsew")
            self.case_text_widgets[field_name] = widget

    def _build_knowledge_tab(self) -> None:
        ttk.Label(self.knowledge_tab, text="Casos conocidos").grid(row=0, column=0, sticky="w")
        ttk.Label(self.knowledge_tab, text="Detalle del caso / conocimiento util").grid(row=0, column=1, sticky="w")

        left = ttk.Frame(self.knowledge_tab)
        left.grid(row=1, column=0, sticky="nsew", padx=(0, 10))
        left.columnconfigure(0, weight=1)
        left.rowconfigure(1, weight=1)

        case_actions = ttk.Frame(left)
        case_actions.grid(row=0, column=0, sticky="ew", pady=(0, 8))
        case_actions.columnconfigure(0, weight=1)
        case_actions.columnconfigure(1, weight=1)
        case_actions.columnconfigure(2, weight=1)
        ttk.Button(case_actions, text="Recargar base", command=self.reload_knowledge_base).grid(row=0, column=0, sticky="ew", padx=(0, 6))
        ttk.Button(case_actions, text="Cargar en editor", command=self.load_selected_case_into_form).grid(row=0, column=1, sticky="ew", padx=(0, 6))
        ttk.Button(case_actions, text="Usar como base", command=self.clone_selected_case_into_form).grid(row=0, column=2, sticky="ew")

        self.knowledge_list = tk.Listbox(left, exportselection=False)
        self.knowledge_list.grid(row=1, column=0, sticky="nsew")
        self.knowledge_list.bind("<<ListboxSelect>>", lambda _event: self.show_selected_case_detail())

        right = ttk.Frame(self.knowledge_tab)
        right.grid(row=1, column=1, sticky="nsew")
        right.columnconfigure(0, weight=1)
        right.rowconfigure(0, weight=1)
        self.knowledge_detail_text = ScrolledText(right, wrap="word")
        self.knowledge_detail_text.grid(row=0, column=0, sticky="nsew")

    def _build_prompt_tab(self) -> None:
        header = ttk.Frame(self.prompt_tab)
        header.grid(row=0, column=0, sticky="ew", pady=(0, 8))
        header.columnconfigure(0, weight=1)
        ttk.Label(
            header,
            text="Este prompt esta pensado para pegarlo despues en un modelo de IA cuando conectemos la capa automatica.",
        ).grid(row=0, column=0, sticky="w")
        self.prompt_text = ScrolledText(self.prompt_tab, wrap="word")
        self.prompt_text.grid(row=1, column=0, sticky="nsew")

    def _build_system_tab(self) -> None:
        self.system_text = ScrolledText(self.system_tab, wrap="word")
        self.system_text.grid(row=0, column=0, sticky="nsew")

    def append_output(self, text: str) -> None:
        self.output_text.insert("end", text.strip() + "\n")
        self.output_text.see("end")

    def build_blank_case(self) -> dict:
        case = {
            "id": "",
            "title": "",
            "system": "Windows desktop support",
            "status": "draft",
            "tags": [],
            "outcome": "",
        }
        for field_name in CASE_TEXT_FIELDS:
            case[field_name] = ""
        return case

    def load_knowledge_base(self) -> dict:
        with KNOWLEDGE_BASE_PATH.open("r", encoding="utf-8") as handle:
            return json.load(handle)

    def save_knowledge_base(self) -> None:
        with KNOWLEDGE_BASE_PATH.open("w", encoding="utf-8") as handle:
            json.dump(self.knowledge_base, handle, indent=2, ensure_ascii=False)

    def get_default_case(self) -> dict:
        cases = self.knowledge_base.get("cases", [])
        if cases:
            return deepcopy(cases[0])
        return self.build_blank_case()

    def case_from_form(self) -> dict:
        case = {
            "id": self.case_id_var.get().strip(),
            "title": self.title_var.get().strip(),
            "system": self.system_var.get().strip(),
            "status": self.status_var.get().strip(),
            "tags": normalize_tags(self.tags_var.get()),
            "outcome": self.outcome_var.get().strip(),
        }
        for field_name in CASE_TEXT_FIELDS:
            widget = self.case_text_widgets[field_name]
            case[field_name] = widget.get("1.0", "end").strip()
        return case

    def populate_case_form(self, case: dict) -> None:
        self.current_case = deepcopy(case)
        self.case_id_var.set(case.get("id", ""))
        self.title_var.set(case.get("title", ""))
        self.system_var.set(case.get("system", ""))
        self.status_var.set(case.get("status", ""))
        self.tags_var.set(", ".join(case.get("tags", [])))
        self.outcome_var.set(case.get("outcome", ""))
        for field_name in CASE_TEXT_FIELDS:
            widget = self.case_text_widgets[field_name]
            widget.delete("1.0", "end")
            widget.insert("1.0", case.get(field_name, ""))

    def new_case(self) -> None:
        self.case_path = None
        self.populate_case_form(self.build_blank_case())
        self.append_output("Formulario reiniciado para un caso nuevo.")
        self.generate_prompt()

    def ensure_case_identity(self, case: dict) -> dict:
        if not case["title"]:
            raise ValueError("El caso necesita titulo.")
        if not case["id"]:
            case["id"] = slugify(case["title"])
        if not case["status"]:
            case["status"] = "draft"
        if not case["system"]:
            case["system"] = "Windows desktop support"
        return case

    def save_current_case(self) -> None:
        try:
            case = self.ensure_case_identity(self.case_from_form())
        except ValueError as exc:
            messagebox.showwarning(WINDOW_TITLE, str(exc))
            return

        case_path = SAVED_CASES_DIR / f"{case['id']}.json"
        with case_path.open("w", encoding="utf-8") as handle:
            json.dump(case, handle, indent=2, ensure_ascii=False)

        self.case_path = case_path
        self.upsert_case_in_knowledge_base(case)
        self.case_id_var.set(case["id"])
        self.populate_case_form(case)
        self.refresh_knowledge_list()
        self.generate_prompt()
        self.append_output(f"Caso guardado en {case_path}")

    def upsert_case_in_knowledge_base(self, case: dict) -> None:
        cases = self.knowledge_base.setdefault("cases", [])
        for index, existing in enumerate(cases):
            if existing.get("id") == case["id"]:
                cases[index] = deepcopy(case)
                self.save_knowledge_base()
                return
        cases.append(deepcopy(case))
        self.save_knowledge_base()

    def reload_knowledge_base(self) -> None:
        self.knowledge_base = self.load_knowledge_base()
        self.refresh_knowledge_list()
        self.refresh_blueprint_view()
        self.append_output("Base de conocimiento recargada.")
        self.generate_prompt()

    def refresh_knowledge_list(self) -> None:
        self.knowledge_list.delete(0, "end")
        self.knowledge_case_ids.clear()
        for case in self.knowledge_base.get("cases", []):
            label = f"{case.get('id', '-')} | {case.get('title', '(sin titulo)')} | {case.get('status', '-')}"
            self.knowledge_list.insert("end", label)
            self.knowledge_case_ids.append(case.get("id", ""))
        if self.knowledge_case_ids:
            self.knowledge_list.selection_clear(0, "end")
            self.knowledge_list.selection_set(0)
            self.show_selected_case_detail()

    def selected_knowledge_case(self) -> dict | None:
        selection = self.knowledge_list.curselection()
        if not selection:
            return None
        case_id = self.knowledge_case_ids[selection[0]]
        for case in self.knowledge_base.get("cases", []):
            if case.get("id") == case_id:
                return deepcopy(case)
        return None

    def format_case_detail(self, case: dict) -> str:
        sections = [
            ("Case ID", case.get("id", "")),
            ("Titulo", case.get("title", "")),
            ("Sistema", case.get("system", "")),
            ("Estado", case.get("status", "")),
            ("Tags", ", ".join(case.get("tags", []))),
            ("Resultado", case.get("outcome", "")),
            ("Resumen", case.get("problem_summary", "")),
            ("Sintomas", case.get("symptoms", "")),
            ("Patron", case.get("reproduction_pattern", "")),
            ("Entorno", case.get("environment_notes", "")),
            ("Evidencia", case.get("evidence", "")),
            ("Acciones", case.get("actions_taken", "")),
            ("Resultados", case.get("results", "")),
            ("Hipotesis", case.get("working_theories", "")),
            ("Siguientes pasos", case.get("next_steps", "")),
            ("Lecciones", case.get("lessons", "")),
        ]
        lines = []
        for title, value in sections:
            lines.append(f"{title}\n{value}\n")
        return "\n".join(lines).strip()

    def show_selected_case_detail(self) -> None:
        case = self.selected_knowledge_case()
        self.knowledge_detail_text.delete("1.0", "end")
        if not case:
            return
        self.knowledge_detail_text.insert("1.0", self.format_case_detail(case))

    def load_selected_case_into_form(self) -> None:
        case = self.selected_knowledge_case()
        if not case:
            messagebox.showinfo(WINDOW_TITLE, "Selecciona un caso conocido.")
            return
        self.case_path = SAVED_CASES_DIR / f"{case.get('id', 'case')}.json"
        self.populate_case_form(case)
        self.generate_prompt()
        self.append_output(f"Caso cargado en editor: {case.get('id', '-')}")

    def clone_selected_case_into_form(self) -> None:
        case = self.selected_knowledge_case()
        if not case:
            messagebox.showinfo(WINDOW_TITLE, "Selecciona un caso conocido.")
            return
        case["id"] = ""
        case["status"] = "draft"
        case["outcome"] = ""
        self.case_path = None
        self.populate_case_form(case)
        self.generate_prompt()
        self.append_output("Caso base cargado como plantilla editable.")

    def relevant_cases(self, current_case: dict, limit: int = 3) -> list[dict]:
        current_tags = set(current_case.get("tags", []))
        title_tokens = set(slugify(current_case.get("title", "")).split("-"))
        ranked = []
        for case in self.knowledge_base.get("cases", []):
            if case.get("id") == current_case.get("id"):
                continue
            score = 0
            score += len(current_tags.intersection(case.get("tags", []))) * 3
            score += len(title_tokens.intersection(set(slugify(case.get("title", "")).split("-"))))
            if score > 0:
                ranked.append((score, case))
        ranked.sort(key=lambda item: item[0], reverse=True)
        return [deepcopy(case) for score, case in ranked[:limit]]

    def generate_prompt(self) -> None:
        current_case = self.case_from_form()
        relevant = self.relevant_cases(current_case)
        blueprint = self.knowledge_base.get("system_blueprint", {})

        sections = [
            "SYSTEM ROLE",
            blueprint.get("assistant_role", ""),
            "",
            "OPERATION MODEL",
        ]
        for item in blueprint.get("workflow", []):
            sections.append(f"- {item}")

        sections.extend(["", "SAFETY RULES"])
        for item in blueprint.get("safety_rules", []):
            sections.append(f"- {item}")

        sections.extend(["", "CURRENT CASE", self.format_case_detail(current_case), "", "RELEVANT KNOWLEDGE"])

        if relevant:
            for case in relevant:
                sections.append(self.format_case_detail(case))
                sections.append("")
        else:
            sections.append("No matching prior cases yet.")
            sections.append("")

        sections.extend(
            [
                "REQUEST TO FUTURE AI",
                "1. Summarize the situation without inventing facts.",
                "2. Identify the most likely fault domains.",
                "3. Propose the lowest-risk next diagnostic actions.",
                "4. Separate confirmed findings from hypotheses.",
                "5. Capture any reusable lesson back into the knowledge base format.",
            ]
        )

        prompt = "\n".join(sections).strip()
        self.prompt_text.delete("1.0", "end")
        self.prompt_text.insert("1.0", prompt)

    def copy_prompt(self) -> None:
        prompt = self.prompt_text.get("1.0", "end").strip()
        if not prompt:
            messagebox.showinfo(WINDOW_TITLE, "No hay prompt para copiar.")
            return
        self.clipboard_clear()
        self.clipboard_append(prompt)
        self.append_output("Prompt copiado al portapapeles.")

    def refresh_blueprint_view(self) -> None:
        blueprint = self.knowledge_base.get("system_blueprint", {})
        lines = [
            "Objetivo del sistema",
            blueprint.get("goal", ""),
            "",
            "Rol esperado",
            blueprint.get("assistant_role", ""),
            "",
            "Flujo operativo",
        ]
        for item in blueprint.get("workflow", []):
            lines.append(f"- {item}")
        lines.extend(["", "Reglas de seguridad"])
        for item in blueprint.get("safety_rules", []):
            lines.append(f"- {item}")
        lines.extend(["", "Campos del caso"])
        for item in blueprint.get("case_fields", []):
            lines.append(f"- {item}")

        self.system_text.delete("1.0", "end")
        self.system_text.insert("1.0", "\n".join(lines).strip())


def main() -> int:
    DATA_DIR.mkdir(parents=True, exist_ok=True)
    SAVED_CASES_DIR.mkdir(parents=True, exist_ok=True)
    app = AIErrorRepairStudio()
    app.mainloop()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
