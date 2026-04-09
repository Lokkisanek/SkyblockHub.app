# Dashboard Current State

## What the dashboard is
Dashboard is a per-user, slot-based widget canvas. It is available to everyone for viewing, but editing is only allowed for users who are logged in and have a linked Minecraft account.

## Current grid and layout rules
- Dashboard uses a 20x20 grid.
- Widget sizes are fixed by template and cannot be resized in the UI.
- Widgets can be dragged to another grid position while editing.
- The dashboard is saved per user and per slot.
- Slot 1 is free by default.
- Slots 2 and 3 are locked unless the user has an active entitlement.

## How editing works
- The page opens in read-only mode for guests and unlinked users.
- Clicking Edit enables dashboard editing.
- In edit mode, the top toolbar shows the controls for:
  - private/public visibility
  - adding widgets
  - saving changes
  - editing the selected widget settings
- A subtle grid overlay is shown in edit mode so the user can see the cell layout.
- Dragging a widget uses snap-to-grid behavior and tries to move the widget into a free nearby place if the target area is occupied.
- Changes are only persisted when the user clicks Save Changes.

## Current widget types
Only these widgets are currently available.

### 3D Skin View
- Fixed size: 3x5
- Purpose: show a live rotating Minecraft skin model
- Settings:
  - Minecraft username
- Data source:
  - skin is fetched from the linked profile
- Notes:
  - the widget is meant to stay fixed size
  - the model should not zoom dynamically in edit mode

### Inventory GUI
- Fixed size: 8x6
- Purpose: show a live inventory preview
- Settings:
  - Minecraft username
  - Show hotbar toggle
- Data source:
  - inventory items from the linked profile
- Notes:
  - the inventory view is scaled to fit the widget space
  - the widget is fixed size and cannot be resized manually

## Widgets that are no longer available
The dashboard no longer exposes these widget templates in the UI:
- Profile Watcher
- Profile Stats
- Item Flip Watcher
- Event Timer Watcher

If old dashboards still contain any of these widget types, they are treated as legacy data and are not part of the current add-widget flow.

## Backend behavior
- Dashboard pages are rendered from the authenticated user and selected slot.
- The backend validates:
  - allowed widget types
  - widget positions within the 20x20 grid
  - no overlaps
  - minimum widget sizes
  - slot entitlement access
- Widget layout and settings are stored in the database per dashboard slot.

## User flow
1. Open the dashboard page.
2. If you can edit, click Edit.
3. Select a widget to edit its settings in the top toolbar.
4. Drag the widget to a free cell if you want to move it.
5. Click Add Widgets to insert a new fixed-size widget.
6. Click Save Changes to persist the layout.

## Implementation notes
- The dashboard UI is built in Vue.
- The backend dashboard controller supplies the widget templates, live data, and slot access rules.
- The 3D skin widget uses a dedicated player model component.
- The inventory widget uses the SkyBlock inventory grid component.
