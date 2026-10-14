# ROOTINS status-color system

ROOTINS uses one presentation-only status palette across Admin, Doctor, Patient, and Pharmacy. The palette does not change stored values, validation, routes, permissions, APIs, or workflow rules.

## Shared classes

Use `ri-status` with one tone modifier. The same modifiers can be applied to badges, labels, table cells, cards, timeline items, modal details, and filter options.

| Modifier | Meaning | Examples |
| --- | --- | --- |
| `ri-status--green` | positive/complete | Active, Available, Approved, Paid, Delivered, Completed |
| `ri-status--amber` | waiting/review | Pending, Submitted, Under review, Awaiting payment |
| `ri-status--blue` | scheduled/in motion | Scheduled, In progress, Dispatched, Out for delivery |
| `ri-status--red` | failed/invalid | Failed, Rejected, Cancelled, Returned, Expired |
| `ri-status--gray` | neutral/unset | Draft, Inactive, Unknown, Not available |
| `ri-status--orange` | attention | Clarification required, Warning, Near expiry |
| `ri-status--purple` | external source | Shopify, External source |
| `ri-status--charcoal` | internal source | Self, Internal source |

Example:

```html
<span class="badge ri-status ri-status--amber ri-status-dot">Pending</span>
```

The shared CSS variables are declared in `public/assets/css/rootins-monochrome.css`. `public/assets/js/status-colors.js` adds the same tone classes to existing rendered status elements and AJAX-inserted content by reading the existing display text/classes only.

Status dots are optional. Add `ri-status-dot` when a compact visual indicator is useful; the dot inherits the semantic tone and keeps the label text intact.

## Canonical mapping rules

The dynamic enhancer uses whole-word matching for `active`, so `Inactive` always remains gray. `Warning` and near-expiry states are orange; they are not amber. Badge geometry is shared across static HTML and AJAX-rendered content, with readable text, border, and background tokens for every tone.
