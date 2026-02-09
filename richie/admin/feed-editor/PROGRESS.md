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

7. **Collection Preview Modal** (Phase 6 - Polish)
   - ✅ Created `CollectionPreviewModal.js` component
   - ✅ Added REST API endpoint `/richie/v1/preview-feed/{collection_id}`
   - ✅ Used real public API (`Richie_Public::feed_route_handler()`) to avoid code duplication
   - ✅ Renders articles in featured/small layouts with real data
   - ✅ Shows ad slots in feed preview
   - ✅ Added "Preview Collection" button in header
   - ✅ Fixed data structure reading (layout at article root, not in article_attributes)
   - ✅ Styled modal at 480px max-width for mobile preview
   - ✅ Removed borders, added subtle dividers for feed-like appearance
   - ✅ Fixed modal width and padding issues
     - Fixed CSS selector to target `.components-modal__frame.collection-preview-modal`
     - Overrode WordPress default padding with `!important` flags
     - Content now fills full modal width (480px max-width, responsive)
     - Header padding reduced to 12px 16px
     - Proper flexbox layout with scrollable content area

8. **Collection Deletion & Cleanup** (Phase 7 - Data Safety)
   - ✅ Added `pre_delete_term` hook in `class-richie-admin.php`
     - Prevents deletion from WordPress UI when sources exist
     - Shows error message directing users to Feed Editor
     - Allows deletion when no sources exist (safe cleanup)
   - ✅ Created REST API endpoint `DELETE /richie/v1/editor/collection/{collection_id}`
   - ✅ Implemented `delete_collection()` method in Feed Editor class
     - Removes all sources associated with the collection
     - Removes all ad slots for the collection
     - Cleans up custom order data (draft and published)
     - Deletes the collection term itself
     - Returns count of deleted items in response
   - ✅ Added "Delete collection" button to CollectionSelector component
     - Confirmation dialog warns about permanent deletion
     - Shows success message with cleanup details
     - Automatically refreshes collection list after deletion

9. **Data Model Update** (Phase 4)
   - ✅ `collection_order` added to `richienews_sources` option structure
   - ✅ `class-richie-public.php` `fetch_articles()` uses new order
   - ✅ Backward compatibility maintained (falls back to `sources` array order)

10. **Ordering Sync Between Editor and Legacy Tab** (Phase 8 - Sync)
   - ✅ Legacy tab reorder (`order_source_list`) now also rebuilds `collection_order` entries
     - Ad slots preserved at their original positions
   - ✅ React editor reorder (`save_order`) now also reorders `sources` array keys
     - Only affects sources in the edited collection; others stay in place
   - ✅ Legacy tab display (`source_list`) now sorts by `collection_order` when present
     - Added `sort_sources_by_collection_order()` helper method
     - Handles pre-existing out-of-sync data

#### 🔄 In Progress

None

#### 📋 TODO

11. **Additional Testing & Documentation**
   - Test all CRUD operations
   - Test drag-and-drop reordering in both editor and legacy tab
   - Verify ordering sync between editor and legacy tab
   - Test collection preview modal with various layouts
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
4. ✅ Run `npm install` and `npm run build` in `richie/admin/feed-editor/`
5. ✅ Test in WordPress admin
6. ✅ Update `fetch_articles()` to use `collection_order`
7. ✅ Sync ordering between editor and legacy sources tab
8. ⏳ End-to-end testing of ordering sync

---

## Notes

- Using REST API instead of wp-admin-ajax for cleaner API
- All React components use WordPress components for consistency
- Preview fetches only article titles (fast loading)
- Backward compatibility maintained with existing data structures
- Legacy tabs will be kept as "Advanced" options
