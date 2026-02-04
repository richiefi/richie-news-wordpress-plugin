# Richie Feed Editor

A visual drag-and-drop editor for managing news feed sections and ad slots in the Richie WordPress plugin.

## Overview

The Feed Editor provides a modern, user-friendly interface for managing content feeds. Instead of managing sections and ad slots separately in different tabs, users can now:

- Select a collection (article set)
- See all sections and ad slots as visual cards
- Drag and drop to reorder items
- See live previews of article titles
- Edit sections and ad slots in modal dialogs

## Features

### Visual Card Interface
- Each section displays as a card with:
  - Layout icon (Featured, Big, Small, etc.)
  - Section name and article count
  - Preview of first 3 article titles
  - Edit and delete buttons
- Ad slots display with provider information

### Drag-and-Drop Reordering
- Intuitive drag-and-drop using @dnd-kit
- Visual feedback during drag
- Saves order automatically

### Comprehensive Modals
- **Section Modal**: All section settings in one place
  - Name, article count, layout style
  - Post type, categories, tags
  - Ordering options and max age
  - Group title, background color
  - Allow duplicates, disable summary

- **Ad Slot Modal**: Simple ad configuration
  - Provider selection (Smart, Google, Readpeak)
  - JSON editor for ad data

### Live Preview
- Fetches and displays current article titles
- Cached for 5 minutes for performance
- Shows first 3 titles + count

## Technology Stack

- **Frontend**: React 18 with WordPress components
- **Build**: @wordpress/scripts
- **Drag-and-Drop**: @dnd-kit/core and @dnd-kit/sortable
- **Styling**: SCSS with WordPress design system
- **Backend**: WordPress REST API

## File Structure

```
richie/admin/feed-editor/
├── package.json                 # Dependencies and build scripts
├── src/
│   ├── index.js                # Entry point
│   ├── App.js                  # Main app component
│   ├── components/
│   │   ├── CollectionSelector.js   # Article set dropdown
│   │   ├── FeedItemList.js         # Sortable container
│   │   ├── SectionCard.js          # Section card with preview
│   │   ├── AdSlotCard.js           # Ad slot card
│   │   ├── SectionModal.js         # Edit/add section dialog
│   │   └── AdSlotModal.js          # Edit/add ad slot dialog
│   ├── hooks/
│   │   └── useFeedItems.js     # Data fetching and state management
│   └── styles/
│       └── editor.scss         # Component styles
├── build/                      # Compiled output (generated)
│   ├── index.js
│   ├── index.css
│   └── index.asset.php
└── PROGRESS.md                 # Development progress diary
```

## REST API Endpoints

All endpoints are under `/wp-json/richie/v1/editor/`:

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/collections` | List all article sets |
| GET | `/items/{collection_id}` | Get sections and ad slots for a collection |
| POST | `/order/{collection_id}` | Save reordered items |
| GET | `/preview/{section_id}` | Get article titles for preview |
| POST | `/section` | Create new section |
| PUT | `/section/{id}` | Update section |
| DELETE | `/section/{id}` | Delete section |
| POST | `/adslot` | Create new ad slot |
| PUT | `/adslot/{id}` | Update ad slot |
| DELETE | `/adslot/{id}` | Delete ad slot |
| GET | `/post-types` | Get available post types |

## Data Model

The feed editor introduces a new `collection_order` field to the existing `richienews_sources` option:

```php
'collection_order' => [
    article_set_id => [
        ['type' => 'source', 'id' => 5],
        ['type' => 'ad', 'id' => 'uuid-here'],
        ['type' => 'source', 'id' => 12],
        // ...
    ],
],
```

This allows unified ordering of sections and ad slots while maintaining backward compatibility.

## Development

### Install Dependencies

```bash
cd richie/admin/feed-editor
npm install
```

### Development Mode

```bash
npm start
```

Starts webpack in watch mode with hot reloading.

### Production Build

```bash
npm run build
```

Compiles and minifies for production.

### Linting

```bash
npm run lint:js    # JavaScript linting
npm run lint:css   # CSS linting
```

## Backward Compatibility

The feed editor is fully backward compatible:

- Existing data structures are preserved
- If no `collection_order` exists, falls back to original behavior
- Legacy "Advanced: Sources" and "Advanced: Ad Slots" tabs remain available
- Published/draft mechanism works as before

## Browser Support

- Modern browsers (Chrome, Firefox, Safari, Edge)
- IE11 not supported (uses modern JavaScript)

## License

Same as Richie WordPress plugin
