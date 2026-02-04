/**
 * Feed Item List Component
 *
 * Sortable container for section and ad slot cards.
 */

import { useCallback } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import {
	DndContext,
	closestCenter,
	KeyboardSensor,
	PointerSensor,
	useSensor,
	useSensors,
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
	const sensors = useSensors(
		useSensor( PointerSensor ),
		useSensor( KeyboardSensor, {
			coordinateGetter: sortableKeyboardCoordinates,
		} )
	);

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
		},
		[ items, onReorder ]
	);

	if ( isLoading ) {
		return (
			<div className="feed-item-list feed-item-list--loading">
				<Spinner />
				<span>{ __( 'Loading feed items...', 'richie' ) }</span>
			</div>
		);
	}

	if ( ! items || items.length === 0 ) {
		return (
			<div className="feed-item-list feed-item-list--empty">
				<p>
					{ __(
						'No sections or ad slots in this collection yet. Click "Add Section" or "Add Ad Slot" to get started.',
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
			onDragEnd={ handleDragEnd }
		>
			<SortableContext
				items={ items.map( ( item ) => item.uniqueId ) }
				strategy={ verticalListSortingStrategy }
			>
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
		</DndContext>
	);
}
