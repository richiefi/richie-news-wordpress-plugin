/**
 * Ad Slot Card Component
 *
 * Displays a draggable card for an ad slot.
 */

import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Icon, dragHandle, edit, trash } from '@wordpress/icons';

// Get ad providers from PHP (single source of truth)
const AD_PROVIDERS = window.richieFeedEditorSettings?.adProviders || [];

// Generate provider labels dynamically
const PROVIDER_LABELS = AD_PROVIDERS.reduce( ( acc, provider ) => {
	acc[ provider ] = provider.charAt( 0 ).toUpperCase() + provider.slice( 1 );
	return acc;
}, {} );

export default function AdSlotCard( { adSlot, onEdit, onDelete } ) {
	const {
		attributes,
		listeners,
		setNodeRef,
		transform,
		transition,
		isDragging,
	} = useSortable( { id: adSlot.uniqueId } );

	const style = {
		transform: CSS.Transform.toString( transform ),
		transition,
		opacity: isDragging ? 0.5 : 1,
	};

	const provider = adSlot.ad_provider || 'unknown';
	const providerLabel = PROVIDER_LABELS[ provider ] || provider;

	const handleDelete = () => {
		if (
			window.confirm(
				__( 'Are you sure you want to delete this ad slot?', 'richie' )
			)
		) {
			onDelete();
		}
	};

	return (
		<div
			ref={ setNodeRef }
			style={ style }
			className={ `feed-item-card ad-slot-card${
				isDragging ? ' is-dragging' : ''
			}` }
		>
			<div className="card-drag-handle" { ...attributes } { ...listeners }>
				<Icon icon={ dragHandle } />
			</div>

			<div className="card-layout-badge card-layout-badge--ad">
				<span className="dashicons dashicons-megaphone"></span>
				<span className="layout-label">{ __( 'AD', 'richie' ) }</span>
			</div>

			<div className="card-content">
				<div className="card-header">
					<strong className="card-title">
						{ providerLabel } { __( 'Ad Slot', 'richie' ) }
					</strong>
				</div>

				<div className="card-preview">
					<span className="ad-provider-info">
						{ __( 'Provider:', 'richie' ) } { providerLabel }
					</span>
				</div>
			</div>

			<div className="card-actions">
				<Button
					icon={ edit }
					label={ __( 'Edit', 'richie' ) }
					onClick={ onEdit }
				/>
				<Button
					icon={ trash }
					label={ __( 'Delete', 'richie' ) }
					onClick={ handleDelete }
					isDestructive
				/>
			</div>
		</div>
	);
}
