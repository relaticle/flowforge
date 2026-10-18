---
title: "Design System"
description: "The tokens, hooks and states a Modern board exposes for styling, and the rules that keep them stable."
canonical_url: "https://relaticle.github.io/essentials/design-system"
---
# Design System

> The tokens, hooks and states a Modern board exposes for styling, and the rules that keep them stable.

A Modern board is styled through a public contract: CSS variables, class hooks and data attributes. Everything on this page is public API. Anything not listed here is internal and can change in any release.

The contract applies to Modern boards only. Classic boards keep their `flowforge-*` classes and are not affected by anything below.

## Layering

Flowforge ships plain CSS. It contains no Tailwind utilities and no `@apply`, so your app needs no `@source` line and a Tailwind `prefix()` cannot break it.

The stylesheet opens with the layer order Filament uses, and puts every rule in one sublayer:

```css
@layer properties, theme, base, components, utilities;

@layer components.flowforge {
    /* every rule */
}
```

Your unlayered CSS always beats flowforge's. Your layered CSS wins when it sits in `components` itself or in a later layer such as `utilities`.

Filament's own component styles sit in `components`, which outranks the `components.flowforge` sublayer. Flowforge uses `!important` in the few places it must beat them. See [Properties set with !important](#properties-set-with-important).

Filament panels get the stylesheet in the page head. A standalone board links it in its own markup. You do not register or import it.

## Variables

Flowforge never sets a public `--ff-*` variable. Each rule reads the variable through a private alias with a default, so a value you set at any scope wins. That scope can be `:root`, `.ff-board` or one card.

The dark column shows where the default changes under Filament's `.dark` class. A single value applies to both modes.

<table>
<thead>
  <tr>
    <th>
      Variable
    </th>
    
    <th>
      Tier
    </th>
    
    <th>
      Default (light / dark)
    </th>
    
    <th>
      Since
    </th>
    
    <th>
      Used for
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        --ff-surface
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--gray-50)
      </code>
      
       / <code>
        var(--gray-950)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Background of the sticky column-header strip in the lane view. Set it to the page background.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-surface-sunken
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--gray-100)
      </code>
      
       / <code>
        color-mix(in oklab, #fff 5%, transparent)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Background of a column tray and of a lane cell. <code>
        --ff-tray-bg
      </code>
      
       overrides it.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-surface-raised
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        #fff
      </code>
      
       / <code>
        var(--gray-900)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Background of a card and of the filters panel. <code>
        --ff-card-bg
      </code>
      
       overrides it for cards.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-border
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        color-mix(in oklab, var(--gray-950) 5%, transparent)
      </code>
      
       / <code>
        color-mix(in oklab, #fff 10%, transparent)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Ring around a card and around the filters panel. <code>
        --ff-card-ring
      </code>
      
       overrides it for cards.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-text
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--gray-950)
      </code>
      
       / <code>
        #fff
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Column titles, lane titles, card titles and the add row on hover.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-text-muted
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--gray-500)
      </code>
      
       / <code>
        var(--gray-400)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Secondary text and icons: the group-by label, column icons, counts, summaries, tags, card meta, the add row, the avatar overflow, lane counts and icons, and the header subheading.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-accent
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--primary-500)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Outline of an accepting drop target, the drop gap border and the loading text.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-accent-tint
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--primary-50)
      </code>
      
       / <code>
        color-mix(in oklab, var(--primary-500) 10%, transparent)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Fill of a tray or lane cell that accepts the card.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-danger
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--danger-500)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Outline of a refusing tray or lane cell, and the limit hint.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-warning
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        var(--warning-600)
      </code>
      
       / <code>
        var(--warning-400)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Count of a full column.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-radius
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        0.75rem
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Card radius. The tray and lane cell radius is this plus <code>
        0.25rem
      </code>
      
      .
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-gap
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        1.25rem
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Space between columns, on the flat board and in the lane grid.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-ease
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        cubic-bezier(0.16, 1, 0.3, 1)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Easing of tray and cell fills, add row color, card shadow and card action fades.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-duration
      </code>
    </td>
    
    <td>
      semantic
    </td>
    
    <td>
      <code>
        200ms
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Duration of the same transitions.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-column-width
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        300px
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Width of a column, and of each column in the lane grid.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-tray-bg
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        var(--ff-surface-sunken)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Background of a column tray and of a lane cell.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-tray-radius
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        calc(var(--ff-radius) + 0.25rem)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Corner radius of a tray and of a lane cell.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-card-bg
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        var(--ff-surface-raised)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Card background.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-card-ring
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        var(--ff-border)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Card ring color.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-card-radius
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        var(--ff-radius)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Card corner radius.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-card-shadow
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        0 1px 2px 0 rgb(0 0 0 / 0.05)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Card shadow list. It sets the resting and the hover shadow together.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-card-padding
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        0.75rem
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Space inside a card.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-drag-tilt
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        2.5deg
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Rotation of the card while it is dragged.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-drag-shadow
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Shadow list of the dragged card.
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        --ff-placeholder-border
      </code>
    </td>
    
    <td>
      component
    </td>
    
    <td>
      <code>
        var(--ff-accent)
      </code>
    </td>
    
    <td>
      4.2.0
    </td>
    
    <td>
      Border of the drop gap.
    </td>
  </tr>
