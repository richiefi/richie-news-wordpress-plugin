# Richie Feed Editor - Development Progress

## Overview
Building a visual drag-and-drop editor for managing news feed sections and ad slots in the Richie WordPress plugin.

## Technology Stack
- **Frontend**: React with @wordpress/scripts
- **Drag-and-Drop**: @dnd-kit/core and @dnd-kit/sortable
- **Backend**: WordPress REST API + PHP AJAX endpoints
- **Styling**: SCSS with WordPress components

---

## Progress Log

### 2026-02-04 - Initial Setup

#### ✅ Completed

1. **React App Structure** (Phase 1 & 2)
   - Created `package.json` with all dependencies
   - Built React app with the following components:
     - `src/index.js` - Entry point
     - `src/App.js` - Main app container
     - `src/components/CollectionSelector.js` - Dropdown for article sets
     - `src/components/FeedItemList.js` - Sortable container with @dnd-kit
     - `src/components/SectionCard.js` - Card for news sections with preview
     - `src/components/AdSlotCard.js` - Card for ad slots
     - `src/components/SectionModal.js` - Full featured edit modal for sections
     - `src/components/AdSlotModal.js` - Edit modal for ad slots with JSON editor
   - Created custom hook:
     - `src/hooks/useFeedItems.js` - Data fetching and state management
   - Added styles:
     - `src/styles/editor.scss` - Complete styling for all components

2. **Features Implemented in React**
   - Collection selector with loading state
   - Drag-and-drop reordering (using @dnd-kit)
   - Section cards with:
     - Layout badges (Featured, Big, Small, etc.)
     - Article preview (first 3 titles + count)
     - Edit/Delete actions
   - Ad slot cards with provider info
   - Full section modal with all fields:
     - Name, article count, layout style
     - Post type, categories, tags
     - Ordering, max age
     - Group title, background color
     - Checkboxes for duplicates/summary
   - Ad slot modal with JSON editor
   - Unsaved changes warning

3. **PHP Backend** (Phase 1)
   - ✅ Created `class-richie-feed-editor.php` with ALL REST API endpoints:
     - ✅ `GET /richie/v1/editor/collections` - List article sets
     - ✅ `GET /richie/v1/editor/items/{collection_id}` - Get sections + ad slots
     - ✅ `POST /richie/v1/editor/order/{collection_id}` - Save order
     - ✅ `GET /richie/v1/editor/preview/{section_id}` - Get article titles
     - ✅ `POST /richie/v1/editor/section` - Create section
     - ✅ `PUT /richie/v1/editor/section/{id}` - Update section
     - ✅ `DELETE /richie/v1/editor/section/{id}` - Delete section
     - ✅ `POST /richie/v1/editor/adslot` - Create ad slot
     - ✅ `PUT /richie/v1/editor/adslot/{id}` - Update ad slot
     - ✅ `DELETE /richie/v1/editor/adslot/{id}` - Delete ad slot
     - ✅ `GET /richie/v1/editor/post-types` - Get available post types

4. **Integration** (Phase 1)
   - ✅ Modified `class-richie-admin.php` to:
     - ✅ Enqueue built React assets on editor tab
     - ✅ Added `enqueue_feed_editor_assets()` method
   - ✅ Created `partials/richie-feed-editor.php` mount point
   - ✅ Updated `partials/richie-admin-display.php`:
     - ✅ Added "Feed Editor" tab as first tab (default)
     - ✅ Renamed legacy tabs to "Advanced: Sources" / "Advanced: Ad Slots"
   - ✅ Modified `class-richie.php`:
     - ✅ Instantiate feed editor class
     - ✅ Register REST API routes on `rest_api_init`

5. **Build and Test**
   - ✅ Ran `npm install` in `richie/admin/feed-editor/` (1536 packages installed)
   - ✅ Ran `npm run build` to compile React app
     - Built successfully: `build/index.js` (76.1 KiB)
     - Built successfully: `build/index.css` (3.54 KiB)
     - Generated `build/index.asset.php` for WordPress

6. **Testing & Bug Fixes**
   - ✅ Tested in WordPress admin
   - ✅ Fixed `apiFetch` initialization error
     - Added inline script to configure `wp.apiFetch` middleware
     - Added REST API root URL and nonce middleware
     - Added script translations
   - ✅ Rebuilt app with fixes

#### 🔄 In Progress

7. **Final Testing** (current task)
   - ⏳ Verify all endpoints work correctly
   - ⏳ Test CRUD operations (create, edit, delete)
   - ⏳ Test drag-and-drop reordering and save
   - ⏳ Test article preview loading

#### 📋 TODO

6. **Data Model Update** (Phase 4)
   - Add `collection_order` to `richienews_sources` option structure
   - Modify `class-richie-public.php` `fetch_articles()` to use new order
   - Maintain backward compatibility

7. **Additional Testing & Documentation**
   - Test all CRUD operations
   - Test drag-and-drop reordering
   - Test preview loading
   - Verify backward compatibility
   - Update documentation

---

## File Structure

```
richie/admin/feed-editor/
├── package.json                           ✅ Created
├── src/
│   ├── index.js                          ✅ Created
│   ├── App.js                            ✅ Created
│   ├── components/
│   │   ├── CollectionSelector.js         ✅ Created
│   │   ├── FeedItemList.js               ✅ Created
│   │   ├── SectionCard.js                ✅ Created
│   │   ├── AdSlotCard.js                 ✅ Created
│   │   ├── SectionModal.js               ✅ Created
│   │   └── AdSlotModal.js                ✅ Created
│   ├── hooks/
│   │   └── useFeedItems.js               ✅ Created
│   └── styles/
│       └── editor.scss                   ✅ Created
└── build/                                 ⏳ Need to run npm build
    ├── index.js
    ├── index.asset.php
    └── style-index.css

richie/admin/
├── class-richie-feed-editor.php          ✅ Created
├── class-richie-admin.php                ✅ Modified
└── partials/
    ├── richie-feed-editor.php            ✅ Created
    └── richie-admin-display.php          ✅ Modified

richie/includes/
└── class-richie.php                      ✅ Modified
```

---

## Next Steps

1. ✅ Create `class-richie-feed-editor.php` with all REST API endpoints
2. ✅ Create mount point partial
3. ✅ Modify admin class to integrate new editor
4. ⏳ Run `npm install` and `npm run build` in `richie/admin/feed-editor/`
5. ⏳ Test in WordPress admin
6. Update `fetch_articles()` to use `collection_order`

---

## Notes

- Using REST API instead of wp-admin-ajax for cleaner API
- All React components use WordPress components for consistency
- Preview fetches only article titles (fast loading)
- Backward compatibility maintained with existing data structures
- Legacy tabs will be kept as "Advanced" options
