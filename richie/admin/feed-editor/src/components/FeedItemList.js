/**
 * Feed Item List Component
 *
 * Sortable container for section and ad slot cards.
 */

import { useCallback, useState } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
	DragOverlay,
} from '@dnd-kit/core';
import {
	SortableContext,
	sortableKeyboardCoordinates,
	verticalListSortingStrategy,
} from '@dnd-kit/sortable';

import SectionCard from './SectionCard';
import AdSlotCard from './AdSlotCard';

export default function FeedItemList( {
	items,
	isLoading,
	onReorder,
	onEditSection,
	onDeleteSection,
	onEditAdSlot,
	onDeleteAdSlot,
} ) {
	const [ activeId, setActiveId ] = useState( null );

	const sensors = useSensors(
		useSensor( PointerSensor ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

	const handleDragStart = useCallback( ( event ) => {
		setActiveId( event.active.id );
	}, [] );

	const handleDragEnd = useCallback(
		( event ) => {
			const { active, over } = event;

			if ( over && active.id !== over.id ) {
				const oldIndex = items.findIndex(
					( item ) => item.uniqueId === active.id
				);
				const newIndex = items.findIndex(
					( item ) => item.uniqueId === over.id
				);

				if ( oldIndex !== -1 && newIndex !== -1 ) {
					onReorder( oldIndex, newIndex );
				}
			}

			setActiveId( null );
		},
		[ items, onReorder ]
	);

	const handleDragCancel = useCallback( () => {
		setActiveId( null );
	}, [] );

	const activeItem = activeId
		? items.find( ( item ) => item.uniqueId === activeId )
		: null;

	if ( isLoading ) {
		return (
			<div className="feed-item-list feed-item-list--loading">
				<Spinner />
				<span>{ __( 'Loading feed items…', 'richie' ) }</span>
			</div>
		);
	}

	if ( ! items || items.length === 0 ) {
		return (
			<div className="feed-item-list feed-item-list--empty">
				<p>
					{ __(
						'No sections or ad slots in this collection yet. Click "Add section" or "Add ad slot" to get started.',
						'richie'
					) }
				</p>
			</div>
		);
	}

	return (
		<DndContext
			sensors={ sensors }
			collisionDetection={ closestCenter }
			onDragStart={ handleDragStart }
			onDragEnd={ handleDragEnd }
			onDragCancel={ handleDragCancel }
		>
			<SortableContext
				items={ items.map( ( item ) => item.uniqueId ) }
				strategy={ verticalListSortingStrategy }
			>
				<div className="feed-item-list-note">
					{ __(
						'Section previews are a sample of current content. They also show duplicated articles if the same source is used in multiple sections. To see how the feed looks in the app, use the "Preview Collection" button above.',
						'richie'
					) }
				</div>
				<div className="feed-item-list">
					{ items.map( ( item ) =>
						item.type === 'source' ? (
							<SectionCard
								key={ item.uniqueId }
								section={ item }
								onEdit={ () => onEditSection( item ) }
								onDelete={ () => onDeleteSection( item.id ) }
							/>
						) : (
							<AdSlotCard
								key={ item.uniqueId }
								adSlot={ item }
								onEdit={ () => onEditAdSlot( item ) }
								onDelete={ () => onDeleteAdSlot( item.id ) }
							/>
						)
					) }
				</div>
			</SortableContext>
			<DragOverlay>
				{ activeItem ? (
					activeItem.type === 'source' ? (
						<SectionCard
							section={ activeItem }
							onEdit={ () => {} }
							onDelete={ () => {} }
						/>
					) : (
						<AdSlotCard
							adSlot={ activeItem }
							onEdit={ () => {} }
							onDelete={ () => {} }
						/>
					)
				) : null }
			</DragOverlay>
		</DndContext>
	);
}