</tbody>
</table>

Flowforge also reads the Filament palette: `--gray-*`, `--primary-*`, `--danger-*` and `--warning-*`. It never sets them.

Notes that apply to several variables:

- The shadow variables take a shadow list. Use `0 0 #0000` for no shadow, never `none`. Flowforge combines the value with a ring, and `none` makes the whole declaration invalid.
- `--ff-surface` paints the sticky lane-header strip behind the column headers. Set it to the background your page uses, so cards scroll under the strip without showing through.
- `--ff-card-shadow` sets the resting shadow and the hover shadow together.
- The dragged card is a clone on `body`. Flowforge copies the variables above from the card you picked up, so a value set at any scope reaches the clone.

## Hooks

Hooks are class names. Modern markup carries `ff-*` hooks only. The `flowforge-*` classes of classic boards never appear in Modern views, so a rule written for one board does not reach the other.

<table>
<thead>
  <tr>
    <th>
      Hook
    </th>
    
    <th>
      Element
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        ff-board
      </code>
    </td>
    
    <td>
      The board root
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-columns
      </code>
    </td>
    
    <td>
      The row that holds the columns of a board without lanes. Set its padding to space the columns from the page edge
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-column
      </code>
    </td>
    
    <td>
      One column: its header and its tray. In the lane view it holds only the column header
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-column-header
      </code>
    </td>
    
    <td>
      The row with the title, count, summary and actions
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-count
      </code>
    </td>
    
    <td>
      The card count in a column header
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-summary
      </code>
    </td>
    
    <td>
      The column summary text
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-tray
      </code>
    </td>
    
    <td>
      The surface that holds a column's cards. The lane view has no tray, its cells are the surface
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-card
      </code>
    </td>
    
    <td>
      One card
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-card-actions
      </code>
    </td>
    
    <td>
      The action menu in a card's corner
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-add-row
      </code>
    </td>
    
    <td>
      The add-card row, the "Show more cards" button in a lane cell and the "Show more lanes" button
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-lanes
      </code>
    </td>
    
    <td>
      The root of the lane view
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-lane-header
      </code>
    </td>
    
    <td>
      The header of one lane. It stays at the left edge while the lanes scroll sideways, and a click collapses or expands the lane
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-cell
      </code>
    </td>
    
    <td>
      One lane and column intersection, a drop target
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-limit-hint
      </code>
    </td>
    
    <td>
      The hint shown over a full column's tray during a drag. The lane view does not render it
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-card-tag
      </code>
    </td>
    
    <td>
      One tag from <code>
        CardTag
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-card-meta
      </code>
    </td>
    
    <td>
      The row of entries from <code>
        CardMeta
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        ff-card-avatar
      </code>
    </td>
    
    <td>
      One avatar from <code>
        CardAvatar
      </code>
    </td>
  </tr>
</tbody>
</table>

Other `ff-*` classes exist for layout. They are internal.

## States

Flowforge reports state through data attributes. SortableJS toggles classes only, so `flowforge-modern.js` mirrors its states into these attributes.

<table>
<thead>
  <tr>
    <th>
      Attribute
    </th>
    
    <th>
      On
    </th>
    
    <th>
      Meaning
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        data-dragging
      </code>
    </td>
    
    <td>
      The dragged card clone
    </td>
    
    <td>
      A drag is in progress
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        data-placeholder
      </code>
    </td>
    
    <td>
      The drop gap
    </td>
    
    <td>
      Where the card will land
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        data-drop="active"
      </code>
      
       or <code>
        data-drop="blocked"
      </code>
    </td>
    
    <td>
      A column, or a lane cell
    </td>
    
    <td>
      The hovered target accepts or refuses the card
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        data-full
      </code>
    </td>
    
    <td>
      A column
    </td>
    
    <td>
      The column is at its limit
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        data-collapsed
      </code>
    </td>
    
    <td>
      A column, or a lane header
    </td>
    
    <td>
      The column shows as a collapsed rail, or the lane's cells are hidden
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        data-pending
      </code>
    </td>
    
    <td>
      A card
    </td>
    
    <td>
      Its move is saving
    </td>
  </tr>
