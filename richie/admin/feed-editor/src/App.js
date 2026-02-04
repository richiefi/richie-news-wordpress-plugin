/**
 * Main Feed Editor App Component
 */

import { useState, useCallback } from '@wordpress/element';
import { Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import CollectionSelector from './components/CollectionSelector';
import FeedItemList from './components/FeedItemList';
import SectionModal from './components/SectionModal';
import AdSlotModal from './components/AdSlotModal';
import useFeedItems from './hooks/useFeedItems';

export default function App() {
	const [ selectedCollection, setSelectedCollection ] = useState( null );
	const [ sectionModalOpen, setSectionModalOpen ] = useState( false );
	const [ adSlotModalOpen, setAdSlotModalOpen ] = useState( false );
	const [ editingItem, setEditingItem ] = useState( null );

	const {
		items,
		isLoading,
		error,
		hasUnsavedChanges,
		reorderItems,
		addSection,
		updateSection,
		deleteSection,
		addAdSlot,
		updateAdSlot,
		deleteAdSlot,
		saveOrder,
		refreshItems,
	} = useFeedItems( selectedCollection );

	const handleCollectionChange = useCallback( ( collection ) => {
		setSelectedCollection( collection );
	}, [] );

	const handleAddSection = useCallback( () => {
		setEditingItem( null );
		setSectionModalOpen( true );
	}, [] );

	const handleAddAdSlot = useCallback( () => {
		setEditingItem( null );
		setAdSlotModalOpen( true );
	}, [] );

	const handleEditSection = useCallback( ( section ) => {
		setEditingItem( section );
		setSectionModalOpen( true );
	}, [] );

	const handleEditAdSlot = useCallback( ( adSlot ) => {
		setEditingItem( adSlot );
		setAdSlotModalOpen( true );
	}, [] );

	const handleSectionModalClose = useCallback( () => {
		setSectionModalOpen( false );
		setEditingItem( null );
	}, [] );

	const handleAdSlotModalClose = useCallback( () => {
		setAdSlotModalOpen( false );
		setEditingItem( null );
	}, [] );

	const handleSectionSave = useCallback(
		( sectionData ) => {
			const promise = editingItem
				? updateSection( editingItem.id, sectionData )
				: addSection( sectionData );

			promise.then( () => {
				handleSectionModalClose();
			} );
		},
		[ editingItem, updateSection, addSection, handleSectionModalClose ]
	);

	const handleAdSlotSave = useCallback(
		( adSlotData ) => {
			const promise = editingItem
				? updateAdSlot( editingItem.id, adSlotData )
				: addAdSlot( adSlotData );

			promise.then( () => {
				handleAdSlotModalClose();
			} );
		},
		[ editingItem, updateAdSlot, addAdSlot, handleAdSlotModalClose ]
	);

	return (
		<div className="richie-feed-editor">
			<div className="feed-editor-header">
				<CollectionSelector
					value={ selectedCollection }
					onChange={ handleCollectionChange }
				/>

				{ selectedCollection && (
					<div className="feed-editor-actions">
						<Button variant="secondary" onClick={ handleAddSection }>
							{ __( 'Add Section', 'richie' ) }
						</Button>
						<Button variant="secondary" onClick={ handleAddAdSlot }>
							{ __( 'Add Ad Slot', 'richie' ) }
						</Button>
					</div>
				) }
			</div>

			{ error && (
				<Notice status="error" isDismissible={ false }>
					{ error }
				</Notice>
			) }

			{ hasUnsavedChanges && (
				<Notice status="warning" isDismissible={ false }>
					{ __( 'You have unsaved changes.', 'richie' ) }
					<Button variant="link" onClick={ saveOrder }>
						{ __( 'Save now', 'richie' ) }
					</Button>
				</Notice>
			) }

			{ selectedCollection ? (
				<FeedItemList
					items={ items }
					isLoading={ isLoading }
					onReorder={ reorderItems }
					onEditSection={ handleEditSection }
					onDeleteSection={ deleteSection }
					onEditAdSlot={ handleEditAdSlot }
					onDeleteAdSlot={ deleteAdSlot }
				/>
			) : (
				<div className="feed-editor-empty">
					<p>
						{ __(
							'Select a collection above to manage its feed items.',
							'richie'
						) }
					</p>
				</div>
			) }

			{ sectionModalOpen && (
				<SectionModal
					section={ editingItem }
					collectionId={ selectedCollection }
					onSave={ handleSectionSave }
					onClose={ handleSectionModalClose }
				/>
			) }

			{ adSlotModalOpen && (
				<AdSlotModal
					adSlot={ editingItem }
					collectionId={ selectedCollection }
					onSave={ handleAdSlotSave }
					onClose={ handleAdSlotModalClose }
				/>
			) }
		</div>
	);
}
