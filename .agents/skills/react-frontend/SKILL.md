---
name: react-frontend
description: Build, refactor, and debug React interfaces with responsive styling and accessible components. Use for React, Vite, JavaScript, TypeScript, Tailwind CSS, routing, and frontend state tasks.
---

# React frontend workflow

1. Inspect `package.json`, existing components, routing, styling system, and lint/test scripts before coding.
2. Follow the project's existing JavaScript or TypeScript conventions; do not introduce a second styling framework without approval.
3. Build reusable components with clear props and predictable state. Keep data fetching, presentation, and business logic separated where practical.
4. Make layouts responsive for mobile, tablet, and desktop. Include loading, empty, error, and success states.
5. Use semantic HTML, keyboard-accessible controls, labels, and visible focus states.
6. Preserve the established design system: spacing, typography, colors, breakpoints, and component patterns.
7. For JSX parse errors, inspect the complete surrounding component and balance every opening/closing tag; do not patch only the reported line blindly.
8. Run the available lint, type-check, build, and relevant tests. Fix regressions before reporting completion.