</tbody>
</table>

The dragged clone sits on `body`, outside `.ff-board`. A rule that must reach it starts at `.ff-card[data-dragging]`, and the visible card is its `.ff-card-body` child.

## Customizing

Set variables in your theme and add hook rules beside them:

```css
:root { --ff-accent: var(--primary-600); --ff-radius: .5rem; --ff-drag-tilt: 1.5deg; }
.dark { --ff-card-bg: var(--gray-900); }
.ff-column[data-drop="blocked"] .ff-tray { outline: 2px dashed var(--ff-danger); }
```

The first line sets three variables for every board. The second sets a dark mode value. The third is a hook rule: it outlines a column that refuses the card you are dragging.

A variable set on `.ff-board` applies to that board only. A variable set on one card applies to that card only.

### Tailwind apps

Tailwind v4 apps can map variables to utilities with `@theme inline`:

```css
@theme inline {
    --color-ff-surface: var(--ff-surface, var(--gray-50));
    --color-ff-accent: var(--ff-accent, var(--primary-500));
}
```

Flowforge never sets `--ff-*` variables, so each mapped variable needs the fallback shown, which matches the light default in the variable table. Set the variable under `.dark` for a dark value. `inline` keeps each utility reading the variable at use time, so your overrides still apply.

### Card components

`CardTitle`, `CardTag`, `CardMeta` and `CardAvatar` are styled by the Modern stylesheet and designed for Modern boards. On a classic board they render `ff-card-*` markup, and their look is not guaranteed: after a `wire:navigate` visit to a Modern board the Modern stylesheet stays in the page head and can style them. See [Card Components](https://relaticle.github.io/essentials/customization#card-components).

## Properties set with !important

Flowforge sets these properties with `!important`, to beat Filament component styles that outrank its sublayer. To override one, put your own `!important` rule in an earlier layer, because cascade layers reverse the order for `!important`. An unlayered or later-layer `!important` rule loses to flowforge's layered one.

```css
@layer base {
    .ff-card-meta .fi-badge { color: red !important; }
}
```

<table>
<thead>
  <tr>
    <th>
      Selector
    </th>
    
    <th>
      Properties
    </th>
  </tr>
</thead>

<tbody>
  <tr>
    <td>
      <code>
        .ff-card-meta .fi-badge
      </code>
    </td>
    
    <td>
      <code>
        padding
      </code>
      
      , <code>
        font-weight
      </code>
      
      , <code>
        color
      </code>
      
      , <code>
        background
      </code>
      
      , <code>
        box-shadow
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        .ff-card-meta .fi-in-text-item
      </code>
    </td>
    
    <td>
      <code>
        font-size
      </code>
      
      , <code>
        color
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        .ff-filters
      </code>
    </td>
    
    <td>
      <code>
        overflow
      </code>
      
      , <code>
        background-color
      </code>
      
      , <code>
        box-shadow
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        .ff-filters-toolbar
      </code>
    </td>
    
    <td>
      <code>
        padding-inline
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        .ff-filters-panel--above
      </code>
    </td>
    
    <td>
      <code>
        border-bottom-width
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        .ff-filters-panel--before
      </code>
      
      , <code>
        .ff-filters-panel--after
      </code>
    </td>
    
    <td>
      <code>
        background-color
      </code>
      
      , <code>
        border-radius
      </code>
      
      , <code>
        box-shadow
      </code>
    </td>
  </tr>
  
  <tr>
    <td>
      <code>
        .ff-filters-indicators
      </code>
    </td>
    
    <td>
      <code>
        padding-inline
      </code>
    </td>
  </tr>
</tbody>
</table>

The `.ff-filters*` classes are internal. They are listed because they carry `!important`, and they can change in any release.

## Versioning

- Adding a variable, hook or state is a minor release.
- Renaming or removing one happens in a major release only. The old name keeps working for that major through a fallback, `var(--ff-new, var(--ff-old))`, and this page marks it deprecated.

The Since column in the variable table records the release that added each variable.


## Sitemap

See the full [sitemap](https://relaticle.github.io/sitemap.md) for all pages.
