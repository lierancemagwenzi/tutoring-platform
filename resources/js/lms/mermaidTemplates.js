// Starter diagrams offered by the Mermaid editors.
export const MERMAID_TEMPLATES = {
    flowchart: 'flowchart TD\n    A[Start] --> B{Decision}\n    B -->|Yes| C[Option 1]\n    B -->|No| D[Option 2]',
    sequence: 'sequenceDiagram\n    participant A\n    participant B\n    A->>B: Hello B\n    B-->>A: Hello A',
    class: 'classDiagram\n    class Animal {\n      +String name\n      +makeSound()\n    }\n    Animal <|-- Dog',
    state: 'stateDiagram-v2\n    [*] --> Idle\n    Idle --> Running\n    Running --> [*]',
    er: 'erDiagram\n    CUSTOMER ||--o{ ORDER : places\n    ORDER ||--|{ LINE_ITEM : contains',
    journey: 'journey\n    title My Day\n    section Morning\n      Wake up: 5: Me\n      Coffee: 3: Me',
    gantt: 'gantt\n    title Project Plan\n    dateFormat YYYY-MM-DD\n    section Phase 1\n      Task 1 :a1, 2024-01-01, 7d',
}

export const MERMAID_TEMPLATE_OPTIONS = [
    { value: 'flowchart', label: 'Flowchart' },
    { value: 'sequence', label: 'Sequence Diagram' },
    { value: 'class', label: 'Class Diagram' },
    { value: 'state', label: 'State Diagram' },
    { value: 'er', label: 'ER Diagram' },
    { value: 'journey', label: 'Journey Diagram' },
    { value: 'gantt', label: 'Gantt Chart' },
]
