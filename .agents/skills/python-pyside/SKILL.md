---
name: python-pyside
description: Build, style, debug, and package desktop applications using Python with PySide or PyQt. Use for widgets, layouts, signals, stylesheets, and desktop UI architecture.
---

# Python desktop UI workflow

1. Inspect Python version, PySide/PyQt version, entry point, dependencies, and current widget hierarchy.
2. Match the installed Qt binding (PySide6, PySide2, PyQt6, or PyQt5); do not mix APIs from different bindings.
3. Translate design intent into Qt layouts, widgets, spacing, typography, colors, and reusable stylesheets. React Native CSS is not directly executable in Qt; map concepts deliberately.
4. Keep UI code separate from business logic and persistence. Use signals/slots for communication.
5. Avoid blocking the UI thread with long-running tasks; use QThread, QRunnable, or asynchronous patterns appropriate to the project.
6. Ensure forms have labels, validation feedback, keyboard navigation, and sensible resizing behavior.
7. Keep assets and paths portable; avoid machine-specific absolute paths.
8. Run the application and exercise key screens. Add tests for non-visual logic and verify packaging/imports.
