# Implementation Plan - Homepage Redesign (Tool Grid)

The goal is to redesign the homepage to mimic "iLovePDF" by presenting a grid of specific conversion tools instead of a generic upload zone.

## User Review Required
> [!IMPORTANT]
> **Backend Limitation**: The current PHP backend (`api/convert.php`) only supports Image conversions (JPG, PNG, GIF, WebP). It does **not** yet support PDF, Word, or PowerPoint conversions.
> The UI will be fully implemented as requested, but attempting to perform actual PDF/Office conversions will likely fail or require backend updates (e.g., installing `ghostscript`, `libreoffice`, or using an external API). For now, the UI will be functional but the actual conversion might show an error for unsupported formats.

## Proposed Changes

### Frontend Design
The "Hero" section will be transformed. Instead of immediately showing the "Drop files here" zone, it will display a grid of 6 options:
1.  **JPG to PDF**
2.  **PDF to JPG**
3.  **PPT to PDF**
4.  **PDF to PPT**
5.  **PDF to Word**
6.  **Word to PDF**

### File Changes

#### [MODIFY] [index.html](file:///d:/Projects/File%20Converter/index.html)
-   Refactor `.hero-content` to display a `.tools-grid`.
-   Add the 6 specific tool cards with icons (using FontAwesome).
-   Hide the `#uploadZone` initially.
-   Add a "Back to Tools" button in the upload view.

#### [MODIFY] [style.css](file:///d:/Projects/File%20Converter/assets/css/style.css)
-   Add styles for `.tools-grid` (CSS Grid layout).
-   Style `.tool-card`: White background, shadow, rounded corners, centered icon + text, hover effects (transform/scale, color change).
-   Add utility classes for hiding/showing sections.

#### [MODIFY] [script.js](file:///d:/Projects/File%20Converter/assets/js/script.js)
-   Add logic to handle Tool Card clicks.
-   Variable `currentMode` to track selected conversion type (e.g., `pdf-to-jpg`).
-   Update `handleFiles` validation to accept appropriate file types based on `currentMode` (e.g., only allow .pdf if mode is `pdf-to-word`).
-   Update `populateFormatOptions` to restrict target format based on `currentMode` (fixed target).
-   Add "Back" functionality to return to the grid.

## Verification Plan
1.  **Visual Check**: Open `index.html` via localhost. Verify the grid looks like the reference (clean, card-based).
2.  **Interaction Check**: Click "JPG to PDF". Verify the Upload Zone appears. Verify the "Back" button works.
3.  **Logic Check**: Try to upload a PNG in "PDF to Word" mode -> Should get an error/warning. Try to upload a PDF -> Should accept.
4.  **Conversion Check**: Test a supported flow (e.g., JPG to PNG if we map "JPG to PDF" to a dummy image conv for testing, or just verify the request is sent with correct params). for unsupported flows, verify the error handling.
